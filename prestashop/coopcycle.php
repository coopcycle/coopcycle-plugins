<?php

if (!defined('_PS_VERSION_'))
  exit;

/**
 * @see https://belvg.com/blog/how-to-create-shipping-module-for-prestashop.html
 * @see http://doc.prestashop.com/pages/viewpage.action?pageId=51184686
 * @see http://doc.prestashop.com/pages/viewpage.action?pageId=15171738
 * @see https://stackoverflow.com/questions/28408612/prestashop-change-order-status-when-payment-is-validated
 */
class Coopcycle extends CarrierModule
{
    const CONFIG_PREFIX = 'COOPCYCLE_';

    public function __construct()
    {
        $this->name = 'coopcycle';
        $this->tab = 'shipping_logistics';
        $this->version = '0.1.0';
        $this->author = 'CoopCycle Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CoopCycle');
        $this->description = $this->l('Description of my module.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('updateCarrier')) {
            return false;
        }

        if (!$this->registerHook('displayCarrierExtraContent')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        if (!$this->unregisterHook('updateCarrier')) {
            return false;
        }

        if (!$this->unregisterHook('displayCarrierExtraContent')) {
            return false;
        }

        // TODO Delete carrier

        return true;
    }

    protected function createCarrier()
    {
        $id = Configuration::get(self::CONFIG_PREFIX . 'CARRIER_ID');

        if ($id) {
            return new Carrier($id, $this->context->language->id);
        }

        $settings = $this->httpRequest('GET', '/api/settings', array(
            'headers' => array(
                'Accept: application/json',
                'Content-Type: application/json',
            ),
        ));

        $brandName = $settings['brand_name'];

        $carrier = new Carrier();
        $carrier->name = $brandName;
        $carrier->active = true;
        $carrier->shipping_handling = false;
        $carrier->need_range = true;
        $carrier->range_behavior = 0;
        $carrier->shipping_external = true;
        $carrier->is_module = true;
        $carrier->external_module_name = $this->name;
        $carrier->delay = array(
            Language::getIdByIso('en') =>
                $this->trans('Delivery by bike with %brand_name%', array('%brand_name%' => $brandName), 'Modules.CoopCycle.Admin'),
            Language::getIdByIso('fr') =>
                $this->trans('Livraison en vélo avec %brand_name%', array('%brand_name%' => $brandName), 'Modules.CoopCycle.Admin'),
        );

        if (!$carrier->add()) {
            return false;
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'CARRIER_ID', $carrier->id);

        return $carrier;
    }

    private function accessToken()
    {
        // TODO Check if API_KEY & API_SECRET is set
        $fieldsValue = $this->getFieldsValue();

        $apiKey = $fieldsValue['COOPCYCLE_API_KEY'];
        $apiSecret = $fieldsValue['COOPCYCLE_API_SECRET'];

        $authorization = base64_encode(sprintf('%s:%s', $apiKey, $apiSecret));

        $response = $this->httpRequest('POST', '/oauth2/token', array(
            'body' => array(
                'grant_type' => 'client_credentials',
                'scope' => 'deliveries',
            ),
            'headers' => array(
                sprintf('Authorization: Basic %s', $authorization),
            ),
        ));

        if ($response) {
            return $response['access_token'];
        }

        return false;
    }

