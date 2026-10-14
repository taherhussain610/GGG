FROM node:22-alpine

WORKDIR /app
ENV NODE_ENV=production
ENV PORT=4173

COPY package.json ./
COPY server.js slot-engine.js providers.json games.json index.html README.md ./
COPY lib ./lib

RUN addgroup -S app && adduser -S app -G app
USER app
EXPOSE 4173

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 CMD node -e "fetch('http://127.0.0.1:4173/api/health').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"

CMD ["node", "server.js"]
