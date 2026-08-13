# Shared Plugin Framework – Members Menu Registration Contract

**Status:** Authoritative  
**Applies to:** The shared-framework-managed iLungu™ Club Members Menu and add-on plugins that contribute managed member-facing items
**Audience:** Developers, Maintainers, QA

## 1. Purpose

This contract defines how the shared plugin framework and add-on plugins register managed items into the iLungu Club Members Menu without writing nav-menu items directly.

The Members Menu is a shared-framework-managed menu surface. Add-on plugins must contribute item specifications through the shared-framework registration filter so seeding, repair, visibility, duplicate prevention, and provider lifecycle stay consistent.

## 2. Ownership Model

The iLungu Club Members Menu is managed by the shared plugin framework.

That means:

- The shared framework seeds and repairs the menu items
- The shared framework stores the item metadata used for duplicate prevention and visibility
- The shared framework evaluates managed Members Menu visibility on the front end
- add-ons register item specifications through the shared-framework filter
- add-ons must not call `wp_update_nav_menu_item()` directly for managed Members Menu entries
- add-ons must not filter `wp_nav_menu_objects` to hide or show their own managed Members Menu items

Site-owned custom menu items may still exist in the same menu, but the shared framework must not rewrite or delete those custom items.

## 3. Registration Hook

Add-ons register managed Members Menu items through:

- `tpw_core/member_menu_items`

Current implementation file:

- `includes/tpw-core-settings.php`

Filter signature:

```php
apply_filters( 'tpw_core/member_menu_items', $items, $context )
```

Where:

- `$items` is the current ordered list of managed item specs, including shared-framework defaults
- `$context` is an array of shared registration context values, currently including `allowed_statuses`

Shared-framework defaults are registered through the same path before the filter runs.

## 4. Item Specification

Managed Members Menu items use the following contract fields.

### 4.1 Required fields

- `key` — unique managed item key. Add-ons must namespace this by provider, for example `flexievent-events`
- `provider` — plugin/provider key, for example `flexievent`
- `title` — visible menu label
- `requires_login` — whether the item should be hidden from logged-out users
- `visibility` — shared-framework visibility rules for the menu item

### 4.2 Preferred destination field

- `system_slug` — preferred canonical destination when the plugin owns a real front-end page registered through `TPW_Core_System_Pages`

### 4.3 Optional compatibility fields

- `shortcode_tag` — shortcode tag used to locate the published page when the route is not yet fully registry-driven
- `fallback_slug` — conventional fallback slug used when no linked page exists yet

### 4.4 Placement fields

- `after_key` — place the item after another managed item key
- `before_key` — place the item before another managed item key
- `parent_key` — nest the item under another managed item key

## 5. URL Resolution

The shared framework resolves the final menu URL from the item specification.

Resolution order:

1. explicit shared-framework-managed `url` when present for special internal items such as Logout
2. special shared-framework route handling where required, such as `my-profile`
3. `system_slug` via `TPW_Core_System_Pages::get_permalink()`
4. `shortcode_tag` plus `fallback_slug` via the shared shortcode-page resolver
5. conventional `site_url( '/{fallback_slug}/' )` fallback

Add-ons should prefer `system_slug` for new managed plugin screens.

## 6. Ordering Rules

The shared framework normalizes the final managed item order before repair or seeding.

Rules:

- `parent_key` places a child after its parent in the managed-item sequence
- `after_key` and `before_key` adjust the managed sequence declaratively
- `logout` is always forced to the last managed position

The shared framework uses the normalized managed order during repair and seeding.

## 7. Duplicate Prevention

Managed duplicate prevention is key-based.

Current matching order during repair or seeding:

1. `_tpw_member_menu_default_key`
2. `_tpw_page_slug`
3. normalized URL matching
4. bootstrap title matching for initial migration or first repair compatibility

The item `key` is the canonical unique identifier. Add-ons must keep keys stable once released.

## 8. Stored Metadata

The shared framework stores the following metadata on managed nav menu items:

- `_tpw_member_menu_default_key`
- `_tpw_member_menu_provider`
- `_tpw_page_slug`
- `_tpw_requires_login`
- `_tpw_visibility_json`

This metadata is used for repair, visibility filtering, and provider lifecycle handling.

## 9. Provider Lifecycle

If a previously managed item is no longer present in the current managed-item registry, the shared framework may remove that managed nav-menu item during repair or seeding.

This rule applies only to shared-framework-managed items carrying managed metadata.

The shared framework must not delete or rewrite arbitrary site-owned custom items.

Implications:

- plugin activation: the add-on starts contributing its managed item spec through the filter
- plugin deactivation: the add-on stops contributing the spec, and the next shared-framework repair or seed pass may remove that stale managed item
- plugin uninstall: no extra custom-menu cleanup path is required if the managed item is already removed by the normal shared-framework repair flow

## 10. Visibility Contract

Managed Members Menu items use the current shared-framework menu visibility model:

- `requires_login`
- `visibility`

The shared framework owns the full visibility evaluation path for managed Members Menu items.

Add-ons must only declare metadata through the item specification. Add-ons must not implement separate front-end Members Menu visibility guards for those managed items.

Current `visibility` support is the same flat model already used by shared-framework-managed Members Menu items, including:

- status arrays under `status`
- role or flag booleans such as `is_admin`, `is_committee`, `is_match_manager`, `is_noticeboard_admin`, and `is_gallery_admin`

Add-ons must still enforce direct access in their own shortcode, router, POST, AJAX, or file-serving code. Menu visibility is not a security boundary.

## 11. System Pages Relationship

Plugin-owned Members Menu destinations should be registry-driven through System Pages when they represent a canonical plugin front-end screen.

Recommended pattern:

1. register the plugin page through `TPW_Core_System_Pages::register_page()`
2. ensure the page exists through the normal plugin/shared-framework lifecycle
3. reference that route from the Members Menu item via `system_slug`

`shortcode_tag` and `fallback_slug` remain compatibility paths, not the preferred contract for new plugin-owned screens.

## 12. Example: FlexiEvent

Target managed item:

- `key` => `flexievent-events`
- `provider` => `flexievent`
- `title` => `Events`
- `after_key` => `noticeboard`
- `before_key` => `members`
- `requires_login` => `true`
- `visibility['status']` => current allowed member statuses

Example registration:

```php
add_filter( 'tpw_core/member_menu_items', function( $items, $context ) {
    $items[] = [
        'key'            => 'flexievent-events',
        'provider'       => 'flexievent',
        'title'          => __( 'Events', 'flexievent' ),
        'system_slug'    => 'events',
        'after_key'      => 'noticeboard',
        'before_key'     => 'members',
        'requires_login' => true,
        'visibility'     => [
            'status' => ! empty( $context['allowed_statuses'] ) && is_array( $context['allowed_statuses'] )
                ? array_values( array_map( 'strval', $context['allowed_statuses'] ) )
                : [ 'Active', 'Honorary', 'Life Member' ],
        ],
    ];

    return $items;
}, 10, 2 );
```

## 13. Validation Expectations

When changing or extending this area, validate all of the following:

1. the managed item is seeded once and not duplicated on repeated repair runs
2. the managed item lands in the intended relative position among other managed items
3. logged-out users do not see member-only items
4. allowed members do see the item
5. provider-managed items disappear cleanly from the managed menu after the provider stops registering them
6. custom site-owned menu items are not deleted or rewritten by the managed repair flow
7. no add-on-local `wp_nav_menu_objects` visibility filter is required for a managed Members Menu item