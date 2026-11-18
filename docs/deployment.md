# Production images

Build both images from the same clean Git revision with `conf/Dockerfile`:

```sh
docker build --target app -f conf/Dockerfile -t codingprojects-app:REVISION .
docker build --target web -f conf/Dockerfile -t codingprojects-web:REVISION .
sh scripts/check-runtime-image.sh codingprojects-app:REVISION
```

The `php-extensions` stage compiles PHP extensions. Composer installs production
dependencies in `builder`, and Node builds frontend assets in `assets`. The final
app starts from Debian slim and receives only PHP, FPM, extensions, configuration,
their runtime libraries and the assembled application. Keep the Debian release
identical to the PHP build stage when changing base versions.

Frontend assets are assembled before the final application copy. Always build
fresh `app` and `web` targets for a release; using the previous application image
as a base and copying a new frontend retains the old assets in Docker layers.

The runtime check runs without network access, application secrets or data
volumes. It exercises PHP extensions, checks FPM configuration, verifies assets
and rejects build tools in the final image. After rollout, also verify container
health and an HTTPS page before deleting the previous release and build cache.

Keep server environment files and deployment overrides outside the build context.
The production image does not include Composer, PHP build helpers or compilers;
application console commands continue to run through `php artisan`.

The production image writes Laravel logs to stderr. Keep
`LOG_CHANNEL=stderr` in the server environment for PHP-FPM, the queue workers
and the scheduler. View their logs with `docker compose logs php queue
notifications scheduler`. Compose configures rotation through Docker's
`json-file` driver.

When running Artisan in the web container, use
`docker compose exec --user www-data php php artisan …`. With daily file
logging, the first process to log on a new day creates the file. Running
Artisan as root can create a file that PHP-FPM cannot append to.
