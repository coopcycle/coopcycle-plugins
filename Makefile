release-wordpress:
	@mkdir -p dist/
	@rm -f dist/coopcycle-$(VERSION).zip
	@cd wordpress && git archive -o ../dist/coopcycle-$(VERSION).zip HEAD && cd ..
	@printf "\e[0;32mCreated release $(VERSION)\e[0m\n"
