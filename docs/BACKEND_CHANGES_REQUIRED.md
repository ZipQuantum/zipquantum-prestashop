# Backend changes required in zipquantum-app

Implementation status: completed and deployed to `https://a.zq.tn` on 2026-09-02.

The provider catalog, provider-bound handshake routes, public PKCE client,
PrestaShop sync validation, `ps:` idempotency and regression suite described
below are now present. The production deployment was backed up at
`/home/supportproxi/codex-backups/zq-prestashop-backend-20260902-191730`.

Original read-only snapshot: 2026-09-02, branch `codex/consolidation-20260821`.

This document preserves the implementation contract that was prepared while the Shopify multi-provider work was active. After that work completed, the listed PrestaShop provider changes were implemented in `zipquantum-app`, covered by the provider regression suite, deployed with the backup above and verified by a real PrestaShop OAuth and Smart Link flow.

## Already provider-neutral - preserve as-is

- The integration schema stores `provider` on handshakes and installations; link associations already belong to an installation. No migration is required for PrestaShop.
- Authenticated context and association listing are installation-scoped.
- Managed-link creation already writes `Link.source` from the authenticated installation provider.
- Managed/attached conflict handling, transactions, row locks, managed-field allowlisting, QR responses, click totals, refresh rotation and revocation are reusable.

## 1. Extend the provider catalog

In `config/integrations.php`, add:

```php
'prestashop' => [
    'provider' => 'prestashop',
    'client_id' => 'zipquantum-prestashop',
    'public_client' => true,
    'polling_prefix' => 'zqps_poll',
    'token_prefix' => 'zqps',
    'idempotency_prefix' => 'ps',
    'scopes' => [
        'account:read', 'domains:read', 'links:read',
        'links:write', 'qr:write', 'analytics:read',
    ],
],
```

Add the same metadata keys to WordPress and Shopify, retaining their current values. Shopify remains the only confidential client. This catalog should replace the two-provider ternaries currently used for authorization-code, access-token, refresh-token and idempotency prefixes.

Affected current code:

- `app/Services/IntegrationOAuthService.php`: lines containing `provider === 'shopify' ? ... : ...`, plus `clientIsAllowed()`.
- `app/Http/Controllers/Api/IntegrationController.php`: the provider allowlist and `wordpress ? wp : shopify` idempotency branch.
- `app/Http/Controllers/IntegrationOAuthController.php`: the token `client_id` allowlist.

## 2. Add provider-bound PrestaShop handshake routes

Add public API routes:

- `POST /api/v1/integrations/prestashop/handshakes`
- `POST /api/v1/integrations/prestashop/handshakes/{handshake}/poll`

Add authenticated web routes:

- `GET /integrations/prestashop/authorize/{handshake}`
- `POST /integrations/prestashop/authorize/{handshake}`
- `GET /integrations/prestashop/callback`

Use the existing WordPress public-client flow but pass a provider key into shared handshake/consent methods. The provider selects client ID, scopes, URL names, display copy and prefixes. Every route that receives a handshake must reject a handshake whose stored provider differs from the route provider; do not let a WordPress handshake be polled or approved through a PrestaShop route.

Affected files:

- `routes/api.php`
- `routes/web.php`
- `app/Http/Controllers/IntegrationOAuthController.php`
- `app/Services/IntegrationOAuthService.php`
- provider-neutral consent and completion views, or new `resources/views/oauth/prestashop-*.blade.php` views

The current `createHandshake()` hard-codes `integrations.wordpress.*`, a `zqwp_poll_` prefix and the WordPress authorization route. Parameterize those exact points. Shopify's direct confidential-client flow must remain unchanged.

## 3. Accept PrestaShop as a public OAuth client

In `IntegrationOAuthController::token()`, include `integrations.prestashop.client_id` in validation. In `IntegrationOAuthService::clientIsAllowed()`, accept both configured public clients without a secret and require the configured secret only for Shopify.

During exchange and refresh, continue binding the grant/token to its original client ID, resource and installation. Derive token prefixes from the authenticated installation provider rather than a two-way ternary. The token response's `installation_id` remains the server-issued `IntegrationInstallation::uuid`; this is the UUID used in the module's idempotency header.

