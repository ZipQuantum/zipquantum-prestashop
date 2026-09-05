# PrestaShop and Marketplace audit

Last verified: 2026-09-02

## Current platform baseline

PrestaShop 9.1 is the current stable line. Its official release requirements are PHP 8.1 with PHP versions through 8.5 supported. The module therefore declares PrestaShop 8.1.0 through 9.99.99 and requires PHP 8.1 or newer.

Official sources:

- PrestaShop 9.1 release: https://build.prestashop-project.org/news/2026/prestashop-9-1-0-available/
- PrestaShop 9 module tutorial: https://devdocs.prestashop-project.org/9/modules/creation/tutorial/
- Module file structure: https://devdocs.prestashop-project.org/9/modules/creation/module-file-structure/
- Admin controllers: https://devdocs.prestashop-project.org/9/modules/concepts/controllers/admin-controllers/

## Mandatory Marketplace rules applied

- Stable technical name: `zipquantum` in the folder, archive, main file, and main class.
- One module per archive with a single top-level `zipquantum/` directory.
- Explicit compatibility range; `_PS_VERSION_` is not used as the upper bound.
- PrestaShop context guard in executable PHP source files.
- Root `.htaccess` and defensive `index.php` files in all non-vendor folders.
- No core table changes, module overrides, edits to other modules, remote code downloads, obfuscation, or external JavaScript.
- All code, comments, default UI copy, and packaged documentation are in English.
- HTML lives in Smarty templates and every dynamic template value is escaped.
- Configuration and table names are prefixed with `ZQPS_` or `zqps_`.
- AJAX uses a back-office controller protected by the employee session and PrestaShop admin token.
- Cron is a module front controller protected by a random per-shop secret.
- No `serialize`, `unserialize`, `eval`, dynamic include path, debug statement, or commented-out code.
- A self-contained PDF guide named `docs/readme_en.pdf` is included.
- Packaged documentation contains no contact details, website link, or external support link.
- External API hosts are declared in `header_csp.txt`; no external UI asset is loaded.
- The module is tested for zero PHP errors in debug-mode integration environments before submission.

Official sources:

- Technical standards: https://docs.cloud.prestashop.com/2-technical-development-standards/
- Validation checklist: https://docs.cloud.prestashop.com/9-prestashop-integration-framework/10-validation-checklist/
- Technical validation guide: https://helpcenter-partners.prestashop.com/hc/en-us/articles/28089957680018-Guide-technical-validation
- Validator documentation: https://validator.prestashop.com/documentation
- Submission process: https://docs.cloud.prestashop.com/5-submission-and-validation-process/

## Submission consequences

All new products must support the latest PrestaShop version. Since February 1, 2026, updates to existing products are also rejected when the product is not compatible with the latest major version. Automated review checks structure, security, PHP compatibility, overrides, and debug-mode errors before expert review.

The Marketplace archive must be named `zipquantum.zip`, without a version suffix. The product's unique `module_key` is intentionally left blank in source and must be injected in the constructor only after the seller product page allocates it.

## Marketplace content deliverables

- Proposed name: `Smart Links & QR Codes: Privacy-Safe Commerce URLs` (59 characters).
- English short description under 250 characters.
- At least three distinct features and explicit merchant/customer benefits.
- 256 x 256 product icon.
- The packaged `logo.png` is independently 32 x 32 pixels for the back-office module list.
- At least three screenshots of 1000 x 1000 pixels per listed language before submission.
- English PDF guide named `readme_en.pdf` with compatibility, PHP requirements, installation, connection, configuration, features, troubleshooting, FAQ, and support route.

Official content rules: https://docs.cloud.prestashop.com/3-content-and-marketing-standards/

## Pre-submission actions that still require external systems

- Obtain the Marketplace `module_key` and add it to the constructor.
- Run the uploaded archive through the live PrestaShop Validator and resolve the generated report.
- Execute install, configure, queue, coupon, clone/move, uninstall, and upgrade tests on real PrestaShop 8.1, 8.2, 9.0, and 9.1 stores with debug mode enabled.
- Capture at least three final 1000 x 1000 screenshots from those real stores.
- Preserve the green automated validator result recorded in `VALIDATION_REPORT.md` when adding the Marketplace `module_key`.
- Create the product page, upload translated listing copy if additional languages are selected, and pay the applicable annual validation fee.
- Obtain official PrestaShop partner eligibility before selecting Free download. Current Marketplace policy reserves free modules for official partners; otherwise a paid Marketplace tier is required.
