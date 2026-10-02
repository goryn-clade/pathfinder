# Build environment for the gulp asset pipeline (JS, CSS, images -> public/).
# Use build-assets.sh rather than running this directly.
#
# node_modules lives in the image at /opt/deps/node_modules. The repo is mounted
# at /opt/deps/app, so Node finds the modules one directory up and the host
# checkout never gets a node_modules folder.
ARG NODE_VERSION=18
FROM node:${NODE_VERSION}-bookworm

# gulp-image-resize shells out to GraphicsMagick (its default; imageMagick option is unset)
RUN apt-get update \
    && apt-get install -y --no-install-recommends graphicsmagick \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/deps
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

ENV PATH=/opt/deps/node_modules/.bin:$PATH
WORKDIR /opt/deps/app
ENTRYPOINT ["gulp"]
CMD ["production"]
