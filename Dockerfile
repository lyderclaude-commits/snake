# Wakabi Boost — image de production.
#
# En trois étapes pour que l'image finale ne contienne ni les sources, ni les
# outils de compilation : better-sqlite3 et sharp sont des modules natifs, ils
# se compilent une fois ici et voyagent compilés.

# ---------- 1. dépendances ----------
FROM node:22-bookworm-slim AS deps
WORKDIR /app
# python3, make et g++ servent à compiler better-sqlite3. Ils restent ici.
RUN apt-get update && apt-get install -y --no-install-recommends python3 make g++ \
    && rm -rf /var/lib/apt/lists/*
COPY package.json package-lock.json ./
RUN npm ci

# ---------- 2. build ----------
FROM node:22-bookworm-slim AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
# WAKABI_BASE_URL n'est PAS nécessaire au build : il n'est lu qu'à l'exécution,
# au moment d'émettre un badge. L'image reste donc valable pour tout domaine.
RUN npm run build

# ---------- 3. exécution ----------
FROM node:22-bookworm-slim AS runtime
WORKDIR /app
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0
RUN useradd --system --uid 1001 wakabi

# La sortie « standalone » embarque le serveur et les seules dépendances
# réellement utilisées — le reste de node_modules ne monte pas dans l'image.
COPY --from=build --chown=wakabi:wakabi /app/.next/standalone ./
COPY --from=build --chown=wakabi:wakabi /app/.next/static ./.next/static
COPY --from=build --chown=wakabi:wakabi /app/public ./public

# La base et les cadres vivent HORS de l'application : un conteneur se
# remplace, ces deux-là ne doivent pas partir avec.
ENV WAKABI_DB=/data/wakabi.sqlite WAKABI_UPLOADS=/data/uploads
RUN mkdir -p /data && chown wakabi:wakabi /data
VOLUME ["/data"]

USER wakabi
EXPOSE 3000
CMD ["node", "server.js"]
