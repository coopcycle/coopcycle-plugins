release-wordpress:
	@mkdir -p dist/
	@rm -f dist/coopcycle-$(VERSION).zip
	@cd wordpress && npm run build && cd ..
	@cd wordpress && git archive -o ../dist/wordpress-$(VERSION).zip HEAD && cd ..
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"

wordpress-make-pot:
	@docker compose -f docker-compose-wordpress.yml run --rm --entrypoint='wp i18n make-pot wp-content/plugins/coopcycle wp-content/plugins/coopcycle/i18n/languages/coopcycle.pot' wp

release-prestashop:
	@mkdir -p dist/
	@rm -f dist/coopcycle-prestashop-$(VERSION).zip
	@cd prestashop && git archive --prefix=coopcycle/ -o ../dist/coopcycle-prestashop-$(VERSION).zip HEAD && cd ..
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"
