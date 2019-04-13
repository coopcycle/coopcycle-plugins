<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 0.7.0
 * Domain Path: /i18n/languages/
 */

require_once __DIR__ . '/src/CoopCycle.php';
require_once __DIR__ . '/src/HttpClient.php';

if (is_admin()) {
    require_once __DIR__ . '/src/CoopCycleSettingsPage.php';
    $settings_page = new CoopCycleSettingsPage();
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($settings_page, 'add_action_link'));
}

function coopcycle_shipping_dates(\DateTime $now = null) {

    if (null === $now) {
        $now = new \DateTime();
    }

    $openingHoursSpecification =
        json_decode(file_get_contents(__DIR__ . '/opening-hours.json'), true);

    $daysOfWeek = array(
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    );

    foreach ($openingHoursSpecification as $key => $openingHours) {
        foreach ($daysOfWeek as $dayOfWeek) {
            if (1 === preg_match("/${dayOfWeek}/", $openingHours['dayOfWeek'])) {
                $openingHoursSpecification[$key]['dayOfWeek'] = $dayOfWeek;
            }
        }
    }

    $candidates = array();
    $storage = new \SplObjectStorage();
    foreach ($openingHoursSpecification as $key => $openingHours) {

        $date = clone $now;
        $expression = sprintf('next %s', strtolower($openingHours['dayOfWeek']));
        $date->modify($expression);

        $storage[$date] = $openingHours;
        $candidates[] = $date;
    }

    sort($candidates);

    $shipping_dates = array();
    foreach ($candidates as $shipping_date) {

        $opening_hours = $storage[$shipping_date];

        $pattern = '/([0-9]+):([0-9]+):([0-9]+)/';

        $opens = clone $shipping_date;
        $closes = clone $shipping_date;

        preg_match($pattern, $opening_hours['opens'], $matches);
        $opens->setTime($matches[1], $matches[2], $matches[3]);

        preg_match($pattern, $opening_hours['closes'], $matches);
        $closes->setTime($matches[1], $matches[2], $matches[3]);

        $shipping_dates[] = array(
            'opens' => $opens,
            'closes' => $closes,
        );
    }

    return $shipping_dates;
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

        if (!$_POST['shipping_date'] || !$_POST['shipping_time']) {
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
        if (!empty($_POST['shipping_time'])) {
            update_post_meta($order_id, 'shipping_time', sanitize_text_field($_POST['shipping_time']));
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

        $shipping_dates = coopcycle_shipping_dates();

        $shipping_date_options = array();
        $shipping_times_by_shipping_date = array();
        foreach ($shipping_dates as $shipping_date) {
            $opens = $shipping_date['opens'];
            $closes = $shipping_date['closes'];

            $shipping_date_options[$opens->format('Y-m-d')] = date_i18n('l d F', $opens->getTimestamp());
            $shipping_times_by_shipping_date[$opens->format('Y-m-d')] = array(
                'opens' => $opens->format('Y-m-d H:i:s'),
                'closes' => $closes->format('Y-m-d H:i:s'),
            );
        }

        $fields['billing']['shipping_date'] = array(
            'type'      => 'select',
            'label'     => __('Shipping date', 'coopcycle'),
            'required'  => true,
            // Field is hidden by default, it will be shown via JavaScript
            // @see coopcycle_after_shipping_rate
            'class'     => array('form-row-wide', 'form-row-hidden'),
            'clear'     => true,
            'priority'  => 120,
            'options'   => $shipping_date_options,
            'custom_attributes' => array(
                'data-shipping-time' => json_encode($shipping_times_by_shipping_date)
            )
        );

        $fields['billing']['shipping_time'] = array(
            'type'         => 'text',
            'label'        => __('Shipping time', 'coopcycle'),
            'required'     => true,
            // Field is hidden by default, it will be shown via JavaScript
            // @see coopcycle_after_shipping_rate
            'class'        => array('form-row-wide', 'form-row-hidden'),
            'clear'        => true,
            'priority'     => 130,
            'autocomplete' => false
        );

        return $fields;
    }

    /**
     * It is not easy to add extra fields depending on shipping method
     * @see https://github.com/woocommerce/woocommerce/issues/15753
     */
    function coopcycle_after_shipping_rate($method, $index) {

        // Show custom fields for accepted shipping methods
        if (CoopCycle::accept_shipping_method($method->get_method_id())) {
            echo '<script>';
            echo 'document.querySelector("#shipping_date_time_heading").classList.remove("form-row-hidden");';
            echo 'document.querySelector("#shipping_date_field").classList.remove("form-row-hidden");';
            echo 'document.querySelector("#shipping_time_field").classList.remove("form-row-hidden");';
            echo '</script>';
        }

        // TODO Hide fields when shipping method has changed
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
            'order_shipping_time' => __('Shipping time', 'coopcycle')
        ));
    }
    function coopcycle_manage_shop_order_posts_custom_column($column, $post_id) {

        $shipping_date = get_post_meta($post_id, 'shipping_date', true);
        $shipping_time = get_post_meta($post_id, 'shipping_time', true);

        $shipping_timestamp = strtotime("{$shipping_date} {$shipping_time}");

        switch ($column) {
            case 'order_shipping_date':
                echo date_i18n(get_option('date_format'), $shipping_timestamp);
                break;
            case 'order_shipping_time':
                echo date_i18n('H:i', $shipping_timestamp);
                break;
        }
    }
    add_filter('manage_shop_order_posts_columns', 'coopcycle_manage_shop_order_posts_columns', 20);
    add_action('manage_shop_order_posts_custom_column', 'coopcycle_manage_shop_order_posts_custom_column', 10, 2);

    function coopcycle_enqueue_scripts() {

        wp_register_style('rome', 'https://cdnjs.cloudflare.com/ajax/libs/rome/2.1.22/rome.min.css');
        wp_register_script('rome', 'https://cdnjs.cloudflare.com/ajax/libs/rome/2.1.22/rome.min.js');

        wp_register_style('coopcycle', plugins_url('/css/coopcycle.css', __FILE__), array(), false);
        wp_register_script('coopcycle', plugins_url('/js/coopcycle.js', __FILE__), array(), false, true);

        wp_enqueue_style('rome');
        wp_enqueue_script('rome');

        wp_enqueue_style('coopcycle');
        wp_enqueue_script('coopcycle');
    }

    add_action('wp_enqueue_scripts', 'coopcycle_enqueue_scripts');

    function coopcycle_woocommerce_order_status_changed($order_id, $old_status, $new_status) {

        if ('processing' === $new_status) {

            $order = wc_get_order($order_id);

            if (!CoopCycle::accept_order($order)) {
                return;
            }

            $shipping_date = $order->get_meta('shipping_date', true);
            $shipping_time = $order->get_meta('shipping_time', true);

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

            $data = array(
                // We only specify the dropoff data
                // Pickup is fully implicit
                'dropoff' => array(
                    'address' => sprintf('%s %s %s',
                        $shipping_address['address_1'],
                        $shipping_address['postcode'],
                        $shipping_address['city']
                    ),
                    'doneBefore' => sprintf('%s %s', $shipping_date, $shipping_time),
                )
            );

            $httpClient = new CoopCycle_HttpClient();

            try {

                $delivery = $httpClient->post('/api/deliveries', $data);

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
