<?php

/**
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/abstracts/abstract-wc-shipping-method.php
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/shipping/flat-rate/class-wc-shipping-flat-rate.php
 */
if (!class_exists('CoopCycle_ShippingMethod')) {

    class CoopCycle_ShippingMethod extends WC_Shipping_Method {

        public function __construct($instance_id = 0)
        {
            parent::__construct($instance_id);

            $this->id = 'coopcycle_shipping_method';
            $this->title = __('CoopCycle', 'coopcycle');
            $this->method_title = __('CoopCycle', 'coopcycle');
            $this->method_description = __('Allow customers to get delivered by a local coop running CoopCycle', 'coopcycle');

            // - shipping-zones Shipping zone functionality + instances
            // - instance-settings Instance settings screens.
            // - settings Non-instance settings screens. Enabled by default for BW compatibility with methods before instances existed.
            // - instance-settings-modal Allows the instance settings to be loaded within a modal in the zones UI.
            $this->supports = array_merge(array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            ), $this->supports);

            // TODO The plugin should be enabled only if it has been configured
            $this->enabled = "yes";

            $this->init();
        }

        public function init_form_fields()
        {
            // $this->form_fields = array(
            //     'base_url' => array(
            //         'title' => __('Base URL', 'coopcycle'),
            //         'type' => 'text',
            //         'description' => __('The URL of the CoopCycle instance.', 'coopcycle'),
            //         'default' => ''
            //     ),
            //     'token' => array(
            //         'title' => __('Token', 'coopcycle'),
            //         'type' => 'password',
            //         'description' => __('The token to communicate with CoopCycle API.', 'coopcycle'),
            //         'default' => ''
            //     ),
            // );

            $this->instance_form_fields = array(
                'title' => array(
                    'title' => __( 'Title', 'coopcycle' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __('CoopCycle', 'coopcycle'),
                    'desc_tip' => true,
                ),
            );
        }

        protected function init()
        {
            $this->init_form_fields();
            // $this->init_settings();

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function calculate_shipping($package = array())
        {
            // country
            // state
            // postcode
            // city
            // address
            // address_2
            $destination = $package['destination'];

            if (!is_array($destination)) {
                return;
            }

            $params = [
                'dropoffAddress' => $destination['address'],
            ];

            $base_url = get_option('coopcycle_base_url');
            $api_token = get_option('coopcycle_api_token');

            $endpoint_url = $base_url . '/api/pricing/calculate-price?' . http_build_query($params);
            $response = wp_remote_get($endpoint_url, array(
                'timeout' => 30,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Token' => $api_token
                )
            ));

            if (is_array($response)) {

                $response_code = wp_remote_retrieve_response_code($response);
                $cost = json_decode(wp_remote_retrieve_body($response), true);

                $rate = array(
                    'id' => $this->get_rate_id(),
                    'label' => $this->title,
                    'cost' => number_format($cost / 100, 2),
                    'package' => $package,
                    // 'taxes' => '???',
                    'calc_tax' => 'per_order'
                );

                $this->add_rate($rate);
            }
        }
    }
}
