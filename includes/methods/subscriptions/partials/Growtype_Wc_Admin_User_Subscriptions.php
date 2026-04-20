<?php

class Growtype_Wc_Admin_User_Subscriptions
{
    public function __construct()
    {
        add_action('show_user_profile', array($this, 'add_subscriptions_table'), 20);
        add_action('edit_user_profile', array($this, 'add_subscriptions_table'), 20);
    }

    /**
     * Renders a table of the user's growtype_wc_subs subscriptions on the admin profile page.
     *
     * @param WP_User $user
     */
    public function add_subscriptions_table($user)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $subscriptions = growtype_wc_get_subscriptions([
            'user_id' => $user->ID,
            'limit'   => -1,
        ]);

        ?>
        <hr/>
        <h3><?php _e('Subscriptions', 'growtype-wc'); ?></h3>
        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px; border-radius: 8px; overflow: hidden;">
            <thead>
            <tr>
                <th style="width: 80px;"><?php _e('ID', 'growtype-wc'); ?></th>
                <th><?php _e('Subscription', 'growtype-wc'); ?></th>
                <th><?php _e('Status', 'growtype-wc'); ?></th>
                <th><?php _e('Price', 'growtype-wc'); ?></th>
                <th><?php _e('Billing Period', 'growtype-wc'); ?></th>
                <th><?php _e('Parent Order', 'growtype-wc'); ?></th>
                <th><?php _e('Created', 'growtype-wc'); ?></th>
                <th><?php _e('Action', 'growtype-wc'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($subscriptions)) : ?>
                <tr>
                    <td colspan="8"><?php _e('No subscriptions found for this user.', 'growtype-wc'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($subscriptions as $sub) : 
                    // $sub is expected to be an object from growtype_wc_get_subscriptions
                    $sub_id = is_object($sub) ? $sub->ID : $sub['ID'];
                    $status = get_post_meta($sub_id, '_status', true);
                    $price  = get_post_meta($sub_id, '_sub_price', true);
                    $period = get_post_meta($sub_id, '_sub_period', true);
                    $parent = get_post_meta($sub_id, '_order_id', true);
                    $date   = get_the_date('', $sub_id);
                    $edit_url = get_edit_post_link($sub_id);
                    ?>
                    <tr>
                        <td><strong>#<?php echo $sub_id; ?></strong></td>
                        <td><?php echo get_the_title($sub_id); ?></td>
                        <td>
                            <span class="badge" style="
                                padding: 3px 8px; 
                                border-radius: 999px; 
                                font-size: 11px; 
                                text-transform: uppercase;
                                background: <?php echo ($status === 'active' ? '#e7f9ed' : '#fbeae5'); ?>;
                                color: <?php echo ($status === 'active' ? '#227131' : '#b32d2e'); ?>;
                            ">
                                <?php echo esc_html($status); ?>
                            </span>
                        </td>
                        <td><?php echo wc_price($price); ?></td>
                        <td><?php echo esc_html($period); ?></td>
                        <td>
                            <?php if ($parent) : ?>
                                <a href="<?php echo get_edit_post_link($parent); ?>">#<?php echo $parent; ?></a>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($date); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                <?php _e('Edit Post', 'growtype-wc'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p class="description">
            <?php _e('These are recurring subscriptions managed by the Growtype WC system (independent of standard WooCommerce Subscriptions).', 'growtype-wc'); ?>
        </p>
        <?php
    }
}
