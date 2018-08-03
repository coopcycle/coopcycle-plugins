release:
	@mkdir -p dist/
	@rm -f dist/coopcycle-$(VERSION).zip
	@git archive -o dist/coopcycle-$(VERSION).zip HEAD
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"
