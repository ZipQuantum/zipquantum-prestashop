# Test and release matrix

## Automated on every change

- PHP syntax for every PHP file.
- PHPUnit pure-domain tests on PHP 8.1, 8.2, 8.3, 8.4, and 8.5.
- PHP_CodeSniffer and PHPStan on provider-neutral helpers.
- Marketplace contract scan: fixed technical name, context guards, no unsafe serialization/eval, no remote DELETE call, protected folders, English PDF, and one-module ZIP structure.
- Deterministic package build and archive inspection.
- Remote URL and QR sanitization, including executable SVG rejection.
- Encoded open-redirect and response-splitting payload rejection for coupon destinations.
- Retry-budget reset after manual retry and idempotent re-enqueue.

## Required real-store release gate

| PrestaShop | PHP | Storefront | Required result |
| --- | --- | --- | --- |
| 8.1 latest | 8.1 | Classic | Install, configure, all object types, queue, uninstall |
| 8.2 latest | 8.2 | Classic | Install/upgrade and debug-mode zero warnings |
| 9.0 latest | 8.3 | Hummingbird | Back-office AJAX, coupon route, cron, QR |
| 9.1 latest | 8.4 and 8.5 | Hummingbird | Full acceptance suite and Marketplace screenshots |

Run every store with debug mode enabled. Also test multistore with two shops connected to different ZipQuantum accounts, network failure, 401, 409, 422, 429 with `Retry-After`, 5xx, cloned database, and irregular cron.

## Acceptance cases

- Existing account connection and new Free account creation.
- Managed create, update, unchanged replay, and mode conflict.
- Attached link reads but never updates the remote link.
- Product, category, code coupon, and automatic promotion payloads.
- QR download and click refresh.
- Bulk 500, concurrent queue workers, retry, resume, quarantine, and explicit failure.
- Object deletion, disconnect, and uninstall leave the remote link intact.
- No storefront network request or script from this module on ordinary customer browsing.
