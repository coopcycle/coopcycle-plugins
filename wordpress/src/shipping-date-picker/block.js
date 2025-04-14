/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import { useSelect, useDispatch } from '@wordpress/data';
import { SelectControl, Spinner } from '@wordpress/components'
import apiFetch from '@wordpress/api-fetch';

const { shippingMethods } = getSetting('shipping-date-picker_data');

// https://github.com/woocommerce/wceu23-shipping-workshop-final
// https://web-nancy.fr/how-to-create-a-custom-block-in-woocommerce-checkout-in-2024/
// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-add-custom-fields.md

function getSelectedShippingRate(cart) {
    const shippingRates = cart.shippingRates && cart.shippingRates[0] && cart.shippingRates[0].shipping_rates;
    return shippingRates && shippingRates.find(rate => rate.selected === true);
}

const Block = ( { children, checkoutExtensionData, cart } ) => {

    const selectedShippingRate = getSelectedShippingRate(cart);

    const [ isVisible, setVisible ] = useState(false);
    const [ shippingDate, setShippingDate ] = useState(null);
    const [ options, setOptions ] = useState([]);

    const { setExtensionData } = checkoutExtensionData;

    const { setValidationErrors, clearValidationError } = useDispatch(
        'wc/store/validation'
    );

    useEffect( () => {

        const acceptShippingMethod = shippingMethods.includes(selectedShippingRate?.method_id);

        setExtensionData('shipping-date-picker', 'shipping_date', shippingDate);
        setVisible(acceptShippingMethod)

        if (acceptShippingMethod && options.length === 0) {
            apiFetch( { path: '/coopcycle/v1/shipping-date-options' } ).then((data) => {
                setOptions([ { label: 'Please select a shipping date', value: ''} ].concat(data.options))
            });
        }

        if (!shippingDate) {
            setValidationErrors( {
                'coopcycle/shipping-date-picker': {
                    message: 'You must select a shipping date',
                    hidden: false,
                },
            } );
            return;
        }

        clearValidationError( 'coopcycle/shipping-date-picker' );

    }, [
        clearValidationError,
        setValidationErrors,
        shippingDate,
        setExtensionData,
        selectedShippingRate,
    ] );

    const { validationError } = useSelect( ( select ) => {
        const store = select( 'wc/store/validation' );
        return {
            validationError: store.getValidationError( 'coopcycle/shipping-date-picker' ),
        };
    } );

    if (!isVisible) {
        return null;
    }

    if (options.length === 0) {
        return (
            <>
                <Spinner />
            </>
        );
    }

    return (
        <>
            <SelectControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label="Shipping date"
                options={ options }
                onChange={ setShippingDate }
            />

            { validationError?.hidden === false && (
                <div>
                    <span role="img" aria-label="Warning emoji">
                        ⚠️
                    </span>
                    { validationError?.message }
                </div>
            ) }
        </>
    );
};

export default Block;
