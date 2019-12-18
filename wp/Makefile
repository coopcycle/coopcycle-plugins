install:
	@bin/install.sh

# https://docs.woocommerce.com/document/importing-woocommerce-sample-data/
import:
	@docker-compose run wp import /var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

release:
	@mkdir -p dist/
	@rm -f dist/coopcycle-$(VERSION).zip
	@git archive -o dist/coopcycle-$(VERSION).zip HEAD
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"
