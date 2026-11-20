#!/usr/bin/env bash
# Open the local Elme site on a phone (Wi-Fi or mobile data) through a public HTTPS tunnel:
# a Cloudflare quick tunnel, or localhost.run over ssh when Cloudflare's tunnel API is not reachable
# (some networks block it). No account needed; the URL is random and dies when this script stops.
#
#   magento-docker/phone-preview.sh        Ctrl+C stops the tunnel and puts the base URL back
#
# Magento redirects every request to its base URL, so while the tunnel runs the base URL is the
# tunnel URL (https://elme-hyva.local then redirects there too). The tunnel goes to Varnish on
# localhost:80, which already tells Magento the request was HTTPS.
# Needs: the Docker stack running; cloudflared (https://github.com/cloudflare/cloudflared/releases) or ssh.
# The site is public while the tunnel runs: do not leave it on, and keep real customer data out.

set -euo pipefail
cd "$(dirname "$0")/.."

LOCAL_URL="https://elme-hyva.local/"
LOG="$(mktemp)"
TUNNEL_PID=""
RESTORED=0

set_base_url() {
    bin/clinotty bash -c "bin/magento config:set web/unsecure/base_url '$1' \
        && bin/magento config:set web/secure/base_url '$1' \
        && bin/magento cache:flush" >/dev/null
}

restore() {
    [ "$RESTORED" = 1 ] && return
    RESTORED=1
    echo
    echo "Stopping the tunnel, base URL back to $LOCAL_URL ..."
    [ -n "$TUNNEL_PID" ] && kill "$TUNNEL_PID" 2>/dev/null || true
    set_base_url "$LOCAL_URL"
    rm -f "$LOG"
    echo "Done."
}
trap restore EXIT INT TERM

# Runs the tunnel command in the background, its output in $LOG.
start_tunnel() {
    : >"$LOG"
    "$@" >"$LOG" 2>&1 &
    TUNNEL_PID=$!
}

# Prints the first public URL matching $1 in the tunnel output, waiting up to $2 seconds.
wait_for_url() {
    local pattern="$1" seconds="$2" found=""
    for _ in $(seq 1 "$seconds"); do
        found="$(grep -aoE "$pattern" "$LOG" | grep -v '//api\.' | head -n 1 || true)"
        [ -n "$found" ] && { echo "$found"; return 0; }
        kill -0 "$TUNNEL_PID" 2>/dev/null || return 1
        sleep 1
    done
    return 1
}

URL=""
if command -v cloudflared >/dev/null; then
    echo "Starting a Cloudflare quick tunnel ..."
    start_tunnel cloudflared tunnel --no-autoupdate --url http://localhost:80
    URL="$(wait_for_url 'https://[a-z0-9-]+\.trycloudflare\.com' 30 || true)"
    if [ -z "$URL" ]; then
        kill "$TUNNEL_PID" 2>/dev/null || true
        echo "Cloudflare tunnel did not come up, trying localhost.run ..."
    fi
fi
if [ -z "$URL" ]; then
    start_tunnel ssh -T -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 \
        -o ExitOnForwardFailure=yes -R 80:localhost:80 nokey@localhost.run
    URL="$(wait_for_url 'https://[a-z0-9]+\.lhr\.life' 30 || true)"
fi
[ -n "$URL" ] || { echo "No tunnel came up:" >&2; cat "$LOG" >&2; exit 1; }

set_base_url "$URL/"

echo
echo "Elme on your phone: $URL"
python3 -c "import qrcode, sys; q = qrcode.QRCode(border=1); q.add_data(sys.argv[1]); q.print_ascii(invert=True)" \
    "$URL" 2>/dev/null || true
echo "Ctrl+C to stop."

wait "$TUNNEL_PID"
