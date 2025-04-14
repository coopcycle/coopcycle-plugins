/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import './style.scss';

// https://developer.woocommerce.com/docs/cart-and-checkout-available-slots/#experimentalordershippingpackages
// https://developer.woocommerce.com/docs/cart-and-checkout-slot-and-fill/

const render = () => {};

registerPlugin('shipping-date-picker', {
	render,
	scope: 'woocommerce-checkout',
});
