<?php

class Growtype_Wc_Admin_Settings
{
    const PAGE_NAME = 'growtype-wc-settings';

    public function __construct()
    {
        if (is_admin()) {
            $this->load_tabs();

            add_action('admin_menu', array ($this, 'admin_menu_pages'));

            add_action('init', array ($this, 'process_posted_data'));
        }
    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {
        add_options_page(
            'Growtype - Wc',
            'Growtype - Wc',
            'manage_options',
            self::PAGE_NAME,
            array ($this, 'options_page_content'),
            1
        );
    }

    function options_page_content() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'growtype-wc-settings') {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : Growtype_Wc_Admin::GROWTYPE_WC_SETTINGS_DEFAULT_TAB;
        $is_generate_tab = ($tab === 'generate');
        ?>

        <div class="wrap">
            <h1>Growtype WC - Settings</h1>

            <?php if (isset($_GET['updated']) && esc_attr($_GET['updated']) === 'true') : ?>
                <div class="updated notice is-dismissible"><p>Settings updated.</p></div>
            <?php endif; ?>

            <?php $this->render_settings_tab_render($tab); ?>

            <form id="growtype_wc_settings_form" method="post" action="options.php">
                <?php
                // Map tab to settings group and section renderer
                $settings_map = [
                    'general'  => 'growtype_wc_settings_general',
                    'plugins'  => 'growtype_wc_settings_plugins',
                    'payments' => 'growtype_wc_settings_payments',
                    'generate' => 'growtype_wc_settings_generate',
                ];

                if (array_key_exists($tab, $settings_map)) {
                    settings_fields($settings_map[$tab]);

                    echo '<table class="form-table">';
                    do_settings_fields('growtype-wc-settings', "{$settings_map[$tab]}_render");
                    echo '</table>';
                }

                if ($is_generate_tab) {
                    echo '<input type="hidden" name="generate_settings[generate]" value="1" />';
                    echo '<button type="submit" class="button button-primary">Generate</button>';
                } else {
                    submit_button();
                }
                ?>
            </form>
        </div>

        <?php
    }

    function process_posted_data()
    {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'growtype_wc_settings_generate') {
            if (isset($_POST['growtype_wc_generate_products']) && !empty($_POST['growtype_wc_generate_products'])) {
                $growtype_wc_crud = new Growtype_Wc_Crud();
                $growtype_wc_crud->generate_products();
            }

            if (isset($_POST['growtype_wc_update_products']) && !empty($_POST['growtype_wc_update_products'])) {
                $growtype_wc_crud = new Growtype_Wc_Crud();
                $products_ids = $_POST['growtype_wc_update_products'] === 'all' ? [] : explode(',', $_POST['growtype_wc_update_products']);

                $growtype_wc_crud->update_products($products_ids);
            }

            wp_redirect(admin_url('admin.php?page=growtype-wc-settings&tab=generate&updated=true'));
            exit();
        }
    }

    function settings_tabs()
    {
        return apply_filters('growtype_wc_admin_settings_tabs', []);
    }

    function render_settings_tab_render($current = Growtype_Wc_Admin::GROWTYPE_WC_SETTINGS_DEFAULT_TAB)
    {
        $tabs = $this->settings_tabs();

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=growtype-wc-settings&tab=$tab'>$name</a>";

        }
        echo '</h2>';
    }

    public function load_tabs()
    {
        /**
         * General
         */
        include_once GROWTYPE_WC_PATH . 'admin/pages/settings/tabs/growtype-wc-admin-settings-general.php';
        new Growtype_Wc_Admin_Settings_General();

        /**
         * Payments
         */
        include_once GROWTYPE_WC_PATH . 'admin/pages/settings/tabs/growtype-wc-admin-settings-payments.php';
        new Growtype_Wc_Admin_Settings_Payments();

        /**
         * Generate
         */
        include_once GROWTYPE_WC_PATH . 'admin/pages/settings/tabs/growtype-wc-admin-settings-plugins.php';
        new Growtype_Wc_Admin_Settings_Plugins();

        /**
         * Generate
         */
        include_once GROWTYPE_WC_PATH . 'admin/pages/settings/tabs/growtype-wc-admin-settings-generate.php';
        new Growtype_Wc_Admin_Settings_Generate();
    }
}
