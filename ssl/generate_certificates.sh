#!/usr/bin/env sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

openssl req -x509 -newkey rsa:3072 -sha256 -nodes \
  -keyout "$script_dir/server.local.key" \
  -out "$script_dir/server.local.crt" \
  -days 365 \
  -subj "/CN=localhost/O=Local Development" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
  -addext "keyUsage=digitalSignature,keyEncipherment" \
  -addext "extendedKeyUsage=serverAuth"

chmod 600 "$script_dir/server.local.key"
printf '%s\n' 'Created a localhost development certificate in ssl/.'

