CoopCycle Plugins
-----------------

### PrestaShop

```
docker-compose -f docker-compose-prestashop.yml up
```

### Wordpress

```
docker-compose -f docker-compose-wordpress.yml up
```

Then go to [localhost:8083](http://localhost:8083)

You can use the following credentials to [login as an administrator](http://localhost:8083/wp-admin):

- Username: **admin**
- Password: **admin**

### Creating a release

```
make release-wordpress VERSION=<version>
```
