# ZipQuantum PrestaShop integration API contract v1

Status: consumer contract frozen for module 1.0 on 2026-09-02.

Base URI: `https://a.zq.tn`

OAuth resource: `https://a.zq.tn/api`

Public client ID: `zipquantum-prestashop`

Provider discriminator: `prestashop`

## Security model

- OAuth 2.0 public client with PKCE S256; no client secret is shipped.
- Central ZipQuantum callback; the shop never exposes an OAuth callback endpoint.
- The code verifier remains encrypted inside PrestaShop and is never sent to handshake or polling endpoints.
- Handshake state, installation nonce, and polling secret are independent random values.
- Access and refresh tokens rotate and are encrypted at rest with AES-256-GCM using PrestaShop installation key material.
- Every link sync requires an installation-bound idempotency key.

## OAuth and installation endpoints

### `POST /api/v1/integrations/prestashop/handshakes`

Request:

```json
{
  "client_id": "zipquantum-prestashop",
  "installation_uuid": "6f0ce8df-6f98-42c7-a8e2-b2f507942eed",
  "installation_nonce": "base64url-43-or-more-characters",
  "home_url": "https://shop.example/",
  "state": "base64url-32-or-more-characters",
  "code_challenge": "base64url-sha256",
  "code_challenge_method": "S256",
  "intent": "connect"
}
```

`intent` is one of `connect`, `move`, or `reconnect`. `redirect_uri` and `code_verifier` are prohibited.

Created response, HTTP 201:

```json
{
  "handshake_id": "uuid",
  "authorization_url": "https://a.zq.tn/integrations/prestashop/authorize/uuid",
  "polling_secret": "base64url-secret",
  "expires_in": 600,
  "interval": 3
}
```

Identity mismatch response, HTTP 409:

```json
{
  "message": "This shop appears to have been moved or cloned.",
  "code": "installation_identity_mismatch",
  "actions": ["move", "new_installation", "reconnect"]
}
```

### `POST /api/v1/integrations/prestashop/handshakes/{handshake}/poll`

Request contains only `polling_secret`. Pending returns HTTP 202 with `Retry-After`. Authorized returns `status`, the original `state`, and a one-time `authorization_code`. Expired/consumed is 410; denied is 403; bad secret is 401.

### `POST /api/v1/integrations/oauth/token`

Existing token endpoint extended to accept the PrestaShop client. Grants are `authorization_code` with `code_verifier`, and `refresh_token`. It returns bearer access token, rotating refresh token, expiry, scope, token type, and `installation_id`.

### `POST /api/v1/integrations/oauth/revoke`

Existing token revocation endpoint. Revocation only disconnects credentials. It does not delete an installation association or Smart Link.

## Authenticated context

### `GET /api/v1/integration/context`

Existing response shape is reused:

- account: id, name;
- installation: id, provider, site origin, port, path, status;
- plan and usage;
- capabilities: integrations access, basic analytics, analytics dashboard, custom aliases, campaign parameters;
- verified domains.

The installation provider must be `prestashop`.

## Link synchronization

### `POST /api/v1/integration-links/sync`

Header:

```text
Idempotency-Key: ps:{server_installation_id}:{object_type}:{object_id}:{canonical_payload_sha256}
```

Common fields:

```json
{
  "provider": "prestashop",
  "object_type": "product",
  "object_id": "42",
  "management_mode": "managed"
}
```

`object_type` is one of `product`, `category`, or `promotion` for module 1.0.

Managed request adds:

```json
{
  "managed_fields": [
    "destination_url",
    "preview_title",
    "preview_description",
    "preview_image_url"
  ],
  "source_url": "https://shop.example/product/42",
  "link": {
    "link": "https://shop.example/product/42",
    "reference": "example-product-42",
    "subdomain": "merchant",
    "preview_title": "Example product",
    "preview_description": "Short description",
    "preview_image_url": "https://shop.example/img/p/42-large_default.jpg"
  }
}
```

`custom_domain` replaces `subdomain` when configured. Attached request contains only the common fields plus integer `link_id`. Attached mode never updates link fields.

Response shape is the existing integration response: `status`, `managed`, `smart_link`, and `association`. `smart_link` must include `id`, `short_link`, `destination_url`, `clicks`, `status`, and a `qr` data URI for sync responses.

`short_link` must be an absolute HTTPS URL without embedded credentials. `qr` must be a base64 PNG, JPEG, or inert SVG data URI; SVG responses must not contain scripts, event handlers, external references, entities, or foreign objects. The module validates these display values again before local persistence.

Idempotent replays return `unchanged`. Explicit errors include 401 reconnect required, 409 identity or management conflict, 422 validation/limit errors, 429 with `Retry-After`, and retriable 5xx.

`server_installation_id` is the `installation_id` returned by the OAuth token endpoint. It is distinct from the local PrestaShop `installation_uuid` supplied during handshake.

## Association list and simple analytics

### `GET /api/v1/integration-links?per_page=100&page=1`

Existing installation-scoped pagination is reused. Each row includes `object_type`, `object_id`, `management_mode`, `managed_fields`, `source_url`, `last_synced_at`, and `smart_link`. `smart_link.clicks` is the only metric required by PrestaShop 1.0.

The endpoint must never expose another user or installation. Filters remain optional. No visitor records are accepted or returned by the PrestaShop integration contract.

## Deletion contract

There is intentionally no PrestaShop remote-delete endpoint in v1. Local object deletion, disconnect, new-installation quarantine, and uninstall make no remote link deletion request. Backend installation/token revocation must preserve links and link associations unless a user explicitly deletes them in ZipQuantum itself.
