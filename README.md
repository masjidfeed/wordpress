# MasjidFeed Plugin

Start the local WordPress environment and wait until it is ready:

```sh
docker-compose up -d --wait --wait-timeout 120
```

- WordPress: <http://localhost:8000>
- phpMyAdmin: <http://localhost:8080>

Stop the environment with `docker-compose down`.

## MasjidFeed App plugin

The plugin lives in `wp-content/plugins/masjid-app` and ships only runtime code; development tooling lives at the repository root.

```sh
composer install          # install build dependencies (php-scoper) and firebase/php-jwt
composer build-release    # regenerate wp-content/plugins/masjid-app/vendor-prefixed
phpunit                   # run the test suite
```
