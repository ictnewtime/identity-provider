#!/usr/bin/env bash
# Smoke test post-deploy — automatizza i passi 2, 3 e 8 di docs/ai/manual-tests-custom.md
#
# SOLO LETTURE: nessuna pubblicazione, nessun purge, nessuna scrittura verso i CRM (R1 della
# meta-doc). Si può eseguire in sicurezza su stage e produzione.
#
# Uso:   ./scripts/smoke-test.sh
# Exit:  0 = tutti i controlli passati · 1 = almeno un controllo fallito
#
# Configurazione (tutte con default, si sovrascrivono da ambiente):
#   SMOKE_CONTAINERS   container che devono essere Up
#   SMOKE_RABBIT       container del broker
#   SMOKE_QUEUE_REQ    coda che deve avere almeno un consumer
#   SMOKE_QUEUE_DLQ    coda che non deve crescere
#   SMOKE_MAX_RESTARTS soglia oltre la quale il restart count è un crash-loop
#   SMOKE_MAX_DLQ      messaggi tollerati in dlq prima di fallire

set -uo pipefail

CONTAINERS=${SMOKE_CONTAINERS:-"rabbitmq-lead consumer-lead consumer-lead-dlx"}
RABBIT=${SMOKE_RABBIT:-rabbitmq-lead}
QUEUE_REQ=${SMOKE_QUEUE_REQ:-lead-req}
QUEUE_DLQ=${SMOKE_QUEUE_DLQ:-lead-dlq}
MAX_RESTARTS=${SMOKE_MAX_RESTARTS:-3}
MAX_DLQ=${SMOKE_MAX_DLQ:-0}

failed=0
warned=0

ok()   { printf '  \033[32m✓\033[0m %s\n' "$1"; }
ko()   { printf '  \033[31m✗\033[0m %s\n' "$1"; failed=$((failed + 1)); }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; warned=$((warned + 1)); }

echo "== 1. Container attivi =="
for c in $CONTAINERS; do
    status=$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null)
    if [ -z "$status" ]; then
        ko "$c: non esiste"
        continue
    fi
    if [ "$status" != "running" ]; then
        ko "$c: stato '$status'"
        continue
    fi
    # Un container vivo può comunque essere in crash-loop: il segnale è il conteggio dei riavvii.
    restarts=$(docker inspect -f '{{.RestartCount}}' "$c" 2>/dev/null)
    if [ "${restarts:-0}" -gt "$MAX_RESTARTS" ]; then
        ko "$c: running ma $restarts riavvii (soglia $MAX_RESTARTS) — crash-loop"
    else
        ok "$c: running, $restarts riavvii"
    fi
done

echo "== 2. Plugin del broker =="
plugins=$(docker exec "$RABBIT" rabbitmq-plugins list -e 2>/dev/null)
if [ -z "$plugins" ]; then
    ko "$RABBIT: impossibile leggere l'elenco dei plugin"
else
    for p in rabbitmq_shovel rabbitmq_shovel_management; do
        if echo "$plugins" | grep -q "$p"; then
            ok "$p abilitato"
        else
            ko "$p non abilitato: il drenaggio del retry non funziona"
        fi
    done
fi

echo "== 3. Code =="
queues=$(docker exec "$RABBIT" rabbitmqctl -q list_queues name messages consumers 2>/dev/null)
if [ -z "$queues" ]; then
    ko "$RABBIT: impossibile leggere le code"
else
    req_line=$(echo "$queues" | awk -v q="$QUEUE_REQ" '$1 == q')
    if [ -z "$req_line" ]; then
        ko "$QUEUE_REQ: la coda non esiste"
    else
        consumers=$(echo "$req_line" | awk '{print $3}')
        if [ "${consumers:-0}" -ge 1 ]; then
            ok "$QUEUE_REQ: $consumers consumer connessi"
        else
            ko "$QUEUE_REQ: nessun consumer — i lead si accumulano"
        fi
    fi

    dlq_line=$(echo "$queues" | awk -v q="$QUEUE_DLQ" '$1 == q')
    if [ -z "$dlq_line" ]; then
        warn "$QUEUE_DLQ: la coda non esiste ancora (nessun errore finora)"
    else
        dlq=$(echo "$dlq_line" | awk '{print $2}')
        if [ "${dlq:-0}" -gt "$MAX_DLQ" ]; then
            ko "$QUEUE_DLQ: $dlq messaggi (soglia $MAX_DLQ) — ci sono lead non scritti sul CRM"
        else
            ok "$QUEUE_DLQ: $dlq messaggi"
        fi
    fi
fi

echo
if [ "$failed" -gt 0 ]; then
    echo "SMOKE TEST FALLITO: $failed controlli KO, $warned avvisi"
    exit 1
fi
echo "SMOKE TEST OK: nessun controllo fallito, $warned avvisi"
