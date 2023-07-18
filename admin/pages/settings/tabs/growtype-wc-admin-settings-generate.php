<?php

class Growtype_Wc_Admin_Settings_Generate
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_wc_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['generate'] = 'Generator';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_wc_settings_generator',
            'growtype_wc_settings_generate_products'
        );

        add_settings_field(
            'growtype_wc_settings_generate_products',
            'Generate products',
            array ($this, 'growtype_wc_optimization_input'),
            Growtype_Wc_Admin_Settings::PAGE_NAME,
            'growtype_wc_settings_generate_render',
            [
                'name' => 'growtype_wc_generate_products',
                'type' => 'checkbox',
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_wc_settings_generator',
            'growtype_wc_settings_update_products'
        );

        add_settings_field(
            'growtype_wc_settings_update_products',
            'Update existing products (comma separated ids. Type "all" to update all)',
            array ($this, 'growtype_wc_optimization_input'),
            Growtype_Wc_Admin_Settings::PAGE_NAME,
            'growtype_wc_settings_generate_render',
            [
                'name' => 'growtype_wc_update_products',
                'type' => 'text',
            ]
        );
    }

    /**
     *
     */
    function growtype_wc_optimization_input($args)
    {
        ?>
        <input type="<?php echo $args['type'] ?>" class="regular-text ltr" name="<?php echo $args['name'] ?>"/>
        <?php

        if (isset($args['amount'])) {
            echo '<p>Amount: ' . $args['amount'] . '</p>';
        }
    }
}
