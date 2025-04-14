<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define('COOPCYCLE_VERSION', '0.1.0');

/**
 * Class for integrating with WooCommerce Blocks
 */
class Coopcycle_Blocks_Integration implements IntegrationInterface {

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return 'shipping-date-picker';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_main_integration();
    }

    /**
     * Registers the main JS file required to add filters and Slot/Fills.
     */
    public function register_main_integration() {
        $script_path = '/build/shipping-date-picker/index.js';
        $style_path  = '/build/shipping-date-picker/style-index.css';

        $script_url = plugins_url( $script_path, __FILE__ );
        $style_url  = plugins_url( $style_path, __FILE__ );

        $script_asset_path = dirname( __FILE__ ) . '/build/shipping-date-picker/index.asset.php';
        $script_asset      = file_exists( $script_asset_path )
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version'      => $this->get_file_version( $script_path ),
            );

        wp_enqueue_style(
            'shipping-date-picker-blocks-integration',
            $style_url,
            [],
            $this->get_file_version( $style_path )
        );
        wp_register_script(
            'shipping-date-picker-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        wp_set_script_translations(
            'shipping-date-picker-blocks-integration',
            'shipping-date-picker',
            dirname( __FILE__ ) . '/i18n/languages'
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array(
            'shipping-date-picker-blocks-integration',
            'shipping-date-picker-block-frontend'
        );
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array();
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data() {

        $data = array(
            'shippingMethods' => CoopCycle::get_accepted_shipping_methods(),
        );

        return $data;

    }

    public function register_block_frontend_scripts() {
        $script_path       = '/build/shipping-date-picker-block-frontend.js';
        $script_url        = plugins_url( $script_path, __FILE__ );
        $script_asset_path = dirname( __FILE__ ) . '/build/shipping-date-picker-block-frontend.asset.php';
        $script_asset      = file_exists( $script_asset_path )
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version'      => $this->get_file_version( $script_asset_path ),
            );

        wp_register_script(
            'shipping-date-picker-block-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        wp_set_script_translations(
            'shipping-date-picker-block-frontend',
            'shipping-date-picker',
            dirname( __FILE__ ) . '/i18n/languages'
        );
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode.
     *
     * @param string $file Local path to the file.
     * @return string The cache buster value to use for the given file.
     */
    protected function get_file_version( $file ) {
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
            return filemtime( $file );
        }
        return COOPCYCLE_VERSION;
    }
}
