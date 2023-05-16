<?php

class VoltioSettings{
    private $voltio_settings_options;
    private $fields;
    public function __construct()
    {
        add_action('admin_menu', [$this, 'voltio_settings_add_plugin_page']);
    }

    /**
     * @return null
     */
    public function voltio_settings_add_plugin_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Volt settings', 'voltio'), // page_title
            __('Volt settings', 'voltio'), // menu_title
            'manage_options', // capability
            'voltio-settings', // menu_slug
            [$this, 'voltio_settings_create_admin_page'], // function
            100
        );
    }

    /**
     * @return void
     */
    public function voltio_settings_create_admin_page()
    {
        $this->voltio_settings_options = get_option('voltio_settings_option_name'); ?>

        <div class="wrap">
            <h2><?php echo __('Volt settings', 'voltio') ?></h2>
            <p></p>
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('voltio_settings_option_group');
                do_settings_sections('voltio-settings-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    /**
     * @param array $args
     * @return void
     */
    public function global_callback($args)
    {
        $id = $args['id'];
        $value = isset($this->voltio_settings_options[$id]) ? esc_attr($this->voltio_settings_options[$id]) : '';
        printf('<input type="text" class="regular-text" value="%s" name="voltio_settings_option_name[%s]" id="%s" />',
            $value, $id, $id);
    }


    /**
     * @return array
     */
    public function before_payment_statuses()
    {
        $statuses = wc_get_order_statuses();
        $available = [];
        foreach ($statuses as $key => $value) {
            if (in_array($key, ['wc-pending', 'wc-on-hold'])) {
                $available[str_replace('wc-', '', $key)] = $value;
            }
        }
        ksort($available);
        return $available;
    }
}

if (is_admin()) {
    new VoltioSettings();
}