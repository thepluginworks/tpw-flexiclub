# Shared Plugin Framework Button Contract

Status: Authoritative  
Applies to: the shared plugin framework and all TPW consumer plugins
Audience: Developers, maintainers, QA

---

## 1. Purpose

This contract defines the canonical TPW button system, including:

- canonical class names
- semantic usage rules
- wrapper and enqueue requirements
- element support rules
- token ownership and styling boundaries
- backwards compatibility requirements

---

## 2. Ownership and Scope

- The shared plugin framework owns the shared button contract.
- Consumer plugins must use shared-framework button classes rather than inventing alternate shared button classes.
- This document is authoritative for button class naming and expected behavior.
- Styling source of truth is [assets/css/tpw-buttons.css](../../../assets/css/tpw-buttons.css).

---

## 3. Required Dependencies

### 3.1 Required stylesheet handle

- Handle: `tpw-buttons`
- File: [assets/css/tpw-buttons.css](../../../assets/css/tpw-buttons.css)

### 3.2 Wrapper expectations

Follow wrapper rules in [docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md](tpw-core-ui-wrapper-enqueue-contract.md):

- Admin/admin-like surfaces: `.tpw-admin-ui`
- Public/member surfaces: `.tpw-frontend-ui`
- Buttons can render without wrapper, but contract-compliant screens must use canonical wrappers.

---

## 4. Canonical Class Inventory

### 4.1 Base class (mandatory)

- `tpw-btn`
  - Base class required on all TPW-styled buttons/links/inputs.

### 4.2 Variant classes

- `tpw-btn-primary`
  - Primary call-to-action.
- `tpw-btn-secondary`
  - Secondary action.
- `tpw-btn-danger`
  - Destructive action.
- `tpw-btn-warning`
  - Cautionary action requiring user attention.
- `tpw-btn-light`
  - Light/neutral tertiary action.
- `tpw-btn-dark`
  - Dark/emphasis utility action.
- `tpw-btn-edit`
  - Edit-focused action variant (action-edit token driven).
- `tpw-btn-admin`
  - Admin/settings emphasis alias (dark style).
- `tpw-btn-gallery`
  - Gallery-context compact variant.

### 4.3 Modifier classes

- `tpw-btn-outline`
  - Outlined variant modifier.
  - Can be combined with `tpw-btn-secondary` for secondary outline behavior.
- `tpw-btn-sm`
  - Small button size.
- `small` (used as `.tpw-btn.small`)
  - Legacy/alias small size modifier.
- `tpw-btn-lg`
  - Large button size.
- `tpw-btn-block`
  - Full-width button.
- `tpw-btn-text-left`
  - Left-align content for supported secondary button pattern.

---

## 5. Supported Element Types

The button system supports:

- `<a class="tpw-btn ...">`
- `<button class="tpw-btn ...">`
- `<input type="submit" class="tpw-btn ...">`
- `<input type="button" class="tpw-btn ...">`
- `<input type="reset" class="tpw-btn ...">`

All must include `tpw-btn` plus at least one semantic variant unless intentionally inheriting base-only style.

---

## 6. Canonical Usage Rules

- Always include base class `tpw-btn`.
- Use one semantic variant for intent (`primary`, `secondary`, `danger`, `warning`, `light`, `dark`, `edit`, `admin`).
- Use modifiers additively (`outline`, size, block), not as replacements for semantic variants.
- Prefer explicit semantic intent over visual-only choices.
- For links styled as buttons, include `role="button"` when appropriate for accessibility parity.

---

## 7. Tokens Consumed by Button System

Primary button token set:

- `--tpw-btn-primary`
- `--tpw-btn-secondary`
- `--tpw-btn-danger`
- `--tpw-btn-warning`
- `--tpw-btn-light`
- `--tpw-btn-dark`
- `--tpw-btn-text-light`
- `--tpw-btn-text-dark`
- `--tpw-btn-text-warning`
- `--tpw-action-edit`
- `--tpw-btn-radius`
- `--tpw-btn-padding`
- `--tpw-btn-font-size`
- `--tpw-btn-height`
- `--tpw-btn-padding-lg`
- `--tpw-btn-font-size-lg`
- `--tpw-btn-height-lg`
- `--tpw-btn-font-family`
- `--tpw-btn-font-weight`

Token source and branding guidance: [docs/help/tpw-branding.md](../../help/tpw-branding.md)

---

## 8. Compatibility and Migration Rules

- Existing class names in this contract are stable shared API.
- Do not rename or remove classes without:
  - documented migration path
  - additive compatibility period
  - coordinated rollout guidance
- Prefer additive new variants over repurposing existing variants.
- Consumer plugins must not override shared class semantics globally.

---

## 9. Examples

### 9.1 Primary submit action

```html
<button type="submit" class="tpw-btn tpw-btn-primary">Save changes</button>
```

### 9.2 Secondary outline action

```html
<a class="tpw-btn tpw-btn-secondary tpw-btn-outline" href="#" role="button">View details</a>
```

### 9.3 Destructive action

```html
<button type="button" class="tpw-btn tpw-btn-danger">Delete</button>
```

### 9.4 Full-width warning action

```html
<button type="button" class="tpw-btn tpw-btn-warning tpw-btn-block">Retry verification</button>
```

---

## 10. Contract References

- Wrapper/enqueue contract: [docs/architecture/ui/tpw-core-ui-wrapper-enqueue-contract.md](tpw-core-ui-wrapper-enqueue-contract.md)
- Branding/token guidance: [docs/help/tpw-branding.md](../../help/tpw-branding.md)
- UI guidance: [docs/help/ui-spec.md](../../help/ui-spec.md)
- Styling implementation source: [assets/css/tpw-buttons.css](../../../assets/css/tpw-buttons.css)
