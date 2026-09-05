# Runtime QA report

## Environment

- Date: 2026-09-02
- PrestaShop: 9.0.0 Classic official automation package
- PHP: 8.2
- Database: MariaDB 11.4.13, isolated on `127.0.0.1:3308`
- Web server: PHP development server, isolated on `127.0.0.1:8089`
- Module archive: `dist/zipquantum.zip`

The QA shop and database live under `tmp/prestashop-qa`, which is excluded from the Marketplace archive. No system service was installed. The shared backend was modified only after the Shopify provider work had completed, then deployed with a rollback backup.

## Verified behavior

- The Marketplace ZIP extracts with the expected `zipquantum/` root directory.
- `prestashop:module install zipquantum` completes successfully.
- PrestaShop registers module version 1.0.0 as active.
- Installation creates `ps_zqps_association` and `ps_zqps_queue`.
- The module is registered on ten hooks.
- The configuration page renders without a ZipQuantum JavaScript or PHP error.
- Account connection, routing, all three object types, managed/attached workflows, bulk queue controls, secured cron, analytics, QR and the privacy/no-remote-deletion notice are visible.
- Saving module settings succeeds and displays `Settings saved.`
- No storefront tracker or ZipQuantum storefront JavaScript is added.

The only browser-console and PHP deprecation messages observed came from PrestaShop's bundled Marketplace/shipping modules. They did not originate from ZipQuantum.

## Production OAuth verification

- The PrestaShop provider backend was deployed to `https://a.zq.tn` on 2026-09-02.
- A dedicated ZipQuantum account completed the real module-to-production Google sign-in, hosted consent, PKCE exchange and module connection flow.
- Two browser-runtime issues found during this verification were fixed before packaging: the back-office script now waits for `DOMContentLoaded`, and the OAuth popup opens synchronously during the administrator click before the network handshake completes.
- PrestaShop 9 signed administrator links are used for AJAX dispatch; the controller also requires an authenticated employee.

## End-to-end Smart Link verification

- Managed mode created a production Smart Link for product `#1`; the HTTPS redirect reached the intended local QA product page.
- A controlled redirect incremented aggregate analytics from zero to one and `Refresh click totals` synchronized that total back into the module.
- QR retrieval, sanitization and download succeeded. Trusted raster data-URI logos and fragment-only SVG clip references are accepted; scripts, event handlers and external references remain rejected.
- Attached mode associated category `#2` read-only with the existing product Smart Link and did not modify the remote link.
- Bulk creation queued all 19 remaining products and processed both batches: the final queue state was 0 pending and 20 complete. One retained historical failure predates the local-preview fix and is deliberately preserved in the isolated QA database.
- Localhost, private, reserved and non-HTTP preview image URLs are now omitted from production payloads instead of causing remote validation failures.
- Refreshing remote analytics merges aggregate fields into the cached Smart Link and no longer erases an already-fetched QR payload.
- No visitor event stream, fingerprint, advertising identifier or storefront tracking script was introduced.
- Object deletion, disconnection and uninstall remain local-only operations; no remote Smart Link was deleted during QA.

## TLS verification and platform follow-up

- The expired wildcard certificate discovered during redirect QA was renewed with Let's Encrypt on 2026-09-02.
- The QA Smart Link host `psqa0902zq.zq.tn` now presents a certificate valid until 2026-12-01 and passes verification without bypassing TLS checks.
- A read-only cPanel audit found 248 previously provisioned managed-subdomain virtual hosts still bound to the old expired certificate. Updating those existing hosts is a separate production-platform rollout and is not part of the module package.
- Two temporary `_acme-challenge.zq.tn` TXT records could not be removed because the current OVH token lacks DNS DELETE permission. They are expired validation tokens, not credentials; an authorized DNS cleanup is still required.

## Marketplace screenshots

Authentic runtime screenshots were captured from the installed module:

- `marketplace-assets/screenshots/01-zipquantum-configuration.png` — 1665 x 1824
- `marketplace-assets/screenshots/02-account-routing.png` — 1425 x 1069
- `marketplace-assets/screenshots/03-workflow-queue-analytics.png` — 1425 x 1069

All three were uploaded to Marketplace draft `6a984c20911daf0ce56a51f6`.

## Release gate result

The provider API, live OAuth, managed and attached Smart Links, QR retrieval, aggregate click synchronization and bulk queue were exercised end to end. The final archive (`7D4A1EB7536D1E7CE916E1080AE240A09D72D42924D614D49BFD57D6C55303A4`) passed official Validator report `3760772` with zero findings in requirements, structure, errors, compatibility, optimizations, translations, licenses and security. The remaining 29 standards notices are the documented non-blocking file-header/guard conflicts. The module is ready for Marketplace submission once the seller explicitly accepts the Marketplace legal terms at submission time.
