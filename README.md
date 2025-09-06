# wg-simple-dash
Simple dashboard for [wg-proxy](https://github.com/jpw4dev/wg-proxy)

This was specifically built to use with [wg-proxy](https://github.com/jpw4dev/wg-proxy) and LinuxServer.io's SWAG. It may work with others with some modification.

EXPOSING THE DASHBOARD TO THE INTERNET IS STRONGLY DISCOURAGED.

Steps to install:

1. Clone repository and navigate into the project directory.
2. Copy the `wg-simple-dash` directory which contains the 3 static site files into SWAG config, something like: `../swag/config/www/`.
3. Copy the `wg-simple-dash.subdomain.conf.sample` file into `../swag/config/proxy-confs` as `wg-simple-dash.subdomain.conf`. (This defines the subdomain as `wg` e.g. `https://wg.<your-subdomain>.com`. Change if desired.)
4. Configure a CNAME record for the configured subdomain.
