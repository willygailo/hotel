#!/usr/bin/env bash
# ==============================================================================
# 🏨 MARVELOUS HOTEL & DORMITORY — ONE-CLICK CAPSTONE LIVE SHARING SCRIPT
# File: scripts/share-capstone.sh (Symlinked to project root as share-capstone.sh)
# Engine: Cloudflare Tunnel, Ngrok Fallback, Local LAN Wi-Fi, & Terminal QR Code
# Synchronized with: GitHub (github.com/gailowilly551-afk/hotel), Gmail SMTP,
#                    Real-Time Room Availability & Live Password Reset Popups
# ==============================================================================

set -e

# 1. Resolve Project Root Directory dynamically
SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do
    DIR="$(cd -P "$(dirname "$SOURCE")" && pwd)"
    SOURCE="$(readlink "$SOURCE")"
    [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
done
CURRENT_DIR="$(cd -P "$(dirname "$SOURCE")" && pwd)"

if [ -f "$CURRENT_DIR/frontend/pages/index.php" ] || [ -f "$CURRENT_DIR/index.php" ]; then
    HOTEL_DIR="$CURRENT_DIR"
elif [ -f "$CURRENT_DIR/../frontend/pages/index.php" ] || [ -f "$CURRENT_DIR/../index.php" ]; then
    HOTEL_DIR="$(cd "$CURRENT_DIR/.." && pwd)"
else
    HOTEL_DIR="/opt/lampp/htdocs/hotel"
fi

cd "$HOTEL_DIR"

# Parse Command-Line Arguments
ACTION="${1:-}"

if [ "$ACTION" = "--help" ] || [ "$ACTION" = "-h" ] || [ "$ACTION" = "help" ]; then
    echo "🏨 Marvelous Hotel & Dormitory — Live Sharing Script Help"
    echo "======================================================================"
    echo "Gamit: ./share-capstone.sh [FLAG]"
    echo ""
    echo "Mga Flags:"
    echo "  (walang flag)   Sisimulan ang live Cloudflare/Ngrok tunnel at maghihintay (Ctrl+C to stop)"
    echo "  --daemon, -d    Patatakbuhin ang tunnel sa background (nohup/disown — ligtas isara ang terminal)"
    echo "  --status        Ipakita ang kasalukuyang tumatakbong live link at tunnel status"
    echo "  --check         Pre-demo health check (Apache, DB Migrations, PHPUnit tests, & Tunnel)"
    echo "  --stop          Isara ang lahat ng aktibong tunnels at patayin ang daemon process"
    echo "  --help, -h      Ipakita ang tulong na ito"
    echo "======================================================================"
    exit 0
fi

if [ "$ACTION" = "--check" ] || [ "$ACTION" = "check" ]; then
    echo "🩺 Marvelous Hotel — Pre-Defense System Health Check"
    echo "======================================================================"
    PHP_BIN=""
    if [ -x "/opt/lampp/bin/php" ]; then
        PHP_BIN="/opt/lampp/bin/php"
    elif command -v php &>/dev/null; then
        PHP_BIN="php"
    fi

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/hotel/index.php 2>/dev/null || true)
    if [ "$HTTP_CODE" != "200" ] && [ "$HTTP_CODE" != "302" ]; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php 2>/dev/null || true)
    fi
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
        echo "🟢 Web Server:   Online (HTTP $HTTP_CODE)"
    else
        echo "🔴 Web Server:   Offline / Unreachable (HTTP ${HTTP_CODE:-down})"
    fi

    if [ -n "$PHP_BIN" ]; then
        echo "🐘 PHP Binary:   $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'))"
        echo ""
        echo "🔄 Database Migrations:"
        "$PHP_BIN" database/migrate.php --status 2>&1 | sed 's/^/   /' || true
        if [ -f "$HOTEL_DIR/phpunit.phar" ]; then
            echo ""
            echo "🧪 Automated Unit Tests:"
            TEST_PHP="$PHP_BIN"
            if command -v php &>/dev/null && php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' 2>/dev/null; then
                TEST_PHP="php"
            fi
            "$TEST_PHP" phpunit.phar --no-progress 2>&1 | tail -n 5 | sed 's/^/   /' || true
        fi
    else
        echo "⚠️  PHP CLI hindi nakita sa PATH."
    fi

    echo ""
    if pgrep -f "cloudflared tunnel" &>/dev/null || pgrep -f "ngrok http" &>/dev/null; then
        echo "🟢 Live Tunnel:  Active (tingnan sa ./share-capstone.sh --status)"
    else
        echo "⚪ Live Tunnel:  Hindi pa tumatakbo (patakbuhin: ./share-capstone.sh --daemon)"
    fi
    echo "======================================================================"
    exit 0
fi

