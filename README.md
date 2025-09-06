# wg-simple-dash
Simple dashboard for [wg-proxy](https://github.com/jpw4dev/wg-proxy)

This was specifically built to use with [wg-proxy](https://github.com/jpw4dev/wg-proxy) and LinuxServer.io's SWAG.

EXPOSING THE DASHBOARD TO THE INTERNET IS STRONGLY DISCOURAGED.

Steps to install:

1. Add `ghcr.io/jpw4dev/wg-simple-dash:latest` to `DOCKER_MODS` environment variable in SWAG's `docker-compose.yml`.
2. Configure a CNAME record for the configured subdomain.
