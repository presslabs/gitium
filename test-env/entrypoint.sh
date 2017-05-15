#!/usr/bin/env bash
set -e


echo "Setting permissions for the docker container..."
/tools/permission_fix.sh || true
chown -R $DOCKER_USER:$DOCKER_GROUP $VOLUME || true
echo "Done."

docker-entrypoint.sh apache2-foreground

#nginx -g "daemon off;"