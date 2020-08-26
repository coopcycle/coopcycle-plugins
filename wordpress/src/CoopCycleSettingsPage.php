<?php

/**
 * @see https://codex.wordpress.org/Creating_Options_Pages
 */
class CoopCycleSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    private $page = 'coopcycle-settings';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));

        add_action('pre_update_option', array($this, 'pre_update_option'), 10, 3);
    }

    public function add_plugin_page()
    {
        add_options_page(
            'CoopCycle',
            'CoopCycle',
            'manage_options',
            'coopcycle-settings',
            array($this, 'options_page')
        );
    }

    public function add_action_link($actions)
    {
        $settings = array(
            'settings' => '<a href="' . admin_url('admin.php?page=coopcycle-settings') . '">' . __( 'Settings' ) . '</a>'
        );

        return array_merge($settings, $actions);
    }

    public function options_page()
    {
        $app_name = get_option('coopcycle_app_name');

        ?>
        <div class="wrap">
            <h1>CoopCycle</h1>
            <?php if ($app_name) : ?>
            <div class="notice notice-info">
                <p><?php /* translators: app name. */ echo sprintf(__('Connected to app "%s"', 'coopcycle'), $app_name) ?></p>
            </div>
            <?php endif; ?>
            <form method="post" action="options.php">
            <?php
                settings_fields('coopcycle_woocommerce');
                do_settings_sections('coopcycle-settings');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        // Settings need to be registered first
        register_setting(
            'coopcycle_woocommerce',
            'coopcycle_base_url',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_base_url')
            )
        );
        register_setting(
            'coopcycle_woocommerce',
            'coopcycle_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key')
            )
        );
        register_setting(
            'coopcycle_woocommerce',
            'coopcycle_api_secret',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_secret')
            )
        );
        register_setting(
            'coopcycle_woocommerce',
            'coopcycle_free_shipping'
        );

        add_settings_section(
            'coopcycle_woocommerce',
            'General',
            array($this, 'print_section_info'),
            'coopcycle-settings'
        );

        add_settings_field(
            'coopcycle_base_url',
            'Base URL',
            array($this, 'coopcycle_base_url_callback'),
            'coopcycle-settings',
            'coopcycle_woocommerce'
        );

        add_settings_field(
            'coopcycle_api_key',
            'API Key',
            array($this, 'coopcycle_api_key_callback'),
            'coopcycle-settings',
            'coopcycle_woocommerce'
        );

        add_settings_field(
            'coopcycle_api_secret',
            'API Secret',
            array($this, 'coopcycle_api_secret_callback'),
            'coopcycle-settings',
            'coopcycle_woocommerce'
        );

        add_settings_field(
            'coopcycle_free_shipping',
            'Execute on free shipping',
            array($this, 'coopcycle_free_shipping_callback'),
            'coopcycle-settings',
            'coopcycle_woocommerce'
        );
    }

    public function print_section_info()
    {
    }

    public function sanitize_base_url($base_url)
    {
        $base_url = trim($base_url);

        if (empty($base_url)) {
            add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Base URL is empty', 'coopcycle'));
        }

        if (0 === preg_match('#^https?://#', $base_url)) {
            add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('The base URL must start with http:// or https://', 'coopcycle'));
        }

        return $base_url;
    }

    public function sanitize_api_key($api_key)
    {
        $api_key = trim($api_key);

        if (empty($api_key)) {
            add_settings_error('coopcycle_api_key', 'coopcycle_api_key', __('API key is empty', 'coopcycle'));
        }

        return $api_key;
    }

    public function sanitize_api_secret($api_secret)
    {
        $api_secret = trim($api_secret);

        if (empty($api_secret)) {
            add_settings_error('coopcycle_api_secret', 'coopcycle_api_secret', __('API secret is empty', 'coopcycle'));
        }

        return $api_secret;
    }

    public function coopcycle_base_url_callback()
    {
        $option = get_option('coopcycle_base_url');
        ?>
        <input class="regular-text" type="text"
            id="coopcycle_base_url" name="coopcycle_base_url" value="<?php echo isset($option) ? esc_attr($option) : '' ?>" />
        <p class="description"><?php echo __('The base URL of the CoopCycle instance (must start with http:// or https://)', 'coopcycle') ?></p>
        <?php
    }

    public function coopcycle_api_key_callback()
    {
        $option = get_option('coopcycle_api_key');

        printf(
            '<input class="regular-text" type="text" id="coopcycle_api_key" name="coopcycle_api_key" value="%s" />',
            isset( $option ) ? esc_attr($option) : ''
        );
    }

    public function coopcycle_api_secret_callback()
    {
        $option = get_option('coopcycle_api_secret');

        printf(
            '<input class="regular-text" type="text" id="coopcycle_api_secret" name="coopcycle_api_secret" value="%s" />',
            isset( $option ) ? esc_attr($option) : ''
        );
    }

    public function coopcycle_free_shipping_callback()
    {
        echo '<input type="checkbox" id="coopcycle_free_shipping" name="coopcycle_free_shipping" value="yes" '
            . checked('yes', get_option('coopcycle_free_shipping'), false) . ' />';
    }

    private function get_entrypoint($base_url)
    {
        $response = wp_remote_get(sprintf('%s/api', $base_url), array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $response_code !== 200) {

            return false;
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true);
    }

    private function validate_entrypoint($entrypoint)
    {
        return isset($entrypoint['@context']) && $entrypoint['@context'] === '/api/contexts/Entrypoint';
    }

    private function validate_credentials($base_url, $api_key, $api_secret)
    {
        $response = wp_remote_post(sprintf('%s/oauth2/token', $base_url), array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $api_key, $api_secret)))
            ),
            'body' => 'grant_type=client_credentials&scope=deliveries',
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $response_code !== 200) {

            return false;
        }

        return true;
    }

    public function pre_update_option($value, $option, $old_value)
    {
        if (in_array($option, array('coopcycle_base_url', 'coopcycle_api_key', 'coopcycle_api_secret'))) {

            $base_url = get_option('coopcycle_base_url');
            $api_key = get_option('coopcycle_api_key');
            $api_secret = get_option('coopcycle_api_secret');

            if (count(get_settings_errors()) === 0 && $base_url && $api_key && $api_secret) {

                if (!$entrypoint = $this->get_entrypoint($base_url)) {
                    add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Server is not compatible with CoopCycle', 'coopcycle'));

                    return $value;
                }

                if (!$this->validate_entrypoint($entrypoint)) {
                    add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Server is not compatible with CoopCycle', 'coopcycle'));

                    return $value;
                }

                if (!$this->validate_credentials($base_url, $api_key, $api_secret)) {
                    add_settings_error('coopcycle_api_key', 'coopcycle_api_key', __('API credentials are not valid', 'coopcycle'));

                    return $value;
                }

                if ($app_name = CoopCycle::get_app_name()) {
                    update_option('coopcycle_app_name', $app_name);
                }
            }
        }

        return $value;
    }
}