if [ "$ACTION" = "--stop" ] || [ "$ACTION" = "stop" ]; then
    echo "🛑 Isinasara ang lahat ng aktibong tunnels (Cloudflare / Ngrok)..."
    pkill -f "cloudflared tunnel" 2>/dev/null || true
    pkill -f "ngrok http" 2>/dev/null || true
    rm -f "$HOTEL_DIR/LIVE_URL.txt" /tmp/hotel_tunnel.pid /tmp/cloudflared.log /tmp/ngrok.log
    echo "✅ Nakasara na ang lahat ng live tunnels."
    exit 0
fi

if [ "$ACTION" = "--status" ] || [ "$ACTION" = "status" ]; then
    if pgrep -f "cloudflared tunnel" &>/dev/null || pgrep -f "ngrok http" &>/dev/null; then
        ACTIVE_PID=$(cat /tmp/hotel_tunnel.pid 2>/dev/null || pgrep -f "cloudflared tunnel|ngrok http" | head -n 1 || true)
        echo "🟢 May aktibong live tunnel na tumatakbo!${ACTIVE_PID:+ (PID: $ACTIVE_PID)}"
        echo ""
        if [ -f "$HOTEL_DIR/LIVE_URL.txt" ]; then
            cat "$HOTEL_DIR/LIVE_URL.txt"
        elif [ -f /tmp/cloudflared.log ]; then
            ACTIVE_URL=$(grep -o 'https://[-a-zA-Z0-9@:%._\+~#=]*\.trycloudflare\.com' /tmp/cloudflared.log | tail -n 1 || true)
            echo "URL: $ACTIVE_URL"
        fi
    else
        echo "🔴 Walang tumatakbong live tunnel sa ngayon."
        echo "Patakbuhin: ./share-capstone.sh  (o ./share-capstone.sh --daemon para hindi mamatay kapag sinara ang terminal)"
    fi
    exit 0
fi

IS_DAEMON=0
if [ "$ACTION" = "--daemon" ] || [ "$ACTION" = "-d" ] || [ "$ACTION" = "daemon" ]; then
    IS_DAEMON=1
fi

clear 2>/dev/null || true
echo "================================================================================"
echo "🏨 INIHAHANDA ANG MARVELOUS HOTEL CAPSTONE LIVE PRESENTATION & CLOUD TUNNEL..."
echo "================================================================================"

# 2. Ensure Permissions on uploads, storage, cache, and logs
echo "🛡️  Sinusuri ang File Permissions sa uploads, storage, cache, at logs..."
mkdir -p "$HOTEL_DIR/uploads" "$HOTEL_DIR/logs" "$HOTEL_DIR/storage/logs" "$HOTEL_DIR/storage/sessions" "$HOTEL_DIR/storage/cache/avatars" 2>/dev/null || true
chmod -R 777 "$HOTEL_DIR/uploads" "$HOTEL_DIR/logs" "$HOTEL_DIR/storage" 2>/dev/null || true

# 3. Detect Local Network IP for LAN / Wi-Fi Sharing
LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || ip -4 addr show scope global 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1 || true)
if [ -z "$LOCAL_IP" ]; then
    LOCAL_IP="127.0.0.1"
fi

# 4. Check & Start XAMPP Apache & MySQL Services
echo "🔍 Sinusuri ang XAMPP Web Server at Database..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/hotel/index.php 2>/dev/null || true)
if [ "$HTTP_STATUS" != "200" ] && [ "$HTTP_STATUS" != "302" ]; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php 2>/dev/null || true)
fi

if [ "$HTTP_STATUS" != "200" ] && [ "$HTTP_STATUS" != "302" ]; then
    echo "⚙️  Sinisimulan ang XAMPP stack (Apache & MySQL)..."
    if [ -x "/opt/lampp/lampp" ]; then
        if sudo -n true 2>/dev/null; then
            sudo /opt/lampp/lampp start >/dev/null 2>&1 || true
        else
            echo "willy" | sudo -S /opt/lampp/lampp start >/dev/null 2>&1 || sudo /opt/lampp/lampp start >/dev/null 2>&1 || true
        fi
        sleep 2
    elif command -v systemctl &>/dev/null; then
        sudo systemctl start apache2 mariadb 2>/dev/null || sudo service apache2 start 2>/dev/null || true
    fi
fi

# 5. Run Database Migrations Automatically
echo "🔄 Sinusuri at inilalapat ang Database Migrations..."
MIGRATE_OUT=""
if [ -x "/opt/lampp/bin/php" ]; then
    MIGRATE_OUT=$(/opt/lampp/bin/php database/migrate.php 2>&1 || true)
elif command -v php &>/dev/null; then
    MIGRATE_OUT=$(php database/migrate.php 2>&1 || true)
fi
if echo "$MIGRATE_OUT" | grep -q '\[applied\]'; then
    echo "$MIGRATE_OUT" | grep '\[applied\]' | sed 's/^/   ✅ /'
