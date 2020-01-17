<?php

/**
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/abstracts/abstract-wc-shipping-method.php
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/shipping/flat-rate/class-wc-shipping-flat-rate.php
 */
if (!class_exists('CoopCycle_ShippingMethod')) {

    class CoopCycle_ShippingMethod extends WC_Shipping_Method {

        private $http_client;

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

            $this->http_client = CoopCycle::http_client();
        }

        public static function instance() {

            $shipping_methods = WC()->shipping()->get_shipping_methods();

            foreach ($shipping_methods as $id => $shipping_method) {
                if ($id === 'coopcycle_shipping_method') {
                    return $shipping_method;
                }
            }

            return false;
        }

        public function init_form_fields()
        {
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

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function get_shipping_date_options()
        {
            $options = array();

            try {

                $me = $this->http_client->get('/api/me');
                $store = $this->http_client->get($me['store']);

                if (isset($store['timeSlot'])) {

                    $time_slot = $this->http_client->get($store['timeSlot']);

                    $date_periods = CoopCycle::time_slot_to_date_periods($time_slot);
                    foreach ($date_periods as $date_period) {
                        $value = sprintf('%s %s-%s',
                            $date_period->getStartDate()->format('Y-m-d'),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $label = sprintf(__('%s between %s and %s'),
                            date_i18n('l d F', $date_period->getStartDate()->getTimestamp()),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $options[$value] = $label;
                    }
                }

            } catch (\Exception $e) {
                // TODO Log error
            }

            return $options;
        }

        public function calculate_shipping($package = array())
        {
            $options = $this->get_shipping_date_options();

            // If there are no choices, skip this shipping method
            if (empty($options)) {
                return;
            }

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
                'dropoffAddress' => trim(sprintf('%s %s %s',
                    $destination['address'],
                    $destination['postcode'],
                    $destination['city']
                )),
            ];

            $uri = sprintf('/api/pricing/calculate-price?%s', http_build_query($params));

            try {

                $cost = $this->http_client->get($uri);

                if ($cost) {

                    $rate = array(
                        'id' => $this->get_rate_id(),
                        'label' => $this->get_option('title'),
                        'cost' => number_format($cost / 100, 2),
                        'package' => $package,
                        // 'taxes' => '???',
                        'calc_tax' => 'per_order'
                    );

                    $this->add_rate($rate);
                }

            } catch (HttpClientException $e) {
                // TODO Should we show a message to the user?
            }
        }
    }
}
