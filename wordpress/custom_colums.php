<?php

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
