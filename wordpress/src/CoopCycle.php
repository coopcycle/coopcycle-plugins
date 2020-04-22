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

    private static function count_number_of_days(array $ranges)
    {
        $iso_days = array_map(function (\DatePeriod $range) {
            return $range->getStartDate()->format('Y-m-d');
        }, $ranges);

        $iso_days = array_values(array_unique($iso_days));

        return count($iso_days);
    }

    public static function time_slot_to_date_periods($time_slot, \DateTime $now = null)
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

                if (isset($time_slot['priorNotice']) && !empty($time_slot['priorNotice'])) {
                    $startWithNotice = clone $range->getStartDate();
                    $startWithNotice->modify(sprintf('-%s', $time_slot['priorNotice']));
                    if ($startWithNotice > $now) {
                        $ranges[] = $range;
                    }
                } else {
                    if ($range->getStartDate() > $now) {
                        $ranges[] = $range;
                    }
                }
            }

            $cursor->modify('+1 day');

            $number_of_days = self::count_number_of_days($ranges);
        }

        uasort($ranges, function (\DatePeriod $a, \DatePeriod $b) {
            if ($a->getStartDate() === $b->getStartDate()) return 0;
            return $a->getStartDate() < $b->getStartDate() ? -1 : 1;
        });

        return $ranges;
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
