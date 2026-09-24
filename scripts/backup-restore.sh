#!/usr/bin/env bash
set -euo pipefail
# Credentials should be supplied through a protected MongoDB Database Tools config file.
# This helper intentionally only restores into a distinct database ending in _restore_test.
if [[ $# -ne 4 ]]; then
  echo "Usage: $0 tools-config.yml source_database target_restore_test archive.gz" >&2
  exit 2
fi
nabaku_config=$1
nabaku_source=$2
nabaku_target=$3
nabaku_archive=$4
[[ $nabaku_target == *_restore_test && $nabaku_target != "$nabaku_source" ]] || { echo 'Unsafe restore target' >&2; exit 2; }
[[ $nabaku_source =~ ^[a-zA-Z0-9_]+$ && $nabaku_target =~ ^[a-zA-Z0-9_]+$ ]] || { echo 'Invalid database name' >&2; exit 2; }
[[ ! -e $nabaku_archive ]] || { echo 'Archive already exists; refusing overwrite' >&2; exit 2; }
umask 077
mongodump --config="$nabaku_config" --db="$nabaku_source" --archive="$nabaku_archive" --gzip
# No --drop. Use an empty disposable target and compare preflight/reconciliation afterwards.
mongorestore --config="$nabaku_config" --archive="$nabaku_archive" --gzip --nsFrom="$nabaku_source.*" --nsTo="$nabaku_target.*" --stopOnError
