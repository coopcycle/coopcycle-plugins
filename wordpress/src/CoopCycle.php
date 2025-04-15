<?php

class CoopCycle
{
    private static $http_client;

    public static function http_client()
    {
        if (!self::$http_client) {
            self::$http_client = new CoopCycle_HttpClient();
        }

        return self::$http_client;
    }

    public static function get_accepted_shipping_methods()
    {
        $shipping_methods = [
            'coopcycle_shipping_method'
        ];

        $execute_on_free_shipping =
            filter_var(get_option('coopcycle_free_shipping'), FILTER_VALIDATE_BOOLEAN);

        if ($execute_on_free_shipping) {
            $shipping_methods[] = 'free_shipping';
        }

        return $shipping_methods;
    }

    public static function accept_shipping_method($shipping_method_id)
    {
        $accepted_shipping_methods = self::get_accepted_shipping_methods();

        return in_array($shipping_method_id, $accepted_shipping_methods);
    }

    public static function contains_accepted_shipping_method($shipping_method_ids)
    {
        foreach ($shipping_method_ids as $shipping_method_id) {
            if (self::accept_shipping_method($shipping_method_id)) {

                return true;
            }
        }

        return false;
    }

    public static function accept_order(WC_Abstract_Order $order)
    {
        $accepted_shipping_methods = self::get_accepted_shipping_methods();

        foreach ($accepted_shipping_methods as $shipping_method) {
            if ($order->has_shipping_method($shipping_method)) {

                return true;
            }
        }

        return false;
    }

    public static function get_app_name()
    {
        $http_client = self::http_client();

        $me = $http_client->get('/api/me');

        if (isset($me['@context']) && $me['@context'] === '/api/contexts/ApiApp' && isset($me['name'])) {

            return $me['name'];
        }

        return false;
    }
}
