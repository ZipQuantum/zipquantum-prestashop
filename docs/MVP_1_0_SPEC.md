# ZipQuantum for PrestaShop - Frozen MVP 1.0

Status: frozen on 2026-09-02

## Product promise

ZipQuantum lets a PrestaShop merchant connect a ZipQuantum account and create or attach privacy-safe Smart Links for three native commerce objects: products, categories, and promotions/coupons. Each remote Smart Link can expose its short URL, QR code, current status, and total click count in the back office.

## Supported environment

- PrestaShop 8.1.0 through 9.99.99, explicitly including PrestaShop 9.1.
- PHP 8.1 through 8.5 with cURL, JSON, and OpenSSL.
- One independent ZipQuantum installation identity and token set per PrestaShop shop.
- Classic and Hummingbird storefronts because the module does not override theme templates.

## Included journeys

### Account connection

1. An authorised back-office employee starts the connection.
2. The module creates a public-client PKCE handshake with a one-time state, nonce, verifier, and polling secret.
3. ZipQuantum opens in a separate window. The merchant can sign in or create a Free account there.
4. The module polls without exposing the verifier, exchanges the one-time code, encrypts the rotated tokens locally, and retrieves account, plan, capability, domain, and usage context.
5. Disconnect revokes the OAuth token when possible and always removes local credentials. It does not remove links.

### Smart Link management

- `managed`: PrestaShop owns destination URL, preview title, preview description, and preview image. Updates are queued and sent idempotently.
- `attached`: the merchant enters an existing ZipQuantum Smart Link ID. PrestaShop records a read-only association and never writes link fields.
- A management mode cannot change implicitly. A backend conflict remains explicit.

### Commerce objects

- Product: canonical storefront URL, localized name, short description, cover image, stable reference.
- Category: canonical storefront URL, localized name, description, category image when present, stable reference.
- Promotion/coupon: a public, shareable module URL applies an active code to the current cart and redirects to a validated local path. Automatic cart rules link directly to that path.

### QR and simple analytics

- The QR is returned by ZipQuantum as a safe data URI and can be downloaded from the association list.
- `clicks` is the only analytics metric displayed in 1.0.
- Refresh reads the installation-scoped association collection. The module sends no storefront event.

### Bulk and queue

- Up to 500 active objects of one type can be added per bulk action.
- A queue row is deduplicated by shop, operation, object type, object ID, and canonical payload hash.
- Queue states: pending, processing, retry, blocked, quarantined, failed, complete, cancelled.
- Retry schedule: 1 minute, 5 minutes, 30 minutes, 2 hours, 12 hours plus jitter.
- HTTP 429 respects `Retry-After`; 422 is explicit failure; 401 blocks for reconnect; installation identity mismatch blocks the shop queue.
- Processing can be triggered from the back office or a unique, encrypted, per-shop cron token.

## Privacy and deletion invariants

- No storefront JavaScript, pixel, cookie, fingerprint, advertising identifier, IP enrichment, visitor event, or customer record is collected by this module.
- The module sends only the connected shop URL and selected object metadata required to create the merchant-requested link.
- Deleting a PrestaShop object deletes the local association and cancels local pending work only.
- Disconnecting, resetting identity, uninstalling, or deleting an object never calls a remote link deletion endpoint.
- A cloned or moved shop is quarantined until the merchant makes an explicit identity decision.

## Deliberately excluded from 1.0

- Orders, customers, carts, manufacturers, CMS pages, suppliers, and combinations as linkable objects.
- Visitor-level analytics, attribution, conversion tracking, geographic or device analytics.
- Custom QR styling or local QR generation.
- Automatic remote deletion or remote lifecycle cleanup.
- Webhooks, campaigns, UTM builders, scheduled campaign rules, and product-page widgets.
- Changing a managed association into attached mode or the reverse without an explicit future detach workflow.
- Editing ZipQuantum account billing or remote domains inside PrestaShop.

Any expansion requires a versioned amendment and must preserve the privacy and no-remote-deletion invariants.

