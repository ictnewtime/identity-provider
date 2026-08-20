#!/usr/bin/env bash
# Confronta la validazione dell'email del frontend con quella del backend, sugli stessi indirizzi.
#
# Perche' esiste (TSR02): il frontend valida con una regex scritta a mano, il backend con la regola
# `email` di Laravel, che senza parametri e' `filter_var(..., FILTER_VALIDATE_EMAIL)`. Sono due
# implementazioni diverse della stessa idea, e divergono in silenzio.
#
# LA DIREZIONE CONTA. Frontend piu' permissivo del backend: l'utente vede l'errore dopo il salvataggio
# invece che accanto al campo — fastidio. Frontend piu' SEVERO del backend: un indirizzo valido non si
# riesce a inserire, e non c'e' modo di aggirarlo dall'interfaccia — difetto. Lo script esce con errore
# solo nel secondo caso, ed elenca il primo.
#
# La regex non e' copiata qui: si legge da UserForm.vue, cosi' il confronto resta vero quando cambia.
#
# Uso:   ./scripts/check-email-validation.sh
#
# COSA NON COPRE: i casi sono quelli scritti qui sotto, scelti sui modi in cui la vecchia regex
# sbagliava. Non e' una prova di equivalenza, e nessuna lista di esempi lo sarebbe.

set -euo pipefail
cd "$(dirname "$0")/.."

SORGENTE="resources/js/components/UserForm.vue"
LAVORO="$(mktemp -d)"
trap 'rm -rf "$LAVORO"' EXIT

cat > "$LAVORO/casi.json" <<'JSON'
["a@b.c","mario.rossi@example.com","x+tag@sub.domain.co.uk","UPPER@EXAMPLE.COM","a_b-c@d-e.fr",
"a@b","a@@b.c","a b@c.de","scrivimi a: a@b.c grazie","  a@b.c","a@b.c  ","a@b.c\ntest","@b.c",
"a@.c","a@b.","a.b.c","","a@b..c","tres@exemple.fr","a@[127.0.0.1]","\"a b\"@c.de","a@b.c,d@e.f"]
JSON

# La regex viva, presa dal componente.
REGEX="$(grep -oE 'const re = /[^;]*/;' "$SORGENTE" | head -1 | sed -E 's|const re = (/.*/);|\1|')"
if [ -z "$REGEX" ]; then
    echo "Non trovo la regex in $SORGENTE: il controllo non puo' dire niente." >&2
    exit 2
fi
echo "Regex del frontend, letta da $SORGENTE:  $REGEX"

cat > "$LAVORO/js.mjs" <<JS
import { readFileSync } from "fs";
const casi = JSON.parse(readFileSync(process.argv[2], "utf-8"));
const re = $REGEX
console.log(JSON.stringify(casi.map((c) => re.test(c))));
JS

cat > "$LAVORO/php.php" <<'PHP'
<?php
$casi = json_decode(file_get_contents($argv[1]), true);
echo json_encode(array_map(fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false, $casi));
PHP

node "$LAVORO/js.mjs" "$LAVORO/casi.json" > "$LAVORO/out-js.json"
docker run --rm -v "$LAVORO":/w -w /w composer:2 php php.php casi.json > "$LAVORO/out-php.json"

python3 - "$LAVORO" <<'PY'
import json
import sys

lavoro = sys.argv[1]
casi = json.load(open(lavoro + "/casi.json"))
frontend = json.load(open(lavoro + "/out-js.json"))
backend = json.load(open(lavoro + "/out-php.json"))

permissivo = [c for c, f, b in zip(casi, frontend, backend) if f and not b]
severo = [c for c, f, b in zip(casi, frontend, backend) if b and not f]

for c in permissivo:
    print("  piu' permissivo del backend: %r — l'errore arriva dopo il salvataggio" % c)
for c in severo:
    print("  PIU' SEVERO del backend: %r — indirizzo valido che non si riesce a inserire" % c)

print("\n%d casi, %d permissivi, %d severi" % (len(casi), len(permissivo), len(severo)))
sys.exit(1 if severo else 0)
PY
