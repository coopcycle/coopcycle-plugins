<?php

/**
 * Plugin Name: CoopCycle
 * Plugin URI: https://coopcycle.org/
 * Description: CoopCycle plugin for WordPress
 * Version: 0.3.0
 */

require_once __DIR__ . '/src/HttpClient.php';

if (is_admin()) {
    require_once __DIR__ . '/src/CoopCycleSettingsPage.php';
    $settings_page = new CoopCycleSettingsPage();
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), array($settings_page, 'add_action_link'));
}

function coopcycle_next_shipping_date(\DateTime $now = null) {

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
        $date = new \DateTime(sprintf('next %s', strtolower($openingHours['dayOfWeek'])));
        $storage[$date] = $openingHours;
        $candidates[] = $date;
    }

    sort($candidates);

    $next = current($candidates);

    $opens = clone $next;
    $closes = clone $next;

    $openingHours = $storage[$next];

    $pattern = '/([0-9]+):([0-9]+):([0-9]+)/';

    preg_match($pattern, $openingHours['opens'], $matches);
    $opens->setTime($matches[1], $matches[2], $matches[3]);

    preg_match($pattern, $openingHours['closes'], $matches);
    $closes->setTime($matches[1], $matches[2], $matches[3]);

    return array(
        'opens' => $opens,
        'closes' => $closes,
    );
}

coopcycle_next_shipping_date();

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

    /**
     * Add custom field to choose shipping date
     * @see https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
     */

    function coopcycle_custom_checkout_field($checkout) {

        $nextShippingDate = coopcycle_next_shipping_date();

        $opens = $nextShippingDate['opens'];
        $closes = $nextShippingDate['closes'];

        $nextShippingDateFormatted = date_i18n('l d F', $opens->getTimestamp());

        echo '<div id="my_custom_checkout_field">';
        echo '<h3>' . __('Shipping date') . '</h3>';

        // Customer cannot choose shipping date
        echo '<input type="hidden" name="order_shipping_date" id="order_shipping_date" value="' . $opens->format('Y-m-d') . '" />';

        echo '<p>';
        echo sprintf(__('The next shipping date is %s', 'coopcycle'), '<strong>' . $nextShippingDateFormatted . '</strong>');
        echo '<br>';
        echo __('Please choose your preferred time for shipping below', 'coopcycle');
        echo '</p>';

        echo '<time id="order_shipping_date_opens" datetime="' . $opens->format('Y-m-d H:i:s') . '"></time>';
        echo '<time id="order_shipping_date_closes" datetime="' . $closes->format('Y-m-d H:i:s') . '"></time>';

        woocommerce_form_field('order_shipping_time', array(
            'type'          => 'text',
            'required'      => true,
            'class'         => array('form-row-wide'),
            'label'         => __('Shipping time'),
        ), $checkout->get_value('order_shipping_time'));

        echo '</div>';
    }

    function coopcycle_checkout_process() {
        // TODO Make sure shipping time is valid
        if (!$_POST['order_shipping_date'] || !$_POST['order_shipping_time']) {
            wc_add_notice(__('Please choose a shipping date.', 'coopcycle'), 'error');
        }
    }

    function coopcycle_checkout_update_order_meta($order_id) {
        if (!empty($_POST['order_shipping_date'])) {
            update_post_meta($order_id, 'order_shipping_date', sanitize_text_field($_POST['order_shipping_date']));
        }
        if (!empty($_POST['order_shipping_time'])) {
            update_post_meta($order_id, 'order_shipping_time', sanitize_text_field($_POST['order_shipping_time']));
        }
    }

    // woocommerce_before_order_notes
    // woocommerce_after_order_notes
    add_action('woocommerce_before_order_notes', 'coopcycle_custom_checkout_field');
    add_action('woocommerce_checkout_process', 'coopcycle_checkout_process');
    add_action('woocommerce_checkout_update_order_meta', 'coopcycle_checkout_update_order_meta');

    function coopcycle_enqueue_scripts() {

        wp_register_style('rome', 'https://cdnjs.cloudflare.com/ajax/libs/rome/2.1.22/rome.min.css');
        wp_register_script('rome', 'https://cdnjs.cloudflare.com/ajax/libs/rome/2.1.22/rome.min.js');

        wp_register_script('coopcycle', plugins_url('/js/coopcycle.js', __FILE__), array(), false, true);

        wp_enqueue_style('rome');
        wp_enqueue_script('rome');
        wp_enqueue_script('coopcycle');
    }

    add_action('wp_enqueue_scripts', 'coopcycle_enqueue_scripts');

    function coopcycle_woocommerce_order_status_changed($order_id, $old_status, $new_status) {

        if ('processing' === $new_status) {

            $order = wc_get_order($order_id);

            $order_shipping_date = $order->get_meta('order_shipping_date', true);
            $order_shipping_time = $order->get_meta('order_shipping_time', true);

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
                    'doneBefore' => sprintf('%s %s', $order_shipping_date, $order_shipping_time),
                )
            );

            $httpClient = new CoopCycle_HttpClient();
            $delivery = $httpClient->post('/api/deliveries', $data);

            // Save task id in order meta
            $order->update_meta_data('task_id', $delivery['dropoff']['id']);
            $order->save();
        }
    }

    add_action('woocommerce_order_status_changed', 'coopcycle_woocommerce_order_status_changed', 10, 3);
}
