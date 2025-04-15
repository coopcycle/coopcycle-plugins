<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 1.0.0
 * Domain Path: /i18n/languages/
 */

require_once __DIR__ . '/src/CoopCycle.php';
require_once __DIR__ . '/src/HttpClient.php';

if (is_admin()) {
    require_once __DIR__ . '/src/CoopCycleSettingsPage.php';
    $settings_page = new CoopCycleSettingsPage();
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($settings_page, 'add_action_link'));
}

function coopcycle_load_plugin_textdomain() {
    load_plugin_textdomain('coopcycle', false, basename(dirname( __FILE__ )) . '/i18n/languages/');
}
add_action('plugins_loaded', 'coopcycle_load_plugin_textdomain');

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

    // Add custom columns to orders list
    // https://stackoverflow.com/questions/36446617/add-columns-to-admin-orders-list-in-woocommerce-hpos

    function coopcycle_manage_shop_order_posts_columns($columns) {
        return array_merge($columns, array(
            'order_shipping_date' => __('Shipping date', 'coopcycle'),
        ));
    }
    function coopcycle_manage_shop_order_posts_custom_column($column, $order) {
        if ($column === 'order_shipping_date') {
            if ($order->meta_exists('shipping_date')) {
                // TODO Format as human readable
                echo $order->get_meta('shipping_date');
            }
        }
    }
    add_filter('manage_woocommerce_page_wc-orders_columns', 'coopcycle_manage_shop_order_posts_columns', 20);
    add_action('manage_woocommerce_page_wc-orders_custom_column', 'coopcycle_manage_shop_order_posts_custom_column', 10, 2);

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
