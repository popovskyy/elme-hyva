# Elme Living - Magento Open Source 2.4.9 + Hyvä

New Elme Living storefront: Magento Open Source on its own instance, Hyvä theme (open source, installed from the public `hyva-themes` GitHub repos - no license key needed).

## Stack (docker compose)

| Service | Image | Host port |
|---|---|---|
| PHP-FPM 8.4 (+ composer, node 22, xdebug off by default) | `elme-php` (`magento-docker/php/Dockerfile`) | - |
| nginx -> Varnish (FPC) -> TLS terminator | nginx / varnish 9 | 80 / 443 |
| MariaDB 11.4 | mariadb:11.4 | 3314 (`elme` / `elme`, db `elme_magento`) |
| Valkey 8 (cache db0, page_cache db1, session db2) | valkey/valkey:8 | - |
| OpenSearch 3.5 | opensearchproject/opensearch:3.5.0 | 9207 |
| RabbitMQ 4.1 | rabbitmq:4.1-management | 5675 / 15675 |
| Mailpit | axllent/mailpit | 1028 (SMTP) / 8028 (UI) |

Ports 80/443 are shared with the other Bart Group Magento stacks, so run only one stack at a time. To run several, remap the host ports in a `docker-compose.override.yml` (git-ignored).

## First run on a new machine

Needs Docker with the compose plugin (v2), [mkcert](https://github.com/FiloSottile/mkcert) and your repo.magento.com keys.

```bash
git clone <repo-url> elme-hyva && cd elme-hyva
cp auth.json.sample auth.json          # put your repo.magento.com public / private key in it
bin/setup
```

`bin/setup` does everything below, in order, and can be run again at any time (it never drops the database):

1. checks Docker, mkcert and `auth.json`;
2. adds `elme-hyva.local` to `/etc/hosts` (asks for sudo) and creates the local HTTPS certificate in `magento-docker/certs/`;
3. builds and starts the containers (the first build takes several minutes) and waits until MariaDB, OpenSearch, RabbitMQ and Valkey answer;
4. `composer install`;
5. `bin/magento setup:install` on a new instance (the command is in "Install" below), `setup:upgrade` when `app/etc/env.php` already exists;
6. activates the `Elme/default` theme, sets up Varnish, switches to developer mode;
7. generates the Hyva theme assets that are not in git (`app/etc/hyva-themes.json`, the Tailwind inputs and the self-hosted Figtree font) with `npm ci && npm run generate`. It does not run `npm run build`, `web/css/styles.css` is committed.

The new store has the CMS pages from the data patches but **no products**: no database dump ships with the repo, ask the team if you need catalogue data.

## Daily use

- Start and stop: `docker compose up -d` / `docker compose down`. Shell in the PHP container: `docker exec -it -u magento elme_php bash`, or one-off commands with `bin/clinotty bash -c "bin/magento ..."`.
- Storefront https://elme-hyva.local ; Admin https://elme-hyva.local/admin (local: `admin` / `admin123`, 2FA disabled locally).
- Xdebug: `XDEBUG_MODE=debug docker compose up -d php`.
- On a phone: `magento-docker/phone-preview.sh` opens a public HTTPS tunnel (Cloudflare quick tunnel, or localhost.run when Cloudflare is blocked), prints the URL and a QR code, and switches the base URL to it; Ctrl+C switches it back. The site is public while it runs, and https://elme-hyva.local redirects to the tunnel meanwhile.

## Theme

`app/design/frontend/Elme/default` - child of `Hyva/default` (Tailwind v4).

```bash
cd app/design/frontend/Elme/default/web/tailwind
npm ci
npm run watch
npm run build        # minified build of web/css/styles.css
```

Design tokens (`tokens.values`) and fonts (Figtree for body and headings, self-hosted from `web/fonts/`) live in `web/tailwind/hyva.config.json`. Breakpoints: mobile < 768 ; tablet `md:` 768 ; desktop `lg:` 1025 ; `xl:` 1280 ; `2xl:` 1440 ; `3xl:` 1920 (container max width 1920px).

Checkout is our own module `Elme_Checkout` on Elme/default (decision D12 in `docs/frontend-tickets.md`); the Luma fallback checkout was removed.

## Install (what `bin/setup` runs on a new instance)

```bash
bin/magento setup:install \
  --base-url=https://elme-hyva.local/ --use-secure=1 --use-secure-admin=1 \
  --db-host=mariadb --db-name=elme_magento --db-user=elme --db-password=elme \
  --admin-user=admin --admin-password='admin123' --admin-email=admin@elme-hyva.local --admin-firstname=Admin --admin-lastname=Elme \
  --backend-frontname=admin --language=en_AU --currency=AUD --timezone=Australia/Melbourne \
  --search-engine=opensearch --opensearch-host=opensearch --opensearch-port=9200 --opensearch-index-prefix=elme \
  --cache-backend=redis --cache-backend-redis-server=redis --cache-backend-redis-db=0 \
  --page-cache=redis --page-cache-redis-server=redis --page-cache-redis-db=1 \
  --session-save=redis --session-save-redis-host=redis --session-save-redis-db=2 \
  --amqp-host=rabbitmq --amqp-port=5672 --amqp-user=guest --amqp-password=guest \
  --http-cache-hosts=varnish:80
bin/magento deploy:mode:set developer
bin/magento config:set design/theme/theme_id <id of Elme/default in the theme table>
bin/magento config:set system/full_page_cache/caching_application 2
bin/magento config:set system/full_page_cache/varnish/backend_host nginx
bin/magento config:set system/full_page_cache/varnish/backend_port 8080
```

The two Magento 2FA modules are already disabled in the committed `app/etc/config.php`, so there is nothing to disable.

`hyva-themes/magento2-mollie-theme-bundle` is in `replace` in `composer.json` on purpose: it would pull in the Mollie payment module + Magewire, which Elme does not use.
