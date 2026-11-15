# Same pinned runtimes as development. No new application dependency.
FROM node:22-bookworm@sha256:e13726ca604184d7a91fcacab1fc09eefd642dc28e79ac34e85540dd93b78cfb AS node
FROM php:8.3-fpm-bookworm@sha256:d7677e2a3c1579fbe52848cc446bc24860187c876c383a43b7d4b8e06dce537e
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip bcmath dom xml
WORKDIR /var/www/html
# Install browser matching the committed Playwright lockfile.
COPY package.json package-lock.json .npmrc ./
RUN npm ci && npx playwright install --with-deps chromium \
    && rm -rf node_modules /var/lib/apt/lists/*
