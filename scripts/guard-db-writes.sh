#!/usr/bin/env bash
# Nega le SCRITTURE sui database, lasciando passare le letture. Dà a R1 il sostegno tecnico che ha
# già su git (docs/ai/full/backlog.md, AVU01 — il difetto più grave aperto).
#
# Perché non basta la deny-list: `permissions.deny` confronta pattern letterali di comando. Un
# `sqlite3 db.sqlite3 "INSERT INTO ..."` non somiglia a nessuno dei pattern e passa. Qui si guarda
# DENTRO il comando.
#
# Hook: PreToolUse, matcher Bash. Legge il JSON dell'evento su stdin.
#
# SCELTA DICHIARATA — questo controllo FALLISCE APERTO: se non riesce a leggere l'input, se `jq`
# manca, se qualcosa va storto, **lascia passare**. È uno strato di difesa in più sopra la
# deny-list, non l'unico: un controllo fail-closed su ogni comando Bash renderebbe la sessione
# inutilizzabile al primo bug, e il rimedio sarebbe spegnerlo — cioè perderlo del tutto.
# La conseguenza va detta: R1 resta **presidiata, non garantita**.
#
# PORTING: WRITE_SQL e WRITE_CMD sono gli unici elenchi da adattare.

set -uo pipefail

# Verbi che scrivono davvero. `SELECT`, `.schema`, `.tables`, `COUNT` non ci sono ed è voluto.
WRITE_SQL='INSERT|UPDATE|DELETE|TRUNCATE|DROP|ALTER|CREATE[[:space:]]+TABLE|REPLACE[[:space:]]+INTO'

# Comandi che scrivono come effetto, anche quando la SQL non si vede: migrazioni, seed, e i processi
# applicativi che producono righe "solo per vedere cosa fa" (R1, § I database).
WRITE_CMD='artisan[[:space:]]+migrate|artisan[[:space:]]+db:seed|db:wipe|migrate:(fresh|refresh|reset|rollback)|knex[[:space:]]+migrate|sequelize[[:space:]]+db:migrate'

input=$(cat 2>/dev/null) || exit 0
[ -n "$input" ] || exit 0

# Estrae il comando senza dipendere da jq: se la forma non è quella attesa, si lascia passare.
cmd=$(printf '%s' "$input" | sed -n 's/.*"command"[[:space:]]*:[[:space:]]*"\(.*\)".*/\1/p' | head -1)
[ -n "$cmd" ] || exit 0

deny() {
    printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"%s"}}\n' "$1"
    exit 0
}

# 1. SQL di scrittura passata a un client di database.
if printf '%s' "$cmd" | grep -qiE '(sqlite3|mysql|mariadb|psql)([[:space:]]|$)' \
   && printf '%s' "$cmd" | grep -qiE "$WRITE_SQL"; then
    deny "R1: scrittura su database. Le letture (SELECT, .schema, conteggi) sono libere; le scritture richiedono un via libera esplicito del developer, volta per volta. Mostra il comando invece di eseguirlo."
fi

# 2. Migrazioni e seed: cambiano lo schema, e uno schema a metà è peggio di un dato cancellato.
if printf '%s' "$cmd" | grep -qiE "$WRITE_CMD"; then
    deny "R1: migrazione o seed. Cambiano lo schema, e valgono come scrittura anche quando non cancellano nulla. Serve l'approvazione esplicita del developer per questa volta."
fi

# 3. Redirezioni che sovrascrivono un file di database.
if printf '%s' "$cmd" | grep -qE '>[[:space:]]*[^|;]*\.(sqlite3?|db)([[:space:]]|$)'; then
    deny "R1: sovrascrittura di un file di database."
fi

exit 0
