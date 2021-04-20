<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 0.11.3
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

/**
 * Check if WooCommerce is active
 */
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

    function coopcycle_checkout_process() {
        // TODO Make sure shipping time is valid

        // Skip if another shipping method was chosen
        if (!CoopCycle::contains_accepted_shipping_method(wc_get_chosen_shipping_method_ids())) {
            return;
        }

        if (!$_POST['shipping_date'] || empty($_POST['shipping_date'])) {
            wc_add_notice(__('Please choose a shipping date.', 'coopcycle'), 'error');
        }
    }

    function coopcycle_checkout_update_order_meta($order_id) {

        // Skip if another shipping method was chosen
        if (!CoopCycle::contains_accepted_shipping_method(wc_get_chosen_shipping_method_ids())) {
            return;
        }

        if (!empty($_POST['shipping_date'])) {
            update_post_meta($order_id, 'shipping_date', sanitize_text_field($_POST['shipping_date']));
        }
    }

    /**
     * Add custom field to choose shipping date
     */
    function coopcycle_shipping_date_dropdown() {

        if (!CoopCycle::contains_accepted_shipping_method(wc_get_chosen_shipping_method_ids())) {
            return '';
        }

        $options = CoopCycle_ShippingMethod::instance()->get_shipping_date_options();

        if (count($options) > 0) {
            ?>
            <tr>
                <th><strong><?php echo __('Shipping date', 'coopcycle') ?></strong></th>
                <td>
                    <select name="shipping_date" class="coopcycle-shipping-date" required>
                        <option value=""><?php echo __('Please choose your preferred time for shipping below', 'coopcycle') ?></option>
                        <?php foreach ($options as $value => $label) : ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php
        } else {
            ?>
            <td colspan="2">
                <span><?php echo __('No time slot available for shipping', 'coopcycle') ?></span>
            </td>
            <?php
        }
    }

    // add_action('woocommerce_after_shipping_calculator', 'coopcycle_shipping_date_dropdown');
    add_action('woocommerce_review_order_after_shipping', 'coopcycle_shipping_date_dropdown');

    add_action('woocommerce_checkout_process', 'coopcycle_checkout_process');
    add_action('woocommerce_checkout_update_order_meta', 'coopcycle_checkout_update_order_meta');

    /* Add custom columns to "shop_order" post list */

    function coopcycle_manage_shop_order_posts_columns($columns) {
        return array_merge($columns, array(
            'order_shipping_date' => __('Shipping date', 'coopcycle'),
        ));
    }
    function coopcycle_manage_shop_order_posts_custom_column($column, $post_id) {

        $shipping_date = get_post_meta($post_id, 'shipping_date', true);

        // FIXME Make this backwards compatible

        if ($column === 'order_shipping_date') {

            if ($shipping_date = get_post_meta($post_id, 'shipping_date', true)) {
                // TODO Format as human readable
                echo $shipping_date;
            }
        }
    }
    add_filter('manage_shop_order_posts_columns', 'coopcycle_manage_shop_order_posts_columns', 20);
    add_action('manage_shop_order_posts_custom_column', 'coopcycle_manage_shop_order_posts_custom_column', 10, 2);

    function coopcycle_enqueue_scripts() {
        wp_register_style('coopcycle', plugins_url('/css/coopcycle.css', __FILE__), array(), false);
        wp_enqueue_style('coopcycle');
    }

    add_action('wp_enqueue_scripts', 'coopcycle_enqueue_scripts');

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

            $task_comments =
                /* translators: order number, website, url. */
                sprintf(__('Order #%1$s from %2$s (%3$s)', 'coopcycle'), $order->get_order_number(), $wp_name, $wp_url);

            $customer_note = $order->get_customer_note();
            if (!empty($customer_note)) {
                $task_comments .= "\n\n".$customer_note;
            }

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
