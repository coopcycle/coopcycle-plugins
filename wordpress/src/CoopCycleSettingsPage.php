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

        add_action('updated_option', array($this, 'updated_option'), 10, 3);
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
        ?>
        <div class="wrap">
            <h1>CoopCycle</h1>
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
            add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Base URL is empty'));
        }

        if (0 === preg_match('#^https?://#', $base_url)) {
            $base_url = 'http://' . $base_url;
        }

        // TODO Guess http / https

        return $base_url;
    }

    public function sanitize_api_key($api_key)
    {
        $api_key = trim($api_key);

        if (empty($api_key)) {
            add_settings_error('coopcycle_api_key', 'coopcycle_api_key', __('API key is empty'));
        }

        return $api_key;
    }

    public function sanitize_api_secret($api_secret)
    {
        $api_secret = trim($api_secret);

        if (empty($api_secret)) {
            add_settings_error('coopcycle_api_secret', 'coopcycle_api_secret', __('API secret is empty'));
        }

        return $api_secret;
    }

    public function coopcycle_base_url_callback()
    {
        $option = get_option('coopcycle_base_url');
        ?>
        <input class="regular-text" type="text"
            id="coopcycle_base_url" name="coopcycle_base_url" value="<?php echo isset($option) ? esc_attr($option) : '' ?>" />
        <p class="description">The base URL of the CoopCycle instance</p>
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

    public function updated_option($option_name, $old_value, $new_value)
    {
        if (in_array($option_name, array('coopcycle_base_url', 'coopcycle_api_key', 'coopcycle_api_secret'))) {

            $base_url = get_option('coopcycle_base_url');
            $api_key = get_option('coopcycle_api_key');
            $api_secret = get_option('coopcycle_api_secret');

            if ($base_url && $api_key && $api_secret) {

                $response = wp_remote_get(sprintf('%s/api', $base_url), array(
                    'timeout' => 30,
                    'sslverify' => false,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                    ),
                ));

                $response_code = wp_remote_retrieve_response_code($response);
                if (is_wp_error($response) || $response_code !== 200) {
                    add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Server is not compatible with CoopCycle'));
                    return;
                }

                $body = wp_remote_retrieve_body($response);

                $entrypoint = json_decode($body, true);

                $entrypoint_valid = isset($entrypoint['@context']) && $entrypoint['@context'] === '/api/contexts/Entrypoint';

                if (!$entrypoint_valid) {
                    add_settings_error('coopcycle_base_url', 'coopcycle_base_url', __('Server is not compatible with CoopCycle'));
                    return;
                }

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
                    add_settings_error('coopcycle_api_key', 'coopcycle_api_key', __('API credentials are not valid'));
                    return;
                }
            }
        }
    }
}
