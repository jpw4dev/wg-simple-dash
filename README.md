# wg-simple-dash
Simple dashboard for WireGuard containers

This was specifically built to use with LinuxServer.io wireguard and swag containers. It may work with others with some modification.

EXPOSING THE DASHBOARD TO THE INTERNET IS STRONGLY NOT RECOMMENDED. 
FOR LOCAL NETWORK USE ONLY.

Steps to install:

1. Clone repository and navigate into the project directory.
2. Copy the `wg-simple-dash` directory which contains the 3 static site files into `../swag/config/www/`.
3. Copy the `wg-simple-dash.subdomain.conf` file into `../swag/config/proxy-confs` (This defines the subdomain as `wg-simple-dash` e.g. `wg-simple-dash.<your-subdomain>.com`. Change if desired.)
4. Configure a CNAME record for the configured subdomain.
5. Configure the `docker-compose.yml` as necessary for your environment.
6. Run `docker compose up -d`.
7. Run `curl http://localhost:8123` to check the API on the server.
8. Load `https://wg-simple-dash.<your-subdomain>.com` (or whatever subdomain you configured to view dashboard.)