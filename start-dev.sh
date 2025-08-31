echo "> Starting development server..."
# Remove previous container if exists
docker rm -f coding-dev > /dev/null 2>&1 || true
docker run --name coding-dev \
   --network=host \
   -p 8000:8000 \
   -v "$(pwd)/storage":/var/www/html/storage \
   -v "$(pwd)/.env":/var/www/html/.env:ro \
   --env-file .env \
   -e DB_CONNECTION=pgsql \
   codingprojects php artisan serve --host=0.0.0.0 --port=8000

echo "Application is running at http://localhost:8000/"
