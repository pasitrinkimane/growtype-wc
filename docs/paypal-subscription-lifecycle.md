# PayPal subscription lifecycle

## Safety invariants

- An Orders API capture creates recurring access only when the checkout was
  explicitly classified as a supported recurring vault flow, the initial
  transaction was marked `CUSTOMER`/`RECURRING`/`FIRST`, and PayPal returned a
  verified `VAULTED` token and customer ID for the requested payment source.
- A PayPal subscription order becomes locally active only after an authoritative
  provider response verifies the expected subscription, plan, order, amount,
  currency, and `ACTIVE` status.
- Recurring benefits fail closed when provider verification is unavailable or
  the provider does not currently report `ACTIVE`.
- Provider cancellation succeeds before local cancellation is persisted.
- Webhook event IDs and local subscription creation are idempotent.
- Merchant-managed vault renewals have one durable billing-date claim and a
  deterministic PayPal request ID. Unknown outcomes stop benefits and put the
  subscription on hold; they are never retried automatically.

## Checkout flows

`Growtype_Wc_Payment_Gateway_Paypal::resolve_checkout_flow()` is the routing
contract. Current flows are:

| Product | Payment source | Flow |
| --- | --- | --- |
| One-time | PayPal, card, Apple Pay, Google Pay | `orders_api_one_time` |
| Subscription | PayPal | `billing_subscription` |
| Subscription | Hosted Fields card | `orders_api_recurring_card` |
| Subscription | Apple Pay | `orders_api_recurring_apple_pay` |
| Subscription | Google Pay | `unavailable` |

Use `growtype_wc_paypal_checkout_flow` to register a future implementation under
a new flow name. Do not map a recurring method to `orders_api_one_time`. A new
flow needs its own consent, token/agreement creation, renewal, retry,
cancellation, webhook, reconciliation, and activation-verification code before
the UI routes customers to it.

Every newly created order stores `_growtype_wc_checkout_flow`, allowing later
activation to verify how the order was created. Older genuine PayPal Billing
Subscriptions without this metadata remain compatible.

`orders_api_recurring_card` and `orders_api_recurring_apple_pay` are
merchant-managed recurring billing. PayPal vaults the selected source, while
Growtype WC schedules and submits each renewal with
`MERCHANT`/`RECURRING`/`SUBSEQUENT`. Cancelling the local subscription stops
future renewal jobs because PayPal does not maintain an `I-...` billing schedule
for this flow.

Google Pay remains unavailable for subscriptions because PayPal's current
Google Pay contract documents one-time payment sessions but not reusable vault
tokens or merchant-initiated recurring charges.

## Provider extension points

- `growtype_wc_can_activate_subscription_for_order`: provider proof required
  before creating a local subscription.
- `growtype_wc_pre_change_subscription_status`: provider mutation required
  before changing local status.
- `growtype_wc_can_process_subscription_benefits`: provider verification
  immediately before recurring benefits are granted.
- `growtype_wc_paypal_webhook_event_handlers`: signed webhook handler registry.
- `growtype_wc_paypal_subscription_status_map`: provider-to-local state mapping.
- `growtype_wc_paypal_supported_local_statuses`: allowlist for mapped local
  states.
- `growtype_wc_paypal_reconciliation_batch_size`: daily queue-generation batch
  size, clamped to 25-500.

Provider-specific filters should return `WP_Error` on uncertainty. Callers treat
errors and every value other than strict `true` as denial.

## Adding a recurring payment source

1. Register a distinct checkout flow for the payment source.
2. Implement provider consent and create a durable recurring agreement.
3. Store the provider reference and checkout-flow provenance on the WC order.
4. Register strict activation, cancellation, and benefit-eligibility guards.
5. Add signed, idempotent webhook handlers and authoritative reconciliation.
6. Add regression coverage for mismatched IDs, amounts, currencies, duplicate
   callbacks, provider downtime, cancellation races, and replayed webhooks.
7. Enable the payment source in the subscription UI only after provider sandbox
   and rendered-browser verification pass.