## 4. Generalize site-identity copy without weakening clone protection

`IntegrationSiteIdentity` already normalizes scheme, host, port and path, and rejects embedded credentials. Change its WordPress-specific validation messages to provider-neutral "integration site URL" copy. Preserve the comparison and the `connect`, `move`, `reconnect` behavior exactly.

No database change is required: `site_origin`, `site_port`, `site_path`, `installation_uuid`, nonce hash and provider are already present.

## 5. Extend sync validation and idempotency

In `app/Http/Controllers/Api/IntegrationController.php`:

- add `prestashop` to the provider allowlist, preferably deriving the list from the provider catalog;
- keep the existing equality check between request provider and authenticated installation provider;
- map PrestaShop to idempotency prefix `ps`;
- optionally enforce provider-specific object types; PrestaShop 1.0 allows only `product`, `category`, `promotion`;
- keep `Link.source = $provider`, which is already correct;
- keep current installation/user scoping, transactions, row locking, managed-field allowlist and management-mode conflict behavior.

Required header format:

```text
ps:{server_installation_id}:{object_type}:{object_id}:{canonical_payload_sha256}
```

`server_installation_id` is the OAuth token response field, not the local PrestaShop installation UUID sent during handshake.

## 6. Preserve analytics, QR and deletion behavior

The existing list endpoint and `LinkResource` already expose aggregate `smart_link.clicks`; the sync response already appends QR data. Preserve these shapes for PrestaShop tokens. Do not add visitor/event ingestion, IP enrichment or device data.

Keep `smart_link.short_link` HTTPS-only and return QR data as base64 PNG/JPEG or inert SVG. SVG output must not contain scripts, event handlers, external references, entities or foreign objects; the module independently rejects unsafe display values.

Do not add automatic remote deletion when a PrestaShop object disappears, a token is revoked, an installation is quarantined, or the module is uninstalled. If installation associations are ever pruned, the user-owned `Link` row must remain.

## 7. Required backend tests

Add a PrestaShop suite beside `WordPressIntegrationOAuthTest` and `ShopifyIntegrationOAuthTest`:

1. public-client handshake, hosted sign-in/account-creation return, PKCE exchange, refresh rotation and revocation;
2. provider-bound routes reject cross-provider handshakes and wrong client/provider combinations;
3. clone/move behavior for `connect`, `move`, `reconnect` and a new local installation;
4. context reports `prestashop` and tokens cannot cross installations;
5. managed create/update/unchanged for product, category and promotion;
6. attached mode is read-only and cannot change implicitly;
7. exact `ps:` idempotency verification using the server installation ID;
8. list pagination, click totals and QR response;
9. revocation and installation-state changes preserve links;
10. existing WordPress and Shopify suites remain green.

## 8. Deployment gate

1. Finish or checkpoint the active Shopify provider work.
2. Add the provider catalog metadata and PrestaShop routes without changing existing provider defaults.
3. Run the full Laravel suite plus all three integration-provider suites.
4. Deploy backend support before distributing `zipquantum.zip`; until then, the module's PrestaShop handshake route is intentionally unavailable.
5. Run one controlled production handshake and managed-link synchronization, then remove any test link only through explicit authorised ZipQuantum administration.

All five deployment gates were completed except deliberate remote-link removal: the QA links remain user-owned and were not deleted, as required by the module's no-remote-deletion contract.

## Production operations follow-up

The provider API is deployed and functional. A separate platform audit performed during redirect QA found 248 previously provisioned `*.zq.tn` managed-subdomain virtual hosts still bound to the expired wildcard certificate. The specific PrestaShop QA host was renewed and verified through 2026-12-01, but the remaining existing virtual hosts need a controlled certificate rollout. Future provisioning should bind the current wildcard certificate automatically.

Two expired OVH DNS-01 challenge TXT records also remain because the current OVH token has no DNS DELETE permission. An authorized DNS operator should remove those records; no credential is stored in them.