elif echo "$MIGRATE_OUT" | grep -q 'Migration run tapos na'; then
    echo "   ✅ Lahat ng database migrations ay up to date."
fi

# 6. Detect Apache Port and Base Path dynamically
TARGET_PORT="80"
BASE_PATH="/hotel"

if curl -s -o /dev/null -f "http://localhost:8080/index.php" 2>/dev/null; then
    TARGET_PORT="8080"
    BASE_PATH=""
elif curl -s -o /dev/null -f "http://localhost:8080/hotel/index.php" 2>/dev/null; then
    TARGET_PORT="8080"
    BASE_PATH="/hotel"
elif curl -s -o /dev/null -f "http://localhost/index.php" 2>/dev/null && ! curl -s -o /dev/null -f "http://localhost/hotel/index.php" 2>/dev/null; then
    TARGET_PORT="80"
    BASE_PATH=""
fi

# 7. Clean up old tunnel processes & logs
pkill -f "cloudflared tunnel" 2>/dev/null || true
pkill -f "ngrok http" 2>/dev/null || true
rm -f /tmp/cloudflared.log /tmp/ngrok.log

# 8. Determine Tunnel Engine (Cloudflare vs Ngrok)
TUNNEL_URL=""
TUNNEL_ENGINE=""
TUNNEL_PID=""

cleanup() {
    echo ""
    echo "🛑 Isinasara ang live tunnel process..."
    if [ -n "$TUNNEL_PID" ]; then
        kill "$TUNNEL_PID" 2>/dev/null || true
    fi
    pkill -f "cloudflared tunnel" 2>/dev/null || true
    pkill -f "ngrok http" 2>/dev/null || true
    rm -f "$HOTEL_DIR/LIVE_URL.txt" /tmp/hotel_tunnel.pid 2>/dev/null || true
    echo "✅ Sarado na ang live link. Salamat at tagumpay ang presentation!"
    exit 0
}
trap cleanup SIGINT SIGTERM

# Locate Cloudflare binary if available
CLOUDFLARED_BIN=""
if command -v cloudflared &>/dev/null; then
    CLOUDFLARED_BIN="cloudflared"
elif [ -x "/tmp/cloudflared" ]; then
    CLOUDFLARED_BIN="/tmp/cloudflared"
elif [ -x "$HOME/bin/cloudflared" ]; then
    CLOUDFLARED_BIN="$HOME/bin/cloudflared"
elif [ -x "$HOME/.local/bin/cloudflared" ]; then
    CLOUDFLARED_BIN="$HOME/.local/bin/cloudflared"
elif [ -x "/usr/local/bin/cloudflared" ]; then
    CLOUDFLARED_BIN="/usr/local/bin/cloudflared"
fi

# Auto-download standalone Cloudflare binary if missing
if [ -z "$CLOUDFLARED_BIN" ]; then
    echo "📥 Cloudflared binary hindi nakita sa PATH. Sinusubukang i-download sa /tmp/..."
    if command -v curl &>/dev/null; then
        curl -fsSL --connect-timeout 8 -o /tmp/cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 2>/dev/null && chmod +x /tmp/cloudflared 2>/dev/null || true
    elif command -v wget &>/dev/null; then
        wget -q --timeout=8 -O /tmp/cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 2>/dev/null && chmod +x /tmp/cloudflared 2>/dev/null || true
    fi
    if [ -x "/tmp/cloudflared" ]; then
        CLOUDFLARED_BIN="/tmp/cloudflared"
        echo "✅ Handa na ang Cloudflare Tunnel binary."
    fi
fi

# Try Cloudflare first if available
if [ -n "$CLOUDFLARED_BIN" ]; then
    echo "🌐 Ikinokonekta sa Internet via Secure High-Speed Cloudflare Tunnel (Port $TARGET_PORT)..."
    nohup "$CLOUDFLARED_BIN" tunnel --url "http://localhost:$TARGET_PORT" \
        --proxy-keepalive-connections 100 \
        --proxy-keepalive-timeout 90s \
        --proxy-tcp-keepalive 30s \
        --retries 5 \
        --logfile /tmp/cloudflared.log </dev/null >/dev/null 2>&1 &
    TUNNEL_PID=$!
    TUNNEL_ENGINE="Cloudflare"

    for i in {1..20}; do
        if [ -f /tmp/cloudflared.log ]; then
            TUNNEL_URL=$(grep -o 'https://[-a-zA-Z0-9@:%._\+~#=]*\.trycloudflare\.com' /tmp/cloudflared.log | tail -n 1 || true)
            if [ -n "$TUNNEL_URL" ]; then
                break
            fi
        fi
        sleep 1
    done
fi

