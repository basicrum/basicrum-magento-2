FROM composer:2.10@sha256:a5f59b9fd2faf31218632be4809dc6491761085e8064c31dc3b84378c48c248b AS composer
FROM node:22.19.0-bookworm-slim@sha256:4a4884e8a44826194dff92ba316264f392056cbe243dcc9fd3551e71cea02b90 AS node
FROM markoshust/magento-php:8.3-fpm-7@sha256:51ef90a707f02299caadbd0ea9b8b5ceea4082b740f3a4d9f91db2db874c5ee9
USER root
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/playwright
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npx --yes playwright@1.62.1 install --with-deps chromium
RUN git config --system --add safe.directory /module
USER app
