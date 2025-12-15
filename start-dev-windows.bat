docker build -f conf/Dockerfile.dev -t codingprojects .
docker rm -f coding-dev
docker run --name coding-dev --rm -p 8000:8000 -v "%cd%\storage":/var/www/html/storage codingprojects
