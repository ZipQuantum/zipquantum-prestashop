# Marketplace listing draft

## Product name

Smart Links & QR Codes: Privacy-Safe Commerce URLs

## Short description

Create branded Smart Links and downloadable QR codes for products, categories and promotions. Sync safely in bulk, attach existing links, and view click totals without storefront tracking or fingerprinting.

## Merchant benefits

- Turn catalogue and campaign URLs into memorable, measurable links without editing themes.
- Save repetitive work with automatic updates, bulk enqueue, retries, and a secured cron runner.
- Keep full ownership of remote links: deleting store content or uninstalling never removes them.
- Connect an existing account or create a Free account in the guided ZipQuantum window.

## Customer benefits

- Scan a QR code or open a concise branded link to reach the intended product, category, or promotion.
- Coupon links can apply an active code and continue to the merchant-selected local checkout path.
- No additional storefront tracker, fingerprint, or advertising identifier is introduced by the connector.

## Distinct features

1. Three native commerce object types: managed Smart Links for products, categories, and promotions/coupons with localized previews.
2. Managed or attached ownership: synchronize four explicit fields or attach an existing Smart Link in read-only mode.
3. Durable queue: deduplicated bulk work, five retry windows, `Retry-After`, explicit blocked/failed states, manual processing, and signed cron.
4. QR and simple analytics: download the server-generated QR and refresh total clicks for every local association.
5. Clone and deletion safety: quarantine moved installations and never delete a remote Smart Link automatically.

## External service disclosure

The module requires the ZipQuantum service to authenticate the merchant, create or attach Smart Links, return QR codes, and retrieve click totals and account capabilities. It may send the shop origin/path plus the selected object's public URL, title, description, image URL, type, and local identifier. It does not send PrestaShop customer data, storefront visitor events, fingerprints, advertising identifiers, or mobile SDK data.

## Required listing assets before submission

- Product icon: 256 x 256 PNG.
- Three or more final screenshots at 1000 x 1000 or larger for every listed language.
- English user guide: `docs/readme_en.pdf` inside the module archive.
- Live demo is recommended but deliberately not represented as complete in version control.

## Suggested keywords

smart links, QR codes, product links, coupon links, link analytics, bulk synchronization

## Marketplace draft status

- Seller account: JINFO IT SRL (Jomaa Ben Sassi)
- Marketplace draft ID: `6a984c20911daf0ce56a51f6`
- Status: Draft, saved on 2026-09-02
- Completion shown by Marketplace: 92%
- Category: Advertising & Marketing (`Publicité & Marketing` in the seller portal)
- Availability: all countries
- Merchant benefit: traffic analytics
- Multistore support: full
- Uploaded assets: product icon, English PDF guide, and three authentic PrestaShop runtime screenshots

## Confirmed pricing

The merchant-confirmed one-time module price is EUR 49.99. PrestaShop adds its mandatory Business Care offer at EUR 20.00 per year, so the seller portal currently displays a first-year total of EUR 69.99.

## Remaining before submission

- Implement and exercise the documented PrestaShop provider contract in the shared backend after the Shopify multi-provider work stabilizes.
- Obtain and add the Marketplace module key when PrestaShop exposes it for this product.
- Rebuild and revalidate the final archive after the module key is added.
- Review the legal acknowledgements and submit the listing for human review only after all preceding items are complete.
