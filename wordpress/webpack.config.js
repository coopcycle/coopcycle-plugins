const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

// Remove SASS rule from the default config so we can define our own.
const defaultRules = defaultConfig.module.rules.filter( ( rule ) => {
    return String( rule.test ) !== String( /\.(sc|sa)ss$/ );
} );

module.exports = {
    ...defaultConfig,
    entry: {
        'shipping-date-picker/index': path.resolve(process.cwd(), 'src', 'shipping-date-picker', 'index.js'),
        'shipping-date-picker-block-frontend': path.resolve(
            process.cwd(),
            'src',
            'shipping-date-picker',
            'frontend.js'
        ),
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            ( plugin ) =>
                plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new WooCommerceDependencyExtractionWebpackPlugin(),
    ],
};
