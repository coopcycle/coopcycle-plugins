<?php

if (!defined('_PS_VERSION_'))
  exit;

require_once dirname(__FILE__).'/classes/CartTimeSlot.php';
require_once dirname(__FILE__).'/classes/OrderTracking.php';

/**
 * @see https://belvg.com/blog/how-to-create-shipping-module-for-prestashop.html
 * @see http://doc.prestashop.com/pages/viewpage.action?pageId=51184686
 * @see http://doc.prestashop.com/pages/viewpage.action?pageId=15171738
 * @see https://stackoverflow.com/questions/28408612/prestashop-change-order-status-when-payment-is-validated
 * @see https://github.com/PrestaEdit/Canvas-Module-Prestashop-15
 * @see https://github.com/quadra-informatique/Colissimo_Simplicite-PrestaShop/
 */
class Coopcycle extends CarrierModule
{
    const CONFIG_PREFIX = 'COOPCYCLE_';

    public function __construct()
    {
        $this->name = 'coopcycle';
        $this->tab = 'shipping_logistics';
        $this->version = '0.3.1';
        $this->author = 'CoopCycle Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CoopCycle');
        $this->description = $this->l('Allow customers to get delivered by a local coop running CoopCycle');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->installDb()) {
            return false;
        }

        if (!$this->registerHook('actionCarrierUpdate')) {
            return false;
        }

        if (!$this->registerHook('actionCarrierProcess')) {
            return false;
        }

        // PS 1.7+
        if (!$this->registerHook('displayCarrierExtraContent')) {
            return false;
        }

        // PS 1.6
        if (!$this->registerHook('displayCarrierList')) {
            return false;
        }

        if (!$this->registerHook('actionValidateOrder')) {
            return false;
        }

        if (!$this->registerHook('actionOrderStatusPostUpdate')) {
            return false;
        }

        return true;
    }

    public function installDb()
    {
        $sql = include dirname(__FILE__).'/sql_install.php';
        foreach ($sql as $s) {
            if (!Db::getInstance()->execute($s)) {
                return false;
            }
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        if (!$this->unregisterHook('actionCarrierUpdate')) {
            return false;
        }

        if (!$this->unregisterHook('actionCarrierProcess')) {
            return false;
        }

        if (!$this->unregisterHook('displayCarrierExtraContent')) {
            return false;
        }

        if (!$this->unregisterHook('displayCarrierList')) {
            return false;
        }

        if (!$this->unregisterHook('actionValidateOrder')) {
            return false;
        }

        if (!$this->unregisterHook('actionOrderStatusPostUpdate')) {
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

        // @see https://github.com/PrestaShop/PrestaShop/blob/9051227101b198586a2f88597e03b07dffc0bc39/classes/Cart.php#L3646

        $carrier = new Carrier();
        $carrier->name = $brandName;
        $carrier->active = true;
        $carrier->shipping_handling = false;
        $carrier->need_range = true;
        $carrier->range_behavior = 0;
        // We don't manage the shipping costs calculation via the API
        // Instead, the shop owner has to configure shipping costs
        $carrier->shipping_external = false;
        $carrier->is_module = true;
        $carrier->external_module_name = $this->name;
        $carrier->delay = array(
            Language::getIdByIso('en') =>
                sprintf('Delivery by bike with %s', $brandName),
            Language::getIdByIso('fr') =>
                sprintf('Livraison en vélo avec %s', $brandName),
        );

        if (!$carrier->add()) {
            return false;
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'CARRIER_ID', $carrier->id);

        return $carrier;
    }

    /**
     * @return string
     */
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
        if (isset($config['body_json']) && is_array($config['body_json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config['body_json']));
        }

        $res = curl_exec($ch);

        $httpCode = !curl_errno($ch) ? curl_getinfo($ch, CURLINFO_HTTP_CODE) : null;

        if (!in_array($httpCode, [200, 201, 204])) {
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
                return $this->displayError($this->l('URL of CoopCycle server is required'));
            }

            if (!$apiKey = Tools::getValue('COOPCYCLE_API_KEY')) {
                return $this->displayError($this->l('API key is required'));
            }

            if (!$apiSecret = Tools::getValue('COOPCYCLE_API_SECRET')) {
                return $this->displayError($this->l('API secret is required'));
            }

            $baseURL = trim($baseURL, '/ ');

            $res = $this->httpRequest('GET', sprintf('%s/api', $baseURL), array(
                'headers' => array(
                    'Content-Type: application/json',
                ),
            ));

            $isEntrypointValid = isset($res['@context']) && $res['@context'] === '/api/contexts/Entrypoint';

            if (!$isEntrypointValid) {
                return $this->displayError(
                    sprintf($this->l('Server with URL "%s" is not compatible'), $baseURL)
                );
            }

            if (!$accessToken = $this->accessToken()) {
                return $this->displayError($this->l('Credentials are not valid'));
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
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right submit_dash_config',
                    'reset' => array(
                        'title' => $this->l('Cancel'),
                        'class' => 'btn btn-default cancel_dash_config',
                    )
                )
            ),
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->l('URL of CoopCycle server'),
            'name' => 'COOPCYCLE_BASE_URL',
            'type' => 'text',
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->l('API key'),
            'name' => 'COOPCYCLE_API_KEY',
            'type' => 'text',
        );

        $fields_form['form']['input'][] = array(
            'label' => $this->l('API secret'),
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

    /**
     * @see https://github.com/PrestaShop/PrestaShop/blob/9051227101b198586a2f88597e03b07dffc0bc39/classes/Cart.php#L3646
     */
    public function getOrderShippingCost($cart, $shipping_cost)
    {
        // We don't modify the shipping cost
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($cart)
    {
        // Not implemented
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

    public function hookDisplayCarrierList($params)
    {
        if ($params['cart']->id_carrier === (int) Configuration::get(self::CONFIG_PREFIX . 'CARRIER_ID')) {
            return $this->hookDisplayCarrierExtraContent($params);
        }

        return '';
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if (!$accessToken = $this->accessToken()) {
            return;
        }

        $cart_time_slot = new CoopCycleCartTimeSlot($this->context->cart->id);
        $current_value = null;
        if (Validate::isLoadedObject($cart_time_slot)) {
            $current_value = $cart_time_slot->time_slot;
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

                    $fmt = datefmt_create(
                        $this->context->language->iso_code,
                        null,
                        null,
                        null,
                        null,
                        'EEEE d MMMM'
                    );

                    $options = array();
                    foreach ($date_periods as $date_period) {
                        $value = sprintf('%s %s-%s',
                            $date_period->getStartDate()->format('Y-m-d'),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $label = sprintf($this->l('%s between %s and %s'),
                            datefmt_format($fmt, $date_period->getStartDate()->getTimestamp()),
                            $date_period->getStartDate()->format('H:i'),
                            $date_period->getEndDate()->format('H:i')
                        );
                        $options[$value] = $label;
                    }

                    $output = '';

                    $output .= '<div class="col-sm-12">';
                    $output .= '<div class="form-group">';
                    $output .= '<label class="form-control-label">'.$this->l('Choose a time slot for delivery').'</label>';
                    $output .= '<select name="coopcycle_time_slot" class="form-control form-control-select">';
                    foreach ($options as $value => $label) {
                        $selected = '';
                        if ($current_value && $value === $current_value) {
                            $selected = ' selected';
                        }
                        $output .= sprintf('<option value="%s"%s>%s</option>', $value, $selected, $label);
                    }
                    $output .= '</div>';
                    $output .= '</select>';
                    $output .= '</div>';

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

        // if (!Tools::isSubmit('confirmDeliveryOption')) {
        //     return;
        // }

        if ($params['cart']->id_carrier !== (int) Configuration::get(self::CONFIG_PREFIX . 'CARRIER_ID')) {
            return;
        }

        if (!$time_slot = Tools::getValue('coopcycle_time_slot')) {
            // FIXME
            // If no time slot was selected, trigger an error
            return;
        }

        PrestaShopLogger::addLog(
            sprintf('CoopCycle::hookActionCarrierProcess - time slot selected = "%s"', $time_slot),
            1, null, 'Cart', (int) $cart->id, true);

        $cart_time_slot = new CoopCycleCartTimeSlot($cart->id);
        $cart_time_slot->time_slot = $time_slot;

        $cart_time_slot->save();
    }

    /**
     * @param $params array(
     *   'cart' => $this->context->cart,
     *   'order' => $order,
     *   'customer' => $this->context->customer,
     *   'currency' => $this->context->currency,
     *   'orderStatus' => $order_status,
     * )
     */
    public function hookActionValidateOrder($params)
    {
        if ((int) $params['orderStatus']->id === (int) Configuration::get('PS_OS_PAYMENT')) {
            $this->createDeliveryFromOrder($params['order']);
        }
    }

    /**
     * @see OrderHistory::changeIdOrderState()
     * @param array $params array(
     *  'newOrderStatus' => (object) OrderState,
     *  'id_order' => (int) Order ID
     * )
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if ((int) $params['newOrderStatus']->id === (int) Configuration::get('PS_OS_PAYMENT')) {
            $order = new Order((int) $params['id_order'], $this->context->language->id);
            $this->createDeliveryFromOrder($order);
        }
    }

    private function createDeliveryFromOrder(Order $order)
    {
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        PrestaShopLogger::addLog(
            'CoopCycle::createDeliveryFromOrder',
            1, null, 'Order', (int) $order->id, true);

        // Make sure we don't create the delivery twice
        if (CoopCycleOrderTracking::existsFor($order)) {
            PrestaShopLogger::addLog(
                'CoopCycle::createDeliveryFromOrder - delivery already exists, skipping',
                1, null, 'Order', (int) $order->id, true);
            return;
        }

        if ((int) $order->id_carrier !== (int) Configuration::get(self::CONFIG_PREFIX . 'CARRIER_ID')) {
            PrestaShopLogger::addLog(
                'CoopCycle::createDeliveryFromOrder - carrier does not match',
                1, null, 'Order', (int) $order->id, true);
            return;
        }

        $cart_time_slot = new CoopCycleCartTimeSlot($order->id_cart);

        if (!Validate::isLoadedObject($cart_time_slot)) {
            PrestaShopLogger::addLog(
                'CoopCycle::createDeliveryFromOrder - cart does not have a time slot',
                1, null, 'Order', (int) $order->id, true);
            return;
        }

        if (!$accessToken = $this->accessToken()) {
            return;
        }

        $address = new Address((int) $order->id_address_delivery, $this->context->language->id);

        $payload = array(
            'dropoff' => array(
                'address' => array(
                    'streetAddress' => $this->stringifyAddress($address),
                    'description'   => $address->other,
                    'contactName'   => trim(implode(' ', array($address->firstname, $address->lastname))),
                ),
                'timeSlot' => $cart_time_slot->time_slot,
            )
        );

        if ($address->phone || $address->phone_mobile) {
            $payload['dropoff']['address']['telephone'] = $address->phone ? $address->phone : $address->phone_mobile;
        }

        $shop = new Shop((int) $order->id_shop);

        if (!Validate::isLoadedObject($shop)) {
            PrestaShopLogger::addLog(
                sprintf('CoopCycle::createDeliveryFromOrder - shop with id %d could not be loaded', $order->id_shop),
                1, null, 'Order', (int) $order->id, true);
            return;
        }

        // We use the shop address as pickup address
        $pickupAddress = $this->stringifyAddress($shop->getAddress());
        if (!empty($pickupAddress)) {
            $payload['pickup']['address'] = $pickupAddress;
        }

        // We send the order summary as text in comments
        $payload['pickup']['comments'] = $this->stringifyOrder($order);

        $delivery = $this->httpRequest('POST', '/api/deliveries', array(
            'body_json' => $payload,
            'headers' => array(
                sprintf('Authorization: Bearer %s', $accessToken),
                'Accept: application/ld+json',
                'Content-Type: application/ld+json',
            ),
        ));

        if (false === $delivery) {
            PrestaShopLogger::addLog(
                'CoopCycle::createDeliveryFromOrder - error, could not create delivery',
                5, null, 'Order', (int) $order->id, true);

            return;
        }

        $order_tracking = new CoopCycleOrderTracking($order->id);
        $order_tracking->delivery = $delivery['@id'];

        $order_tracking->save();

        PrestaShopLogger::addLog(
            'CoopCycle::createDeliveryFromOrder - success',
            1, null, 'Order', (int) $order->id, true);
    }

    private function stringifyAddress(Address $address)
    {
        $parts = [];

        if (!empty($address->address1)) {
            $parts[] = $address->address1;
        }

        if (!empty($address->city)) {
            $parts[] = trim($address->postcode . ' ' . $address->city);
        }

        if (count($parts) === 0) {

            return '';
        }

        return implode(', ', $parts);
    }

    private function stringifyOrder(Order $order)
    {
        $text = '';

        foreach ($order->getProducts() as $product) {
            $text .= sprintf("%d × %s\n", (int) $product['product_quantity'], $product['product_name']);
        }

        return $text;
    }
}
