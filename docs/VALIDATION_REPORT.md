# Official PrestaShop Validator report

Last validation: 2026-09-02

Report ID: `3760772`

Report URL: `https://validator.prestashop.com/module/3760772/validate`

Validated archive SHA-256: `7D4A1EB7536D1E7CE916E1080AE240A09D72D42924D614D49BFD57D6C55303A4`

## Green sections

- Requirements
- Structure
- Errors: zero
- Compatibility
- Optimizations
- Translations
- Licenses
- Security

## Non-blocking standards notices

The validator reports 29 style notices. Twenty-seven are the validator's mutually conflicting file-header rules: its license checker requires the file comment immediately after the PHP opening tag while its formatter requests a blank line there and no blank line after the file comment. The package keeps the arrangement accepted by the license, structure and security checks.

The two remaining notices on `sql/install.php` and `sql/uninstall.php` request fully qualified strict types, but adding the suggested leading namespace separator to `defined()` makes the validator's mandatory context-guard detector reject those files. The exact mandatory guard is therefore preserved.

These notices are warnings, not blocking errors. The automated report explicitly shows zero errors and green compatibility, structure, licensing and security.
