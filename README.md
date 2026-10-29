# Elme Living — Magento Open Source 2.4.9 + Hyvä

New Elme Living storefront: Magento Open Source on its own instance, Hyvä theme (open source, installed from the public `hyva-themes` GitHub repos — no license key needed).

## Stack (docker compose)

| Service | Image | Host port |
|---|---|---|
| PHP-FPM 8.4 (+ composer, node 22, xdebug off by default) | `elme-php` (`magento-docker/php/Dockerfile`) | — |
| nginx → Varnish (FPC) → TLS terminator | nginx / varnish 9 | 80 / 443 (fallback 8087 / 8447) |
| MariaDB 11.4 | mariadb:11.4 | 3314 (`elme` / `elme`, db `elme_magento`) |
| Valkey 8 (cache db0, page_cache db1, session db2) | valkey/valkey:8 | — |
| OpenSearch 3.5 | opensearchproject/opensearch:3.5.0 | 9207 |
| RabbitMQ 4.1 | rabbitmq:4.1-management | 5675 / 15675 |
| Mailpit | axllent/mailpit | 1028 (SMTP) / 8028 (UI) |

The 80/443 ports are shared with the other local Magento stacks (Bart, Shaw) — run one at a time. The dev-dashboard does this for you.

## First run on a new machine

```bash
echo "127.0.0.1 elme-hyva.local" | sudo tee -a /etc/hosts
(cd magento-docker && mkdir -p certs && cd certs && mkcert -cert-file elme-hyva.local.pem -key-file elme-hyva.local-key.pem elme-hyva.local)
cp auth.json.sample auth.json          # repo.magento.com keys
docker compose up -d
docker exec -it elme_php composer install
# then bin/magento setup:install … (see "Install" below) or import a DB dump
```

## Daily use

- `elmeStart` / `elmeStop` / `enterElme` (aliases in `~/.my_aliases`), or the **Elme Living (Hyvä)** card in dev-dashboard (http://127.0.0.1:7777).
- Storefront https://elme-hyva.local · Admin https://elme-hyva.local/admin (local: `admin` / `Admin12345!`, 2FA disabled locally).
- Xdebug: `XDEBUG_MODE=debug docker compose up -d php`.
- On a phone: `magento-docker/phone-preview.sh` opens a public HTTPS tunnel (Cloudflare quick tunnel, or localhost.run when Cloudflare is blocked), prints the URL and a QR code, and switches the base URL to it; Ctrl+C switches it back. The site is public while it runs, and https://elme-hyva.local redirects to the tunnel meanwhile.

## Theme

`app/design/frontend/Elme/default` — child of `Hyva/default` (Tailwind v4).

```bash
cd app/design/frontend/Elme/default/web/tailwind
npm ci
npm run watch        # or: elmeTailwind / dashboard "WATCH START"
npm run build        # minified build of web/css/styles.css
```

Design tokens (`tokens.values`) and fonts (Karla, DM Serif Display, self-hosted from `web/fonts/`) live in `web/tailwind/hyva.config.json`. Breakpoints: mobile < 768 · tablet `md:` 768 · desktop `lg:` 1025 · `xl:` 1280 · `2xl:` 1440 · `3xl:` 1920 (container max width 1920px).

Checkout is our own module `Elme_Checkout` on Elme/default (decision D12 in `docs/frontend-tickets.md`); the Luma fallback checkout was removed.

## Install (how this instance was created)

```bash
bin/magento setup:install \
  --base-url=https://elme-hyva.local/ --use-secure=1 --use-secure-admin=1 \
  --db-host=mariadb --db-name=elme_magento --db-user=elme --db-password=elme \
  --admin-user=admin --admin-password='Admin12345!' --admin-email=admin@elme-hyva.local --admin-firstname=Admin --admin-lastname=Elme \
  --backend-frontname=admin --language=en_AU --currency=AUD --timezone=Australia/Melbourne \
  --search-engine=opensearch --opensearch-host=opensearch --opensearch-port=9200 --opensearch-index-prefix=elme \
  --cache-backend=redis --cache-backend-redis-server=redis --cache-backend-redis-db=0 \
  --page-cache=redis --page-cache-redis-server=redis --page-cache-redis-db=1 \
  --session-save=redis --session-save-redis-host=redis --session-save-redis-db=2 \
  --amqp-host=rabbitmq --amqp-port=5672 --amqp-user=guest --amqp-password=guest \
  --http-cache-hosts=varnish:80
bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth
bin/magento deploy:mode:set developer
bin/magento config:set design/theme/theme_id <Elme/default theme_id>
bin/magento config:set system/full_page_cache/caching_application 2
bin/magento config:set system/full_page_cache/varnish/backend_host nginx
bin/magento config:set system/full_page_cache/varnish/backend_port 8080
```

`hyva-themes/magento2-mollie-theme-bundle` is in `replace` in `composer.json` on purpose: it would pull in the Mollie payment module + Magewire, which Elme does not use.
