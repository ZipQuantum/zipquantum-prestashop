# Changelog

## 1.0.0 - 2026-09-02

- Initial Marketplace-ready MVP.
- ZipQuantum account connection and account creation through OAuth PKCE handoff.
- Managed and attached Smart Links for products, categories and promotions/coupons.
- QR display/download, simple click totals, durable bulk queue and secured cron processing.
- Privacy-safe connector: no storefront tracking, fingerprinting or advertising identifiers.
- Local deletion semantics: deleting or uninstalling never deletes a remote Smart Link.
- Strict validation of remote Smart Link URLs and QR data before back-office display.
- Manual retry and re-enqueue actions reset their retry budget.
- Hardened coupon destination validation against encoded redirects and header injection.
- Removed direct global `Context` access in favor of constructor-injected shop, language and link services for PrestaShop 9 compatibility.
