#!/usr/bin/env sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
timestamp=$(date -u +%Y%m%d-%H%M%S)
backup_root="${project_root}/../RemindEDU-backups/${timestamp}"

mkdir -p "${backup_root}"
cp -R "${project_root}/storage/data/." "${backup_root}/"
chmod -R go-rwx "${backup_root}"
printf 'Backup created: %s\n' "${backup_root}"
