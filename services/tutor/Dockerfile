FROM python:3.12-slim
WORKDIR /srv/tutor
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY tutor.py reference.json ./
USER nobody
CMD ["gunicorn", "--bind=0.0.0.0:8080", "--workers=2", "--threads=4", "--timeout=75", "tutor:app"]

