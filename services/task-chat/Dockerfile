FROM node:22-alpine
WORKDIR /srv/task-chat
COPY package*.json ./
RUN npm ci --omit=dev --no-audit --no-fund
COPY server.mjs ./
USER node
CMD ["node","server.mjs"]
