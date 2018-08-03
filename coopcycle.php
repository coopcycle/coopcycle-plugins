<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 0.3.0
 */

if (is_admin()) {
    require __DIR__ . '/CoopCycleSettingsPage.php';
    $settings_page = new CoopCycleSettingsPage();
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($settings_page, 'add_action_link'));
}

require __DIR__ . '/HttpClient.php';

// FIXME How to allow customer to choose shipping date?
// @see https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function coopcycle_shipping_method_init() {
        require __DIR__ . '/ShippingMethod.php';
    }

    add_action('woocommerce_shipping_init', 'coopcycle_shipping_method_init');

    function add_your_shipping_method($methods) {
        $methods['coopcycle_shipping_method'] = 'CoopCycle_ShippingMethod';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_your_shipping_method');

    function coopcycle_woocommerce_order_status_changed($order_id, $old_status, $new_status) {

        if ('completed' === $new_status) {

            $order = wc_get_order($order_id);

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

            $data = [
                'dropoff' => [
                    'address' => sprintf('%s %s %s',
                        $shipping_address['address_1'],
                        $shipping_address['postcode'],
                        $shipping_address['city']
                    )
                ]
            ];

            $httpClient = new CoopCycle_HttpClient();

            $delivery = $httpClient->post('/api/deliveries', $data);
        }
    }

    add_action('woocommerce_order_status_changed', 'coopcycle_woocommerce_order_status_changed', 10, 3);
}
