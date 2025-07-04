<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 1.1.0
 * Domain Path: /i18n/languages/
 */

require_once __DIR__ . '/src/CoopCycle.php';
require_once __DIR__ . '/src/HttpClient.php';

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

if (is_admin()) {
    require_once __DIR__ . '/src/CoopCycleSettingsPage.php';
    $settings_page = new CoopCycleSettingsPage();
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($settings_page, 'add_action_link'));
}

add_action('plugins_loaded', 'coopcycle_init');

// https://github.com/woocommerce/woocommerce/tree/trunk/packages/js/extend-cart-checkout-block

add_action(
    'init',
    function () {
        register_block_type_from_metadata( __DIR__ . '/build/shipping-date-picker' );
    }
);

function get_rest_shipping_date_options() {

    $options = CoopCycle_ShippingMethod::instance()->get_shipping_date_options();

    $data = [];
    foreach ($options as $value => $label) {
        $data[] = [
            'value' => $value,
            'label' => $label,
        ];
    }

    return new WP_REST_Response(['options' => $data], 200);
}

add_action('rest_api_init', function () {
  register_rest_route('coopcycle/v1', '/shipping-date-options', array(
    'methods' => 'GET',
    'callback' => 'get_rest_shipping_date_options',
  ));
});

add_action(
    'woocommerce_blocks_loaded',
    function () {

        require_once __DIR__ . '/coopcycle-blocks-integration.php';
        require_once __DIR__ . '/coopcycle-extend-store-endpoint.php';
        require_once __DIR__ . '/coopcycle-extend-woo-core.php';

        add_action(
            'woocommerce_blocks_cart_block_registration',
            function ( $integration_registry ) {
                $integration_registry->register( new Coopcycle_Blocks_Integration() );
            }
        );
        add_action(
            'woocommerce_blocks_checkout_block_registration',
            function ( $integration_registry ) {
                $integration_registry->register( new Coopcycle_Blocks_Integration() );
            }
        );

        Coopcycle_Extend_Store_Endpoint::init();

        $extend_core = new Coopcycle_Extend_Woo_Core();
        $extend_core->init();
    }
);

function coopcycle_init() {

    load_plugin_textdomain('coopcycle', false, basename(dirname( __FILE__ )) . '/i18n/languages/');

    /**
     * Check if WooCommerce is active
     */
    // https://github.com/woocommerce/woocommerce/blob/trunk/docs/extension-development/check-if-woo-is-active.md
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        function coopcycle_shipping_method_init() {
            require __DIR__ . '/src/ShippingMethod.php';
        }

        add_action('woocommerce_shipping_init', 'coopcycle_shipping_method_init');

        function add_your_shipping_method($methods) {
            $methods['coopcycle_shipping_method'] = 'CoopCycle_ShippingMethod';
            return $methods;
        }

        add_filter('woocommerce_shipping_methods', 'add_your_shipping_method');

        // Check if the shortcode is used
        // https://stackoverflow.com/questions/77948982/check-programatically-if-cart-or-checkout-blocks-are-used-in-woocommerce
        if (!CartCheckoutUtils::is_checkout_block_default()) {
            require_once __DIR__ . '/legacy_shortcode.php';
        }

        require_once __DIR__ . '/custom_colums.php';

        function coopcycle_woocommerce_order_status_changed($order_id, $old_status, $new_status) {

            if ('processing' === $new_status) {

                $order = wc_get_order($order_id);

                if (!CoopCycle::accept_order($order)) {
                    return;
                }

                // Avoid creating the delivery twice
                // if the order changes to "processing" more than once
                $coopcycle_delivery = $order->get_meta('coopcycle_delivery', true);
                if (!empty($coopcycle_delivery)) {
                    return;
                }

                $shipping_date = $order->get_meta('shipping_date', true);

                // Array
                // (
                //     [first_name]
                //     [last_name]
                //     [company]
                //     [address_1]
                //     [address_2]
                //     [city]
                //     [state]
                //     [postcode]
                //     [country]
                // )
                $shipping_address = $order->get_address('shipping');

                $street_address = sprintf('%s %s %s',
                    $shipping_address['address_1'],
                    $shipping_address['postcode'],
                    $shipping_address['city']
                );

                $contact_name = implode(' ', array_filter(array(
                    $shipping_address['first_name'],
                    $shipping_address['last_name']
                )));

                $wp_name = get_bloginfo('name');
                $wp_url = get_bloginfo('url');

                $items = "";

                foreach ($order->get_items() as &$it) {
                    $items .= sprintf("%sx %s \n", $it->get_quantity(), $it->get_name());
                }

                $task_comments =
                    /* translators: order number, website, url. */
                    sprintf(__('Order #%1$s from %2$s (%3$s)', 'coopcycle'), $order->get_order_number(), $wp_name, $wp_url);

                $customer_note = $order->get_customer_note();
                if (!empty($customer_note)) {
                    $task_comments .= "\n\n".$customer_note;
                }

                $task_comments.= "\n******\nItems : \n".$items;

                $data = array(
                    // We only specify the dropoff data
                    // Pickup is fully implicit
                    'pickup' => array(
                        'comments' => $task_comments,
                    ),
                    'dropoff' => array(
                        'address' => array(
                            'streetAddress' => $street_address,
                            'contactName' => $contact_name,
                        ),
                        'timeSlot' => $shipping_date,
                        'comments' => $task_comments,
                    )
                );

                $phone_number = get_user_meta($order->get_customer_id(), 'billing_phone', true);
                if (!$phone_number) {
                    $phone_number = $order->get_billing_phone();
                }

                if ($phone_number) {
                    $data['dropoff']['address']['telephone'] = $phone_number;
                }

                $http_client = CoopCycle::http_client();

                try {

                    $delivery = $http_client->post('/api/deliveries', $data);

                    // Save useful info in order meta
                    $order->update_meta_data('coopcycle_delivery', $delivery['@id']);

                    // Legacy
                    $order->update_meta_data('task_id', $delivery['dropoff']['id']);

                    $order->save();

                } catch (HttpClientException $e) {
                    // TODO Store something to retry API call later?
                }

            }
        }

        add_action('woocommerce_order_status_changed', 'coopcycle_woocommerce_order_status_changed', 10, 3);
    }

}