    private function httpRequest($method, $uri, $config = array())
    {
        if (0 === strpos($uri, 'http')) {
            $url = $uri;
        } else {
            // TODO Check if BASE_URL is set
            $fieldsValue = $this->getFieldsValue();
            $baseURL = $fieldsValue['COOPCYCLE_BASE_URL'];
            $uri = sprintf('/%s', ltrim($uri, '/'));
            $url = sprintf('%s%s', $baseURL, $uri);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : array();
        $headers = array_unique($headers);
        $headers = array_values($headers);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ('POST' === strtoupper($method)) {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        if (isset($config['body']) && is_array($config['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($config['body']));
        }

        $res = curl_exec($ch);

        $httpCode = !curl_errno($ch) ? curl_getinfo($ch, CURLINFO_HTTP_CODE) : null;

        if ($httpCode !== 200) {
            curl_close($ch);

            return false;
        }

        curl_close($ch);

        return json_decode($res, true);
    }

    public function getContent()
    {
        return $this->postProcess() . $this->renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCoopCycleConfig')) {

            if (!$baseURL = Tools::getValue('COOPCYCLE_BASE_URL')) {
                return $this->displayError($this->trans('URL of CoopCycle server is required', [], 'Modules.CoopCycle.Admin'));
            }

            if (!$apiKey = Tools::getValue('COOPCYCLE_API_KEY')) {
                return $this->displayError($this->trans('API key is required', [], 'Modules.CoopCycle.Admin'));
            }

            if (!$apiSecret = Tools::getValue('COOPCYCLE_API_SECRET')) {
                return $this->displayError($this->trans('API secret is required', [], 'Modules.CoopCycle.Admin'));
            }

            $baseURL = trim($baseURL, '/ ');

            $res = $this->httpRequest('GET', sprintf('%s/api', $baseURL), array(
                'headers' => array(
                    'Content-Type: application/json',
                ),
            ));

            $isEntrypointValid = isset($res['@context']) && $res['@context'] === '/api/contexts/Entrypoint';

            if (!$isEntrypointValid) {
                return $this->displayError($this->trans('Server with URL "%url%" is not compatible', [
                    '%url%' => $baseURL,
                ], 'Modules.CoopCycle.Admin'));
            }

            if (!$accessToken = $this->accessToken()) {
                return $this->displayError($this->trans('Credentials are not valid', [], 'Modules.CoopCycle.Admin'));
            }

            Configuration::updateValue(self::CONFIG_PREFIX . 'BASE_URL', $baseURL);
            Configuration::updateValue(self::CONFIG_PREFIX . 'API_KEY', $apiKey);
            Configuration::updateValue(self::CONFIG_PREFIX . 'API_SECRET', $apiSecret);

            $this->createCarrier();

            return Tools::redirectAdmin(AdminController::$currentIndex
                . '&configure=' . $this->name
                . '&token=' . Tools::getAdminTokenLite('AdminModules'));
        }

        return '';
    }

    protected function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'id_form' => 'step_carrier_general',
                'input' => array(),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right submit_dash_config',
                    'reset' => array(
                        'title' => $this->trans('Cancel', array(), 'Admin.Actions'),
                        'class' => 'btn btn-default cancel_dash_config',
                    )
                )
            ),
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->trans('URL of CoopCycle server', array(), 'Modules.CoopCycle.Admin'),
            'name' => 'COOPCYCLE_BASE_URL',
            'type' => 'text',
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->trans('API key', array(), 'Modules.CoopCycle.Admin'),
            'name' => 'COOPCYCLE_API_KEY',
            'type' => 'text',
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->trans('API secret', array(), 'Modules.CoopCycle.Admin'),
            'name' => 'COOPCYCLE_API_SECRET',
            'type' => 'text',
        );

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCoopCycleConfig';
        $helper->tpl_vars = array(
            'fields_value' => $this->getFieldsValue(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    protected function getFieldsValue()
    {
        return array(
            'COOPCYCLE_BASE_URL' => Tools::getValue('COOPCYCLE_BASE_URL', Configuration::get(self::CONFIG_PREFIX . 'BASE_URL')),
            'COOPCYCLE_API_KEY' => Tools::getValue('COOPCYCLE_API_KEY', Configuration::get(self::CONFIG_PREFIX . 'API_KEY')),
            'COOPCYCLE_API_SECRET' => Tools::getValue('COOPCYCLE_API_SECRET', Configuration::get(self::CONFIG_PREFIX . 'API_SECRET')),
        );
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return 7.5;
    }

    public function getOrderShippingCostExternal($params)
    {
        return 10;
    }

    private static function countNumberOfDays(array $ranges)
    {
        $iso_days = array_map(function (\DatePeriod $range) {
            return $range->getStartDate()->format('Y-m-d');
        }, $ranges);

        $iso_days = array_values(array_unique($iso_days));

        return count($iso_days);
    }

    public static function timeSlotToDatePeriods($time_slot, \DateTime $now = null)
    {
        if (null === $now) {
            $now = new \DateTime();
        }

        $number_of_days = 0;
        $expected_number_of_days = 2;

        $cursor = clone $now;

        $ranges = array();
        while ($number_of_days < $expected_number_of_days) {

            foreach ($time_slot['openingHoursSpecification'] as $ohs) {

                if (!in_array($cursor->format('l'), $ohs['dayOfWeek'])) {
                    continue;
                }

                $pattern = '/^([0-9]+):([0-9]+):?([0-9]+)?/';

                $opens = clone $cursor;
                $closes = clone $cursor;

                preg_match($pattern, $ohs['opens'], $matches);
                $opens->setTime($matches[1], $matches[2]);

                preg_match($pattern, $ohs['closes'], $matches);
                $closes->setTime($matches[1], $matches[2]);

                $range = new \DatePeriod($opens, $closes->diff($opens), $closes);

                if ($range->getStartDate() > $now) {
                    $ranges[] = $range;
                }
            }

            $cursor->modify('+1 day');

            $number_of_days = self::countNumberOfDays($ranges);
        }

        uasort($ranges, function (\DatePeriod $a, \DatePeriod $b) {
            if ($a->getStartDate() === $b->getStartDate()) return 0;
            return $a->getStartDate() < $b->getStartDate() ? -1 : 1;
        });

        return $ranges;
    }

    public function hookActionCarrierUpdate($params)
    {
        if ((int) $params['id_carrier'] === (int) Configuration::get(self::CONFIG_PREFIX . 'CARRIER_ID')) {
            Configuration::updateValue(self::CONFIG_PREFIX . 'CARRIER_ID', $params['carrier']->id);
        }
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if (!$accessToken = $this->accessToken()) {
            return;
        }

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            sprintf('Authorization: Bearer %s', $accessToken),
        );

        $me = $this->httpRequest('GET', '/api/me', array(
            'headers' => $headers,
        ));

        if ($me) {
            $store = $this->httpRequest('GET', $me['store'], array(
                'headers' => $headers,
            ));
            if ($store) {
                $time_slot = $this->httpRequest('GET', $store['timeSlot'], array(
                    'headers' => $headers,
                ));
                if ($time_slot) {

                    $date_periods = self::timeSlotToDatePeriods($time_slot);

                    $options = array();
                    foreach ($date_periods as $date_period) {
                        $value = sprintf('%s %s-%s',
                            $date_period->getStartDate()->format('Y-m-d'),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $label = sprintf('%s between %s and %s',
                            $date_period->getStartDate()->format('Y-m-d'),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $options[$value] = $label;
                    }

                    $output = '<select name="coopcycle_time_slot">';
                    foreach ($options as $value => $label) {
                        $output .= sprintf('<option value="%s">%s</option>', $value, $label);
                    }
                    $output .= '<select>';

                    return $output;
                }
            }
        }

        return '';
    }

    /**
     * @see https://www.prestashop.com/forums/topic/241470-adding-a-custom-field-during-the-checkout-process/
     * @see https://www.prestashop.com/forums/topic/319917-how-to-add-custom-fields-in-checkout/
     */
    public function hookActionCarrierProcess($params)
    {
        $cart = $params['cart'];

        if (!($cart instanceof Cart)) {
            return;
        }

        if (!Tools::isSubmit('confirmDeliveryOption')) {
            return;
        }

        if (!$time_slot = Tools::getValue('coopcycle_time_slot')) {
            return
        }

        // TODO Store time slot in cart
    }

    /**
     * array(
     *   'cart' => $this->context->cart,
     *   'order' => $order,
     *   'customer' => $this->context->customer,
     *   'currency' => $this->context->currency,
     *   'orderStatus' => $order_status,
     * )
     */
    // public function hookActionValidateOrder($params)
    // {
    // }

    /**
     * @see OrderHistory::changeIdOrderState()
     * @param array $params array(
     *  'newOrderStatus' => (object) OrderState,
     *  'id_order' => (int) Order ID
     * )
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if ($params['newOrderStatus'] === Configuration::get('PS_OS_PAYMENT')) {
            $order = new Order((int) $params['id_order'], $this->context->language->id);
            if (Validate::isLoadedObject($order)) {
                if (!$accessToken = $this->accessToken()) {
                    return;
                }

                $address = new Address($order->id_address_delivery, $this->context->language->id);

                $street_address = implode(' ', array(
                    $address->address1,
                    $address->address2,
                    $address->postcode,
                    $address->city,
                ));

                $payload = array(
                    'dropoff' => array(
                        'address' => array(
                            'streetAddress' => $street_address,
                            'description' => $address->other,
                            // 'telephone' => $address->phone_mobile,
                            // 'contactName' => $contact_name,
                        ),
                        // 'timeSlot' => $shipping_date,
                        // 'comments' => $order->get_customer_note(),
                    )
                );

                $delivery = $this->httpRequest('POST', '/api/deliveries', array(
                    'headers' => array(
                        sprintf('Authorization: Bearer %s', $accessToken),
                        'Accept: application/json',
                        'Content-Type: application/json',
                    ),
                ));
            }
        }
    }
}
