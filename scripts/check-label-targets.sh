#!/usr/bin/env bash
# Verifica che ogni `<label for="X">` del frontend punti a un elemento che puo' prendere il fuoco.
#
# Il difetto che corregge (VDF25): `for` funziona solo verso un elemento *labelable* — input,
# select, textarea, button. I componenti PrimeVue mettono l'attributo `id` sul CONTENITORE, che e'
# un div (uno span per DatePicker); l'elemento che prende il fuoco sta dentro e con `id` non ha id.
# L'etichetta resta quindi collegata a niente: cliccarla non fa nulla e un lettore di schermo non
# legge il nome del campo. Il modo giusto e' `inputId`, che il resto del progetto usa e che porta
# l'id sull'input vero.
#
# `Select` e' l'eccezione: non ha un input, e nemmeno `inputId` lo rende labelable perche' finisce su
# uno span. La' l'associazione si fa con `aria-labelledby` verso l'id dell'etichetta, e il controllo
# la accetta solo se quell'etichetta esiste: un `aria-labelledby` che nomina un id inesistente lascia
# il campo senza nome e non lo dice a nessuno.
#
# LA REGOLA: se il `for` di una label combacia con un `id="..."` dichiarato su uno dei componenti
# elencati in CONTENITORI, o se non combacia con nessun id del file, e' un rilievo.
#
# Uso:   ./scripts/check-label-targets.sh        elenco, exit 1 se trova qualcosa
#
# COSA NON COPRE: non verifica che un `aria-labelledby` punti a un'etichetta esistente, e non
# guarda i componenti di terze parti diversi da PrimeVue.

set -euo pipefail
cd "$(dirname "$0")/.."

python3 - <<'PY'
import glob
import re
import sys

# Componenti che mettono `id` sul contenitore invece che sull'elemento focalizzabile.
# Misurato con vue/server-renderer, non dedotto: vedi VDF25.
CONTENITORI = {
    "Select",
    "MultiSelect",
    "Password",
    "ToggleSwitch",
    "DatePicker",
    "LocalizedDatePicker",
    "Checkbox",
    "RadioButton",
}

rilievi = []

for percorso in sorted(glob.glob("resources/js/**/*.vue", recursive=True)):
    testo = open(percorso, encoding="utf-8").read()
    linee = testo.split("\n")

    for numero, linea in enumerate(linee, start=1):
        etichetta = re.search(r'<label[^>]*\bfor="([^"]+)"', linea)
        if not etichetta:
            continue

        bersaglio = etichetta.group(1)
        virgolette = re.escape(bersaglio)

        # `inputId` e' la forma giusta: se c'e', l'id sta sull'input vero.
        if re.search(r':?inputId="%s"' % virgolette, testo):
            continue

        # Su quale componente e' dichiarato quell'id?
        componente = re.search(r'<([A-Za-z][A-Za-z0-9]*)\b[^>]*?\bid="%s"' % virgolette, testo, re.S)

        if not componente:
            rilievi.append((percorso, numero, bersaglio, "nessun elemento porta questo id"))
            continue

        nome = componente.group(1)
        if nome not in CONTENITORI:
            continue

        # `Select` non ha un input: nemmeno `inputId` lo rende labelable. La' l'unica associazione
        # possibile e' `aria-labelledby` verso l'id dell'etichetta, e va verificata su entrambi i
        # lati — un `aria-labelledby` che nomina un'etichetta inesistente e' peggio di niente.
        aria = re.search(
            r'<%s\b[^>]*?\b:?aria-labelledby="([^"]+)"' % nome, testo, re.S
        )
        if aria:
            atteso = aria.group(1)
            if re.search(r'<label[^>]*\bid="%s"' % re.escape(atteso), testo):
                continue
            rilievi.append(
                (percorso, numero, bersaglio, "aria-labelledby nomina `%s`, che nessuna label porta" % atteso)
            )
            continue

        rilievi.append((percorso, numero, bersaglio, "%s mette l'id sul contenitore" % nome))

for percorso, numero, bersaglio, motivo in rilievi:
    print('%s:%d  for="%s"  — %s' % (percorso, numero, bersaglio, motivo))

if rilievi:
    print("\nEtichette non collegate: %d (VDF25)" % len(rilievi))
    sys.exit(1)

print("Etichette ok: ogni `for` punta a un elemento che prende il fuoco")
PY
