# Notices (Noticeboard)

## Overview
The Notices module powers a simple noticeboard, allowing admins to publish time‑bound notices displayed on front‑end screens.

## Canonical Implementation
- Canonical shortcode loader: `modules/notices/shortcodes/noticeboard-list.php`
- Canonical modal template: `modules/notices/templates/form.php`
- Canonical front-end assets: `modules/notices/assets/css/noticeboard.css`, `modules/notices/assets/js/noticeboard.js`
- Canonical shortcode: `[tpw_noticeboard_list]`

Legacy compatibility copies currently remain at `assets/css/noticeboard.css` and `assets/js/noticeboard.js`, but those root assets are not the canonical implementation and are not used by the normal Core loader path for `[tpw_noticeboard_list]`.

## Key Screens / Shortcodes
- Shortcode: `[tpw_noticeboard_list]`
- Legacy shortcode note: older references to `[tpw-noticeboard]` may still exist in site content or historical notes, but current Core loading for the front-end noticeboard list is owned by `modules/notices/shortcodes/noticeboard-list.php`.

## Hooks
- (Theme/plugin specific) Filter notice queries or output using your theme/plugin hooks if you wrap the shortcode.

## Extending
- Use your own template part or shortcode wrapper to control markup and placement.
- Respect the module Noticeboard enqueue path when reusing the front-end list UI.
- Do not treat the root `assets/noticeboard` copies as the canonical source for new integrations.

## References
- Developer Guide → ../developer-guide.md
- Active assets: `modules/notices/assets/css/noticeboard.css`, `modules/notices/assets/js/noticeboard.js`
- Active template: `modules/notices/templates/form.php`
- Active shortcode loader: `modules/notices/shortcodes/noticeboard-list.php`

See also: Core Hooks Index → ../developer-guide.md#core-hooks-index
