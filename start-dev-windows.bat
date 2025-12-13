docker build -f conf/Dockerfile.dev -t codingprojects .
docker run --rm -p 8000:8000 codingprojects
