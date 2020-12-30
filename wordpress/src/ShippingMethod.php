<?php

/**
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/abstracts/abstract-wc-shipping-method.php
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/shipping/flat-rate/class-wc-shipping-flat-rate.php
 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/shipping/flat-rate/includes/settings-flat-rate.php
 */
if (!class_exists('CoopCycle_ShippingMethod')) {

    class CoopCycle_ShippingMethod extends WC_Shipping_Flat_Rate {

        private $http_client;

        public function __construct($instance_id = 0)
        {
            // Do *NOT* call parent constructor!
            // It would call woocommerce_update_options_shipping_* with flat_rate

            $this->id                 = 'coopcycle_shipping_method';
            $this->instance_id        = absint($instance_id);
            $this->title              = __('CoopCycle', 'coopcycle');
            $this->method_title       = __('CoopCycle', 'coopcycle');
            $this->method_description = __('Allow customers to get delivered by a local coop running CoopCycle', 'coopcycle');
            $this->supports           = array(
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            );

            // TODO The plugin should be enabled only if it has been configured
            $this->enabled = "yes";

            $this->init();

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
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

        public function get_instance_form_fields()
        {
            $instance_form_fields = parent::get_instance_form_fields();

            foreach ($instance_form_fields as $key => $value) {
                if ($key === 'title') {
                    $instance_form_fields[$key]['default'] = __('CoopCycle', 'coopcycle');
                }
            }

            return $instance_form_fields;
        }

        public function get_shipping_date_options()
        {
            $http_client = CoopCycle::http_client();

            $options = array();

            try {

                $me = $http_client->get('/api/me');
                $store = $http_client->get($me['store']);

                if (isset($store['timeSlot'])) {

                    $time_slot = $http_client->get($store['timeSlot']);

                    $date_periods = CoopCycle::time_slot_to_date_periods($time_slot);
                    foreach ($date_periods as $date_period) {
                        $value = sprintf('%s %s-%s',
                            $date_period->getStartDate()->format('Y-m-d'),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        /* translators: date, start time, end time. */
                        $label = sprintf(__('%1$s between %2$s and %3$s', 'coopcycle'),
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
    }
}
