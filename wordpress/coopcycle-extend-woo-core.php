<?php

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

class Coopcycle_Extend_Woo_Core
{
    /**
     * Plugin Identifier, unique to each plugin.
     *
     * @var string
     */
    private $name = 'shipping-date-picker';

    /**
     * Bootstraps the class and hooks required data.
     */
    public function init()
    {
        $this->save_shipping_date();
        $this->show_shipping_date_in_order();
        $this->show_shipping_date_in_order_confirmation();
        $this->show_shipping_date_in_order_email();
    }

    /**
     * Saves the shipping date/time to the order's metadata.
     *
     * @return void
     */
    private function save_shipping_date()
    {
        add_action(
            'woocommerce_store_api_checkout_update_order_from_request',
            function (\WC_Order $order, \WP_REST_Request $request) {

                $request_data = $request['extensions'][$this->name];
                $shipping_date = $request_data['shipping_date'];

                $order->update_meta_data('shipping_date', $shipping_date);

                $order->save();
            },
            10,
            2
        );
    }

    /**
     * Adds the shipping date and time in the order page in WordPress admin.
     */
    private function show_shipping_date_in_order()
    {
        add_action(
            'woocommerce_admin_order_data_after_shipping_address',
            function (\WC_Order $order) {
                if ($order->meta_exists('shipping_date')) {

                    $shipping_date = $order->get_meta('shipping_date');

                    echo sprintf(
                        '<div class="address">
                            <p><strong>%s</strong><span>%s</span></p>
                        </div>',
                        esc_html__('Shipping date', $this->name),
                        esc_html($shipping_date)
                    );
                }
            }
        );
    }

    /**
     * Adds the pickup date and time on the order confirmation page.
     */
    private function show_shipping_date_in_order_confirmation()
    {
        add_action(
            'woocommerce_order_details_after_customer_address',
            function ($type, $order) {
                if ($type === 'shipping') {
                    if ($order->meta_exists('shipping_date')) {

                        $shipping_date = $order->get_meta('shipping_date');

                        printf(
                            "<strong>%s</strong>
                            <span>%s</span>",
                            esc_html__('Shipping date', $this->name),
                            esc_html($shipping_date)
                        );
                    }
                }
            },
            priority: 9,
            accepted_args: 2
        );
    }

    /**
     * Adds the shipping date and time on the order confirmation email.
     */
    private function show_shipping_date_in_order_email()
    {
        add_action(
            'woocommerce_email_after_order_table',
            function ($order, $sent_to_admin, $plain_text, $email) {
                if ($order->meta_exists('shipping_date')) {
                    $shipping_date = $order->get_meta('shipping_date');

                    printf(
                        "<h2>%s</h2>
                        <p>%s</p>",
                        esc_html__('Shipping date', $this->name),
                        esc_html($shipping_date)
                    );
                }
            },
            priority: 10,
            accepted_args: 4
        );
    }
}
