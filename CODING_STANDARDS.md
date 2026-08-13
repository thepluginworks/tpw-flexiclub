# Coding Standards – TPW WordPress Plugins

All PHP code in this repository **must pass PHPCS using WordPress-Core**.

## Tooling
- PHP_CodeSniffer (PHPCS)
- WordPress Coding Standards (WPCS)
- Standard: `WordPress-Core`
- Ruleset: `phpcs.xml` in project root

## Required Conventions

### General
- Tabs for indentation (not spaces)
- Yoda conditions are required
- Strict comparisons (`===`, `!==`)
- No short ternaries (`?:`)

### Input Handling
- All `$_GET`, `$_POST`, `$_REQUEST` must:
  1. Be checked with `isset()`
  2. Be `wp_unslash()` **before** sanitization
  3. Be sanitized using the correct sanitizer
     - `sanitize_text_field`
     - `sanitize_key`
     - `absint`
     - `sanitize_email`
     - etc.

### Nonces
- All form submissions and actions **must** use nonces
- Nonces must be verified before processing input

### Output Escaping
- All output must be escaped at render time
- Use context-appropriate escaping:
  - `esc_html()`
  - `esc_attr()`
  - `esc_url()`
  - `wp_kses_post()`
- Translated output must use escaped variants:
  - `esc_html__()`
  - `esc_attr__()`

### Database
- All queries must use `$wpdb->prepare()`
- No interpolated variables in SQL
- Table names must come from trusted internal variables only
- Dynamic `ORDER BY` must use whitelisting

### Dates & Time
- Use `gmdate()` instead of `date()`
- Store timestamps in UTC

### Redirects
- Use `wp_safe_redirect()`
- Always call `exit;` after redirects

## AI Usage Rules
When using ChatGPT or GitHub Copilot:
- Generated code **must comply with this document**
- Assume PHPCS will be run before merge
- Do not change logic when fixing PHPCS issues unless required