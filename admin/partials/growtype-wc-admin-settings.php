<?php

class Growtype_Wc_Admin_Settings
{
    public function __construct()
    {
        add_action('admin_menu', array ($this, 'admin_menu_pages'));

        add_action('init', array ($this, 'process_form'));
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
                            settings_fields('growtype_wc_settings');

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-wc-settings', 'growtype_wc_settings_general');
                            echo '</table>';

                            break;
                        case 'generate':
                            settings_fields('growtype_wc_settings');

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-wc-settings', 'growtype_wc_settings_generate');
                            echo '</table>';

                            echo '<input type="hidden" name="generate_settings[generate]" value="1" />';

                            echo '<button type="submit" class="button button-primary">Generate</button>';

                            break;
                    }
                    ?>
                </form>
            </div>

            <?php
        }
    }

    function process_form()
    {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'growtype_wc_settings' && isset($_POST['generate_settings'])) {
            $generate_settings = $_POST['generate_settings'];

            $growtype_wc_crud = new Growtype_Wc_Crud();
            $growtype_wc_crud->generate_products($generate_settings);
        }

//        menu_page_url('growtype-wc-settings', false) . sprintf('&tab=%s', $tab, '')
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
}
