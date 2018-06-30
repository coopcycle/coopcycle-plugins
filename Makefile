release:
	@mkdir -p dist/
	@rm -f dist/coopcycle-$(VERSION).zip
	@zip -r dist/coopcycle-$(VERSION).zip -j -q wp-content/plugins/coopcycle
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"
