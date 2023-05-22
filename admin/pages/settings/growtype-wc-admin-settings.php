<?php

class Growtype_Wc_Admin_Settings
{
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
            __('Growtype - Wc', 'growtype-wc'),
            __('Growtype - Wc', 'growtype-wc'),
            'manage_options',
            'growtype-wc-settings',
            array ($this, 'options_page_content'),
            1
        );
    }

    function options_page_content()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'growtype-wc-settings') { ?>

            <div class="wrap">

                <h1>Growtype Wc - Settings</h1>

                <?php
                if (isset($_GET['updated']) && 'true' == esc_attr($_GET['updated'])) {
                    echo '<div class="updated" ><p>Settings updated.</p></div>';
                }

                if (isset ($_GET['tab'])) {
                    $this->render_settings_tab_render($_GET['tab']);
                } else {
                    $this->render_settings_tab_render();
                }
                ?>

                <form id="growtype_wc_settings_form" method="post" action="options.php">
                    <?php

                    if (isset ($_GET['tab'])) {
                        $tab = $_GET['tab'];
                    } else {
                        $tab = Growtype_Wc_Admin::GROWTYPE_WC_SETTINGS_DEFAULT_TAB;
                    }

                    switch ($tab) {
                        case 'general':
                            settings_fields('growtype_wc_settings_general');

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-wc-settings', 'growtype_wc_settings_general_render');
                            echo '</table>';

                            break;
                        case 'plugins':
                            settings_fields('growtype_wc_settings_plugins');

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-wc-settings', 'growtype_wc_settings_plugins_render');
                            echo '</table>';

                            break;
                        case 'generate':
                            settings_fields('growtype_wc_settings_generate');

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-wc-settings', 'growtype_wc_settings_generate_render');
                            echo '</table>';

                            echo '<input type="hidden" name="generate_settings[generate]" value="1" />';

                            echo '<button type="submit" class="button button-primary">Generate</button>';

                            break;
                    }

                    if ($tab !== 'generate') {
                        submit_button();
                    }

                    ?>
                </form>
            </div>

            <?php
        }
    }

    function process_posted_data()
    {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'growtype_wc_settings_generate') {
            if (isset($_POST['generate_settings'])) {
                $growtype_wc_crud = new Growtype_Wc_Crud();
                $growtype_wc_crud->generate_products();
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
