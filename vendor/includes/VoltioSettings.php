<?php

class VoltioSettings{
    private $voltio_settings_options;
    private $fields;
    public function __construct()
    {
        $this->fields = $this->voltio_fields();
        add_action('admin_menu', [$this, 'voltio_settings_add_plugin_page']);
        add_action('admin_init', [$this, 'voltio_settings_page_init']);
    }

    /**
     * @return array
     */
    public static function voltio_fields()
    {
        return [
            'id_seller' => [
                'label' => __('Seller ID', 'voltio'),
                'description' => __('Your tpay.com merchant ID. A number with at least four digits (can be five digits), e.g. 12345',
                    'tpay')
            ],
            'security_code' => [
                'label' => __('Security code', 'tpay'),
                'description' => __('The security code for your tpay.com account.', 'tpay')
            ],
            'api_key' => [
                'label' => __('API key', 'tpay'),
                'description' => __('API key generated in tpay.com payment recipient\'s panel.',
                    'tpay')
            ],
            'api_key_password' => [
                'label' => __('API key password', 'tpay'),
                'description' => __('API key password', 'tpay'),
            ],
            'transaction_title' => [
                'label' => __('Transaction title', 'tpay'),
                'description' => __('Transaction title', 'tpay'),
            ],
        ];
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
     * @return void
     */
    public function voltio_settings_page_init()
    {
        register_setting(
            'voltio_settings_option_group', // option_group
            'voltio_settings_option_name', // option_name
            [$this, 'voltio_settings_sanitize'] // sanitize_callback
        );

        //global
        add_settings_section(
            'voltio_settings_setting_section', // id
            __('Volt config global', 'voltio'), // title
            [], // callback
            'voltio-settings-admin' // page
        );
        foreach ($this->fields as $field => $desc) {
            $args = [
                'id' => 'global_' . $field,
                'desc' => $desc['label'],
                'name' => 'voltio_settings_option_name'
            ];
            add_settings_field(
                $args['id'], // id
                $args['desc'], // title
                [$this, 'global_callback'], // callback
                'voltio-settings-admin', // page
                'voltio_settings_setting_section',
                $args
            );
        }
        add_settings_field(
            'global_default_on_hold_status', // id
            __('Default on-hold status', 'voltio'), // title
            [$this, 'global_default_on_hold_status_callback'], // callback
            'voltio-settings-admin', // page
            'voltio_settings_setting_section' // section
        );
        add_settings_field(
            $args['id'], // id
            $args['desc'], // title
            [$this, 'global_callback'], // callback
            'voltio-settings-admin', // page
            'voltio_settings_setting_section', // section
            $args
        );
        $args = [
            'id' => 'global_percentage_fee',
            'desc' => 'Percentage fee',
            'name' => 'voltio_settings_option_name'
        ];
        add_settings_field(
            $args['id'], // id
            $args['desc'], // title
            [$this, 'global_callback'], // callback
            'voltio-settings-admin', // page
            'voltio_settings_setting_section', // section
            $args
        );
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
     * @param array $input
     * @return array
     */
    public function voltio_settings_sanitize($input)
    {
        foreach ($this->fields as $field => $desc) {
            if (isset($input['global_' . $field])) {
                $sanitary_values['global_' . $field] = sanitize_text_field($input['global_' . $field]);
            }
        }

        if (isset($input['global_default_on_hold_status'])) {
            $sanitary_values['global_default_on_hold_status'] = sanitize_text_field($input['global_default_on_hold_status']);
        }

        if (isset($input['global_enable_fee'])) {
            $sanitary_values['global_enable_fee'] = sanitize_text_field($input['global_enable_fee']);
        }

        if (isset($input['global_amount_fee'])) {
            $sanitary_values['global_amount_fee'] = sanitize_text_field($input['global_amount_fee']);
        }

        if (isset($input['global_percentage_fee'])) {
            $sanitary_values['global_percentage_fee'] = sanitize_text_field($input['global_percentage_fee']);
        }


        return $sanitary_values;
    }


    /**
     * @return null
     */
    public function global_default_on_hold_status_callback()
    {
        ?>
        <select class="regular-text" type="text" name="voltio_settings_option_name[global_default_on_hold_status]"
                id="global_default_on_hold_status">
            <?php foreach ($this->before_payment_statuses() as $key => $value): ?>
                <option <?php if (@$this->voltio_settings_options['global_default_on_hold_status'] === $key) echo 'selected="selected"' ?>
                    value="<?php echo $key ?>"><?php echo $value ?></option>
            <?php endforeach; ?>
        </select>
        <?php
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