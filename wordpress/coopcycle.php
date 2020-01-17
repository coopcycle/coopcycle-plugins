<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 0.8.3
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
     * We use this filter to prepend a separator before the "shipping_date" field
     * @see https://github.com/woocommerce/woocommerce/blob/74693979db82198284a10e2610378a26a6a54939/includes/wc-template-functions.php#L2447
     */
    function coopcycle_form_field($field, $key, $args, $value) {

        if ($key === 'shipping_date') {

            // Make sure to include data-priority, or the row is reordered by JavaScript!
            $separator = '<p class="form-row form-row-hidden" data-priority="'.$args['priority'].'" id="shipping_date_time_heading">'
                . __('Please choose your preferred time for shipping below', 'coopcycle')
                . '</p>';

            return $separator . $field;
        }

        return $field;
    }

    /**
     * Add custom field to choose shipping date
     * @see https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
     */
    function coopcycle_checkout_fields($fields) {

        if (!class_exists('CoopCycle_ShippingMethod')) {
            return $fields;
        }

        $shipping_method = CoopCycle_ShippingMethod::instance();
        $options = $shipping_method->get_shipping_date_options();

        if (!$options) {
            return $fields;
        }

        // Add an empty option at the beginning
        $options_with_defaults = array('' => '');
        foreach ($options as $key => $value) {
            $options_with_defaults[$key] = $value;
        }

        $is_hidden = !CoopCycle::contains_accepted_shipping_method(wc_get_chosen_shipping_method_ids());
        $css_class = array('form-row-wide');
        if ($is_hidden) {
            $css_class[] = 'form-row-hidden';
        }

        $fields['billing']['shipping_date'] = array(
            'type'      => 'select',
            'label'     => __('Shipping date', 'coopcycle'),
            'required'  => true,
            // Field is hidden by default, it will be shown via JavaScript
            // @see coopcycle_after_shipping_rate
            'class'     => $css_class,
            'clear'     => true,
            'priority'  => 120,
            'options'   => $options_with_defaults,
        );

        return $fields;
    }

    /**
     * It is not easy to add extra fields depending on shipping method
     * This appends HTML *AFTER* the radio button to choose shipping rate
     * @see https://github.com/woocommerce/woocommerce/issues/15753
     */
    function coopcycle_after_shipping_rate($method, $index) {

        // We check if the desired shipping method is checked to toggle form fields
        if (CoopCycle::accept_shipping_method($method->get_method_id())) {
            echo "<script>\n";
            echo "(function () {\n";

            echo "  var input = document.querySelector('#shipping_date_field');\n";
            echo "  var heading = document.querySelector('#shipping_date_time_heading');\n";

            echo "  var hiddenField = document.querySelector('#shipping_method input[type=\"hidden\"][value=\"{$method->id}\"]');\n";
            echo "  var radioButton = document.querySelector('#shipping_method input[type=\"radio\"][value=\"{$method->id}\"]');\n";

            echo "  if (hiddenField || (radioButton && radioButton.checked)) {\n";
            echo "    input && input.classList.remove('form-row-hidden');\n";
            echo "    heading && heading.classList.remove('form-row-hidden');\n";
            echo "  } else {\n";
            echo "    input && input.classList.add('form-row-hidden');\n";
            echo "    heading && heading.classList.add('form-row-hidden');\n";
            echo "  }\n";

            echo "})();\n";
            echo '</script>';
        }
    }

    add_filter('woocommerce_form_field', 'coopcycle_form_field', 10, 4);
    add_filter('woocommerce_checkout_fields' , 'coopcycle_checkout_fields');
    add_action('woocommerce_after_shipping_rate', 'coopcycle_after_shipping_rate', 10, 2);

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

            $data = array(
                // We only specify the dropoff data
                // Pickup is fully implicit
                'dropoff' => array(
                    'address' => array(
                        'streetAddress' => $street_address,
                        'telephone' => get_user_meta($order->get_customer_id(), 'billing_phone', true),
                        'contactName' => $contact_name,
                    ),
                    'timeSlot' => $shipping_date,
                    'comments' => $order->get_customer_note(),
                )
            );

            $http_client = CoopCycle::http_client();

            try {

                $delivery = $http_client->post('/api/deliveries', $data);

                // Save task id in order meta
                $order->update_meta_data('task_id', $delivery['dropoff']['id']);
                $order->save();

            } catch (HttpClientException $e) {
                // TODO Store something to retry API call later?
            }

        }
    }

    add_action('woocommerce_order_status_changed', 'coopcycle_woocommerce_order_status_changed', 10, 3);
}
