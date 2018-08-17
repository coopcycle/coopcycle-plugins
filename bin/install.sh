#!/bin/bash

cd "$(dirname "$0")/.."

set +e

docker-compose run wp plugin is-installed woocommerce
WOOCOMMERCE_INSTALLED=$?

docker-compose run wp theme is-installed storefront
STOREFRONT_THEME_INSTALLED=$?

docker-compose run wp plugin is-installed wordpress-importer
WORDPRESS_IMPORTER_INSTALLED=$?

set -e

if [ $WOOCOMMERCE_INSTALLED != 0 ]
then
    docker-compose run wp plugin install woocommerce --activate
fi

if [ $STOREFRONT_THEME_INSTALLED != 0 ]
then
    docker-compose run wp theme install storefront --activate
fi

if [ $WORDPRESS_IMPORTER_INSTALLED != 0 ]
then
    docker-compose run wp plugin install wordpress-importer --activate
fi
