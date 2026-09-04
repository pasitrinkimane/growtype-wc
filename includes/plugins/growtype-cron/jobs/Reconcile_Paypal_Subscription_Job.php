<?php

class Reconcile_Paypal_Subscription_Job
{
    public function run($job): void
    {
        $payload = json_decode($job['payload'] ?? '', true);
        $subscription_id = (int) ($payload['subscription_id'] ?? 0);
        if ($subscription_id <= 0) {
            error_log('[GWC PayPal Reconciliation] Invalid subscription job payload.');
            return;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways[Growtype_Wc_Payment_Gateway_Paypal::PROVIDER_ID] ?? null;
        if (
            !$gateway instanceof Growtype_Wc_Payment_Gateway_Paypal
            || !$gateway->subscriptions instanceof Growtype_Wc_Payment_Gateway_Paypal_Subscriptions
        ) {
            error_log(sprintf(
                '[GWC PayPal Reconciliation] Gateway unavailable for local subscription #%d.',
                $subscription_id
            ));
            return;
        }

        $gateway->subscriptions->reconcile_subscription($subscription_id);
    }
}
