# Payments

## Overview
Payments provides lightweight helpers and webhooks to log completed transactions (e.g., SumUp, Square) and expose settings like currency for dependent plugins.

## Current Payment Methods Status
- Visible in the shared Payment Methods settings UI: Bank Transfer (BACS), Cheque, Cash, Card on the day, and Square compatibility settings.
- Hidden from the shared Payment Methods settings UI for now: SumUp and WooCommerce.
- SumUp and WooCommerce remain development or integration surfaces in the shared plugin framework and must not be treated as current club-facing configuration options in the FE or BE Payment Methods list.
- Square remains visible because the shared framework still preserves its compatibility-era configuration state, even when the TPW Square Gateway add-on is not active.

## Key Screens / Shortcodes
- Settings → iLungu™ Club → Payment Methods (shared gateway enablement and configuration)
- Webhook endpoint(s): modules/payments/webhook.php (for gateways to call)

## Hooks
- tpw_payment_completed (action) — Fires when a gateway webhook marks a payment completed. Args: gateway, reference, email, amount, payload.

## Extending
- Subscribe to tpw_payment_completed to update your domain models (orders, entries). Validate payloads and idempotency yourself.
- Use get_option('flexievent_settings') for currency_symbol and currency_code where needed.

## References
- Developer Guide → ../developer-guide.md
- Logger: modules/payments/class-tpw-payment-logger.php
- Settings UI: modules/payments/class-tpw-payments-admin.php

See also: Shared Framework Hooks Index → ../developer-guide.md#core-hooks-index
