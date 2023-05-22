<?php

class WC_Printful_Shipping_Standard_Carbon_Offset_Method extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'printful_shipping_standard_carbon_offset'; // Id for your shipping method. Should be uunique.
        $this->method_title = __('Printful Standard Carbon offset Shipping');  // Title shown in admin
        $this->method_description = __('Printful shipping standard carbon offset method.'); // Description shown in admin
        $this->instance_id = absint($instance_id);

        $this->supports = array (
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    function init()
    {
        $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

        $this->title = $this->get_option('title');

        add_action('woocommerce_update_options_shipping_' . $this->id, array ($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array (
            'title' => array (
                'title' => __('Method title', 'growtype-wc'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'growtype-wc'),
                'default' => __('Carbon offset', 'growtype-wc'),
                'desc_tip' => true,
            )
        );
    }

    public function calculate_shipping($package = array ())
    {
        $rate = array (
            'label' => $this->title,
            'cost' => '8'
        );

        $this->add_rate($rate);
    }
}
