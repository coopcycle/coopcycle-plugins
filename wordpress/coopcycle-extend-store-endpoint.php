<?php

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

/**
 * https://web-nancy.fr/how-to-create-a-custom-block-in-woocommerce-checkout-in-2024/
 */
class Coopcycle_Extend_Store_Endpoint {

    /**
     * Stores Rest Extending instance.
     *
     * @var ExtendRestApi
     */
    private static $extend;

    /**
     * Plugin Identifier, unique to each plugin.
     *
     * @var string
     */
    const IDENTIFIER = 'shipping-date-picker';

    /**
     * Bootstraps the class and hooks required data.
     *
     */
    public static function init() {
        self::$extend = StoreApi::container()->get(ExtendSchema::class);
        self::extend_store();
    }

    /**
     * Registers the actual data into each endpoint.
     */
    public static function extend_store() {
        if ( is_callable( [ self::$extend, 'register_endpoint_data' ] ) ) {
            self::$extend->register_endpoint_data(
                [
                    'endpoint'        => CheckoutSchema::IDENTIFIER,
                    'namespace'       => self::IDENTIFIER,
                    'schema_callback' => [ self::class, 'extend_checkout_schema' ],
                    'schema_type'     => ARRAY_A,
                ]
            );
        }
    }

    /**
     * Register the new field block schema into the Checkout endpoint.
     *
     * @return array Registered schema.
     *
     */
    public static function extend_checkout_schema() {
        return [
            'shipping_date'   => [
                'description' => 'A description of the field',
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
                'optional'    => true,
            ]
        ];
    }
}