# Fallback to Ngrok if Cloudflare is not available or timed out
if [ -z "$TUNNEL_URL" ] && command -v ngrok &>/dev/null; then
    if [ -n "$TUNNEL_PID" ]; then
        kill "$TUNNEL_PID" 2>/dev/null || true
    fi
    echo "⚡ Cloudflare unavailable, lumilipat sa Ngrok Tunnel (Port $TARGET_PORT)..."
    nohup ngrok http "$TARGET_PORT" --log=stdout </dev/null > /tmp/ngrok.log 2>&1 &
    TUNNEL_PID=$!
    TUNNEL_ENGINE="Ngrok"

    for i in {1..20}; do
        if [ -f /tmp/ngrok.log ]; then
            TUNNEL_URL=$(grep -o 'https://[-a-zA-Z0-9@:%._\+~#=]*\.ngrok-free\.app' /tmp/ngrok.log | tail -n 1 || true)
            if [ -z "$TUNNEL_URL" ]; then
                TUNNEL_URL=$(curl -s http://127.0.0.1:4040/api/tunnels 2>/dev/null | grep -o 'https://[^"]*\.ngrok[^"]*' | head -n 1 || true)
            fi
            if [ -n "$TUNNEL_URL" ]; then
                break
            fi
        fi
        sleep 1
    done
fi

# If neither cloud tunnel succeeded, fallback to local network Wi-Fi link
if [ -z "$TUNNEL_URL" ]; then
    echo "⚠️  Walang cloud tunnel na kumonekta. Gagamitin ang Local Network (Wi-Fi) IP bilang default."
    if [ "$TARGET_PORT" = "80" ]; then
        TUNNEL_URL="http://${LOCAL_IP}"
    else
        TUNNEL_URL="http://${LOCAL_IP}:${TARGET_PORT}"
    fi
    TUNNEL_ENGINE="Local Wi-Fi / LAN"
fi

MAIN_LINK="${TUNNEL_URL}${BASE_PATH}/"
STAFF_LOGIN="${TUNNEL_URL}${BASE_PATH}/staff/login.php"
ADMIN_LOGIN="${TUNNEL_URL}${BASE_PATH}/admin/login.php"
if [ "$TARGET_PORT" = "80" ]; then
    LAN_LINK="http://${LOCAL_IP}${BASE_PATH}/"
else
    LAN_LINK="http://${LOCAL_IP}:${TARGET_PORT}${BASE_PATH}/"
fi

# Write active links only (no credentials/passwords) to LIVE_URL.txt
cat << EOF > "$HOTEL_DIR/LIVE_URL.txt"
============================================================
🏨 MARVELOUS HOTEL & DORMITORY — LIVE LINKS ($TUNNEL_ENGINE)
============================================================
🌐 Main Website (Phone / Laptop / PC): $MAIN_LINK
🛎️ Staff Portal (Laptop / PC Only):    $STAFF_LOGIN
👑 Admin Portal (Laptop / PC Only):    $ADMIN_LOGIN
📶 Local Wi-Fi / LAN:                  $LAN_LINK
============================================================
EOF
chmod 666 "$HOTEL_DIR/LIVE_URL.txt" 2>/dev/null || true

# 9. Display Clean Links + QR Code Only (No Extra Clutter / No Passwords)
clear 2>/dev/null || true
echo "============================================================"
echo "🏨 MARVELOUS HOTEL & DORMITORY — LIVE LINKS ($TUNNEL_ENGINE)"
echo "============================================================"
echo ""
echo "🌐 Main Website (Phone / Laptop / PC):"
echo "   $MAIN_LINK"
echo ""
echo "🛎️ Staff Portal (Laptop / PC Only):"
echo "   $STAFF_LOGIN"
echo ""
echo "👑 Admin Portal (Laptop / PC Only):"
echo "   $ADMIN_LOGIN"
echo ""
if [ "$LOCAL_IP" != "127.0.0.1" ]; then
echo "📶 Local Wi-Fi / LAN:"
echo "   $LAN_LINK"
echo ""
fi

# Print QR code for Main Hotel Website if qrencode is installed
if command -v qrencode &>/dev/null; then
    echo "📱 Scan QR Code (Main Hotel Website):"
    qrencode -t UTF8 "$MAIN_LINK"
fi

echo "============================================================"

if [ "$IS_DAEMON" -eq 1 ]; then
    echo "$TUNNEL_PID" > /tmp/hotel_tunnel.pid
    disown "$TUNNEL_PID" 2>/dev/null || true
    trap - SIGINT SIGTERM
    echo "🚀 Background mode active. Stop: ./share-capstone.sh --stop"
    exit 0
fi

echo "⏳ Live (Press Ctrl+C to stop)"

if [ -n "$TUNNEL_PID" ]; then
    wait "$TUNNEL_PID"
else
    # Keep alive if running purely on LAN
    while true; do sleep 1; done
fi
