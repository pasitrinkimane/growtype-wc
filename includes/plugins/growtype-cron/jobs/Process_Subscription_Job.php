<?php

class Process_Subscription_Job
{
    public function run($job)
    {
        error_log(sprintf('Process_Subscriptions_Job: %s', print_r($job, true)));

//        $job_payload = json_decode($job['payload'], true);
//
//        $order_id = $job_payload['order_id'];
//
//        $renewal_order = wc_get_order($order_id);
//
//        do_action('woocommerce_scheduled_subscription_payment_' . $renewal_order->get_payment_method(), $renewal_order->get_total(), $renewal_order);
//
//        error_log('Process_Subscriptions_Job: ' . $order_id);
    }
}
