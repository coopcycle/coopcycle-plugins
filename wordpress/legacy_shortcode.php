<?php

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

function coopcycle_enqueue_scripts() {
    wp_register_style('coopcycle', plugins_url('/css/coopcycle.css', __FILE__), array(), false);
    wp_enqueue_style('coopcycle');
}

add_action('woocommerce_review_order_after_shipping', 'coopcycle_shipping_date_dropdown');

add_action('woocommerce_checkout_process', 'coopcycle_checkout_process');

add_action('woocommerce_checkout_update_order_meta', 'coopcycle_checkout_update_order_meta');

add_action('wp_enqueue_scripts', 'coopcycle_enqueue_scripts');
