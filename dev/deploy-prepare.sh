#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf deploy
mkdir -p deploy/storage

cp -al app deploy/app
cp -al bootstrap deploy/bootstrap
cp -al config deploy/config
cp -al database deploy/database
cp -al public deploy/public
cp -al resources deploy/resources
cp -al routes deploy/routes
cp -al vendor deploy/vendor
cp -al runtime deploy/runtime
cp -al artisan deploy/artisan
cp -al storage/framework deploy/storage/framework
cp -al storage/logs deploy/storage/logs

mkdir -p deploy/storage/app/public deploy/storage/app/private
touch deploy/storage/app/.gitkeep deploy/storage/app/public/.gitkeep deploy/storage/app/private/.gitkeep

echo "deploy stage built:"
du -sh deploy