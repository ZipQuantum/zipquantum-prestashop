<p align="center">
  <img src="https://cdn.simpleicons.org/prestashop/FF007A" alt="PrestaShop logo" width="64" height="64"><br>
  <strong>PrestaShop</strong>
</p>

<h1 align="center">ZipQuantum – Smart Links & QR Codes for PrestaShop</h1>

<p align="center">
  Official ZipQuantum integration for PrestaShop stores.
</p>

> **Developer distribution:** install this package directly from Module Manager while Marketplace merchant-account onboarding is pending.

<p align="center">
  <a href="https://github.com/ZipQuantum/zipquantum-prestashop/releases/latest/download/zipquantum.zip"><strong>Download the latest installable ZIP</strong></a>
  ·
  <a href="https://zq.tn/developers/integrations/">Installation guide</a>
</p>

Marketplace-oriented PrestaShop module developed as a separate repository from the ZipQuantum backend.

## Installation

1. Download `zipquantum.zip` from the latest GitHub Release.
2. In the PrestaShop back office, open **Modules → Module Manager → Install a module**.
3. Upload the ZIP, configure the module, and connect your ZipQuantum account.

## MVP 1.0

- Connect an existing ZipQuantum account or create one through the hosted OAuth flow.
- Create managed Smart Links for products, categories and promotions/coupons.
- Attach an existing Smart Link in read-only mode.
- Display and download the QR code returned by ZipQuantum.
- Refresh simple click totals.
- Enqueue individual or bulk synchronization with retries, explicit failures and a secured cron endpoint.
- Never inject storefront analytics, fingerprint visitors or send advertising identifiers.
- Never delete a remote Smart Link when a PrestaShop object, connection or module is removed.

The frozen scope, compliance audit and backend contract are in [`docs/`](docs/).

## Compatibility

- PrestaShop 8.1 through 9.x, including the current 9.1 release.
- PHP 8.1 through 8.5.
- PHP extensions: cURL, JSON and OpenSSL.
- One independent ZipQuantum connection per shop in a multistore installation.

## Development

```powershell
php ..\.cache\composer.phar install
php ..\.cache\composer.phar check
php bin\build.php
```

The Marketplace archive is generated as `dist/zipquantum.zip`. It contains one top-level `zipquantum/` directory and no development dependencies or tests.

## Backend boundary

This repository does not modify `zipquantum-app`. The exact provider-neutral changes needed there are documented in [`docs/BACKEND_CHANGES_REQUIRED.md`](docs/BACKEND_CHANGES_REQUIRED.md).

## License

AFL-3.0 — see [LICENSE.md](LICENSE.md).

<p align="center">
  <a href="https://zq.tn/">Product</a> · <a href="https://zq.tn/docs/">Documentation</a> · <a href="https://zq.tn/developers/ai-agents/">AI agents</a>
</p>
