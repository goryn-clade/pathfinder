#!/usr/bin/env bash
# Build public/ assets with gulp inside a container, so Node, gulp and
# GraphicsMagick don't need installing on the host.
#
# Usage: ./build-assets.sh [gulp task and options]   (default: production)
#        NODE_VERSION=20 ./build-assets.sh            (try another Node version)
#
# Output is written straight into ./public through the bind mount, owned by
# the calling user (also when run with sudo).
set -euo pipefail
cd "$(dirname "$0")"

NODE_VERSION="${NODE_VERSION:-18}"
IMAGE="pathfinder-gulp:node${NODE_VERSION}"

# Only package.json/package-lock.json are needed, so send a minimal build context.
tar -c gulp.Dockerfile package.json package-lock.json \
    | docker build --build-arg NODE_VERSION="$NODE_VERSION" -f gulp.Dockerfile -t "$IMAGE" -

docker run --rm \
    --user "${SUDO_UID:-$(id -u)}:${SUDO_GID:-$(id -g)}" \
    -e HOME=/tmp \
    -v "$PWD":/opt/deps/app \
    "$IMAGE" "${@:-production}"
