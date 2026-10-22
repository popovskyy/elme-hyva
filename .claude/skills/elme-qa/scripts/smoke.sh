#!/usr/bin/env bash
# Backend smoke for an Elme QA pass. Read-only: it never writes to the DB or to the code.
#
#   bash .claude/skills/elme-qa/scripts/smoke.sh [path ...]
#
# Paths are requested on https://elme-hyva.local. With no paths, a default set of core pages is used.
set -u
cd "$(git rev-parse --show-toplevel)"
SCOPE=".claude/skills/elme-qa/scripts/scope.py"
HOST=https://elme-hyva.local
THEME=app/design/frontend/Elme/default
PHP=(docker exec -u magento -w /var/www/html elme_php)
fail=0
say() { printf '%s\n' "$*"; }
bad() { printf '  FAIL %s\n' "$*"; fail=1; }

say "== containers"
for c in elme_php elme_mariadb elme_nginx elme_varnish elme_ssl elme_opensearch elme_redis; do
  state=$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null || echo missing)
  [ "$state" = running ] && say "  ok   $c" || bad "$c is $state -> run: elmeStart (or docker compose up -d)"
done
[ "$fail" = 1 ] && { say "containers down, stopping here"; exit 1; }

say "== php -l on changed PHP"
while read -r f; do
  out=$("${PHP[@]}" php -l "$f" 2>&1 | grep -v Xdebug)
  echo "$out" | grep -q "No syntax errors" && say "  ok   $f" || bad "$f: $out"
done < <(python3 "$SCOPE" --list php)

say "== changed XML is well-formed"
while read -r f; do
  out=$("${PHP[@]}" php -r '
    libxml_use_internal_errors(true); (new DOMDocument())->load($argv[1]);
    foreach (libxml_get_errors() as $e) { echo trim($e->message), " line ", $e->line, "\n"; }' "$f" 2>&1 | grep -v Xdebug)
  [ -z "$out" ] && say "  ok   $f" || bad "$f: $out"
done < <(python3 "$SCOPE" --list xml)

say "== schema and data patches applied"
# The dry run lists the DDL it would execute. It also rewrites app/etc/config.php: put the developer's
# copy back byte for byte, so the smoke leaves no diff behind.
config_backup=$(mktemp); cp app/etc/config.php "$config_backup"
"${PHP[@]}" php bin/magento setup:upgrade --dry-run=1 --keep-generated >/dev/null 2>&1
cmp -s "$config_backup" app/etc/config.php || cat "$config_backup" > app/etc/config.php
rm -f "$config_backup"
pending=$(grep -E '^(ALTER|CREATE|DROP|RENAME)' var/log/dry-run-installation.log 2>/dev/null | cut -c1-200)
[ -z "$pending" ] && say "  ok   no pending schema change" || { bad "schema not applied -> run setup:upgrade:"; echo "$pending" | sed 's/^/         /'; }
while read -r f; do
  case "$f" in */Setup/Patch/*.php) ;; *) continue ;; esac
  cls=$("${PHP[@]}" php -r '
    $s = file_get_contents($argv[1]);
    preg_match("/^namespace\s+([^;]+);/m", $s, $n); preg_match("/^class\s+(\w+)/m", $s, $c);
    echo str_replace(chr(92), "/", $n[1]), "/", $c[1];' "$f" 2>/dev/null)
  n=$(docker exec elme_mariadb mariadb -uelme -pelme elme_magento -N -e \
      "SELECT COUNT(*) FROM patch_list WHERE REPLACE(patch_name, CHAR(92), '/') = '$cls'" 2>/dev/null)
  [ "$n" = 1 ] && say "  ok   patch applied: $cls" || bad "patch not applied: $cls -> run setup:upgrade"
done < <(python3 "$SCOPE" --list php)

say "== committed styles.css = a fresh Tailwind build"
# A class added to a template does nothing until styles.css is rebuilt: the page is 200, no console
# error, the element is just unstyled. Build into a temp file in the container (same steps as
# npm run build; it refreshes the gitignored generated/ files) and compare. File dates are useless here:
# Tailwind leaves an unchanged output file untouched.
css=$THEME/web/css/styles.css
if [ ! -f "$css" ]; then
  bad "$css missing -> cd $THEME/web/tailwind && npm ci && npm run build"
elif [ ! -d "$THEME/web/tailwind/node_modules" ]; then
  bad "$THEME/web/tailwind/node_modules missing -> npm ci there first"
else
  "${PHP[@]}" bash -c "cd $THEME/web/tailwind && npx hyva-sources && npx hyva-tokens && npx hyva-fonts \
    && npx tailwindcss -i tailwind-source.css -o /tmp/elme-qa-styles.css --minify" >/dev/null 2>&1
  if docker exec elme_php cat /tmp/elme-qa-styles.css 2>/dev/null | cmp -s - "$css"; then
    say "  ok   styles.css matches a fresh build"
  else
    bad "styles.css is stale (a fresh build differs) -> npm run build in $THEME/web/tailwind and commit it"
  fi
fi
served=$(curl -sk --max-time 30 "$HOST/" | grep -oE "$HOST/static/version[0-9]+/frontend/Elme/default/en_AU/css/styles.css" | head -1)
if [ -z "$served" ]; then
  bad "homepage links no Elme/default styles.css: is the theme assigned to the store? (design/theme/theme_id)"
elif ! curl -sk --max-time 30 "$served" | cmp -s - "$css"; then
  bad "served styles.css differs from $css -> rm -rf pub/static/frontend/Elme var/view_preprocessed/pub/static/frontend/Elme, bump pub/static/deployed_version.txt"
else
  say "  ok   served styles.css = built file"
fi

say "== HTTP status"
paths=("$@")
[ ${#paths[@]} -eq 0 ] && paths=(/ /customer/account/login/ /checkout/cart/ "/catalogsearch/result/?q=vase" /admin/)
log_offsets() { for f in var/log/exception.log var/log/system.log; do printf '%s:%s ' "$f" "$(stat -c %s "$f" 2>/dev/null || echo 0)"; done; }
before=$(log_offsets)
for p in "${paths[@]}"; do
  code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 60 "$HOST$p")
  case "$code" in 200|301|302) say "  ok   $code $p" ;; *) bad "$code $p" ;; esac
done

say "== new ERROR/CRITICAL log lines written during this run"
new=0
for pair in $before; do
  f=${pair%%:*}; off=${pair##*:}
  [ -f "$f" ] || continue
  lines=$(tail -c +"$((off + 1))" "$f" | grep -E '\.(ERROR|CRITICAL|EMERGENCY): ' | cut -c1-240 | grep -v '^$')
  if [ -n "$lines" ]; then new=1; bad "$f:"; echo "$lines" | sed 's/^/         /' | head -20; fi
done
[ "$new" = 0 ] && say "  ok   none"

[ "$fail" = 0 ] && say "SMOKE: PASS" || say "SMOKE: FAIL"
exit "$fail"
