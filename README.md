CoopCycle Plugins
-----------------

Running this project supposes that you have the [coopcycle-web](https://github.com/coopcycle/coopcycle-web) project running. If you don't you will need to create a Docker network manually.

```
docker network create coopcycle-web_default
```

After this you will be able to execute the following commands.

### PrestaShop

```
docker-compose -f docker-compose-prestashop.yml up
```

Then go to [localhost:8082](http://localhost:8082)

#### Creating a release

```
make release-prestashop VERSION=<version>
```

### Wordpress

```
docker-compose -f docker-compose-wordpress.yml up
```

Then go to [localhost:8083](http://localhost:8083)

You can use the following credentials to [login as an administrator](http://localhost:8083/wp-admin):

- Username: **admin**
- Password: **admin**

#### Creating a release

```
make release-wordpress VERSION=<version>
```
