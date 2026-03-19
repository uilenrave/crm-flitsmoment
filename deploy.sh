#!/bin/bash
# ─────────────────────────────────────────────────────────────
# deploy.sh — Veilig deployen naar crm.flitsmoment.nl
#
# Gebruik:
#   ./deploy.sh          → deploy + backup + migrate
#   ./deploy.sh --backup → alleen backup maken (geen deploy)
#   ./deploy.sh --pull   → productie-DB lokaal importeren
# ─────────────────────────────────────────────────────────────

set -e  # stop bij elke fout

# ── Configuratie ──────────────────────────────────────────────
SSH_HOST="212.107.16.104"
SSH_PORT="65002"
SSH_USER="u493340040"
SSH_PASS="gF%#irdVon84N6"
REMOTE_BASE="/home/u493340040/domains/crm.flitsmoment.nl/laravel"
PHP="/opt/alt/php84/usr/bin/php"

DB_NAME="u493340040_crm_fm"
DB_USER="u493340040_crm_fm"
DB_PASS="^rg!HKaX8~"

LOCAL_PROJECT="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="$LOCAL_PROJECT/.backups"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M")

SSH_CMD="sshpass -p '$SSH_PASS' ssh -p $SSH_PORT -o StrictHostKeyChecking=no -o PreferredAuthentications=password -o PubkeyAuthentication=no $SSH_USER@$SSH_HOST"
SCP_CMD="sshpass -p '$SSH_PASS' scp -P $SSH_PORT -o StrictHostKeyChecking=no -o PreferredAuthentications=password -o PubkeyAuthentication=no"

# ─────────────────────────────────────────────────────────────
backup() {
    mkdir -p "$BACKUP_DIR"
    echo "📦 Backup maken van productie-database..."

    eval $SSH_CMD "mysqldump -u '$DB_USER' -p'$DB_PASS' '$DB_NAME' --single-transaction --quick" \
        > "$BACKUP_DIR/db_$TIMESTAMP.sql"

    # Comprimeer
    gzip "$BACKUP_DIR/db_$TIMESTAMP.sql"
    echo "✅ Backup opgeslagen: .backups/db_$TIMESTAMP.sql.gz"

    # Bewaar alleen de laatste 10 backups
    ls -t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
}

# ─────────────────────────────────────────────────────────────
deploy_files() {
    echo ""
    echo "🚀 Bestanden deployen..."

    # App code
    eval $SCP_CMD -r \
        "$LOCAL_PROJECT/app" \
        "$LOCAL_PROJECT/routes" \
        "$LOCAL_PROJECT/config" \
        "$LOCAL_PROJECT/database" \
        "$LOCAL_PROJECT/resources" \
        "$SSH_USER@$SSH_HOST:$REMOTE_BASE/"

    # Composer autoloader opnieuw genereren als composer.json gewijzigd
    eval $SCP_CMD \
        "$LOCAL_PROJECT/composer.json" \
        "$LOCAL_PROJECT/composer.lock" \
        "$SSH_USER@$SSH_HOST:$REMOTE_BASE/"

    echo "✅ Bestanden gedeployed"
}

# ─────────────────────────────────────────────────────────────
run_migrations() {
    echo ""
    echo "🗄️  Pending migrations controleren..."

    PENDING=$(eval $SSH_CMD "$PHP $REMOTE_BASE/artisan migrate:status --pending 2>/dev/null | grep -c 'Pending' || echo '0'")

    if [ "$PENDING" -gt 0 ] 2>/dev/null; then
        echo "⚠️  Er zijn $PENDING pending migrations."
        read -p "   Migrations uitvoeren? (j/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Jj]$ ]]; then
            eval $SSH_CMD "$PHP $REMOTE_BASE/artisan migrate --force"
            echo "✅ Migrations uitgevoerd"
        else
            echo "⚠️  Migrations overgeslagen — controleer of de code compatible is!"
        fi
    else
        echo "✅ Geen pending migrations"
    fi
}

# ─────────────────────────────────────────────────────────────
clear_caches() {
    echo ""
    echo "🧹 Caches wissen..."
    eval $SSH_CMD "$PHP $REMOTE_BASE/artisan view:clear && $PHP $REMOTE_BASE/artisan config:clear && $PHP $REMOTE_BASE/artisan route:clear"
    echo "✅ Caches geleegd"
}

# ─────────────────────────────────────────────────────────────
pull_production_db() {
    echo "⬇️  Productie-database lokaal importeren..."

    # Lees lokale .env
    LOCAL_DB=$(grep '^DB_DATABASE=' "$LOCAL_PROJECT/.env" | cut -d'=' -f2)
    LOCAL_USER=$(grep '^DB_USERNAME=' "$LOCAL_PROJECT/.env" | cut -d'=' -f2)
    LOCAL_PASS=$(grep '^DB_PASSWORD=' "$LOCAL_PROJECT/.env" | cut -d'=' -f2)

    if [ -z "$LOCAL_DB" ]; then
        echo "❌ Geen lokale .env gevonden of DB_DATABASE niet ingesteld"
        exit 1
    fi

    DUMP_FILE="/tmp/crm_prod_$TIMESTAMP.sql"

    echo "   Dump ophalen van productie..."
    eval $SSH_CMD "mysqldump -u '$DB_USER' -p'$DB_PASS' '$DB_NAME' --single-transaction --quick" > "$DUMP_FILE"

    echo "   Importeren in lokale database: $LOCAL_DB"
    mysql -u "$LOCAL_USER" ${LOCAL_PASS:+-p"$LOCAL_PASS"} "$LOCAL_DB" < "$DUMP_FILE"
    rm "$DUMP_FILE"

    echo "✅ Productie-data staat nu lokaal in: $LOCAL_DB"
    echo "   Je kunt nu lokaal testen met echte data"
}

# ─────────────────────────────────────────────────────────────
# Main
case "${1:-}" in
    --backup)
        backup
        ;;
    --pull)
        pull_production_db
        ;;
    *)
        echo "═══════════════════════════════════════════"
        echo "  Deploy → crm.flitsmoment.nl"
        echo "  $(date '+%d-%m-%Y %H:%M')"
        echo "═══════════════════════════════════════════"

        backup
        deploy_files
        run_migrations
        clear_caches

        echo ""
        echo "═══════════════════════════════════════════"
        echo "  ✅ Deploy geslaagd!"
        echo "═══════════════════════════════════════════"
        ;;
esac
