#!/bin/sh
# Avvia il server di sviluppo di vite con l'utente che possiede l'albero montato.
#
# Perche' esiste (punto TSR13): vite resta in piedi tutto il giorno e riscrive nell'albero a ogni
# salvataggio di un file .vue. Avviato come root — com'era fino al 2026-08-21 — lasciava file di root
# nell'albero di chi sviluppa, e da quel momento quei file non si riscrivono e non si cancellano piu':
# e' l'EACCES di `npm run build`, difetto BDB32.
#
# PERCHE' L'UID SI RICAVA QUI E NON SI PASSA. Il `docker-compose.yml` non puo' calcolarlo: la sua
# interpolazione legge variabili, non esegue comandi. Passarlo vorrebbe dire scriverlo in un file
# (`.env`) o esportarlo prima di ogni `docker compose up` — due cose da ricordare, e un valore fisso
# scritto da qualche parte e' giusto su una macchina e sbagliato sulla successiva.
#
# Il proprietario di /var/www invece **e' il dato**: quell'albero e' la cartella del progetto sull'host,
# montata dentro. Chi la possiede e' esattamente l'utente che deve poter riscrivere cio' che vite
# genera. Niente da configurare, e funziona identico sulla macchina di un altro.
#
# Se un giorno l'albero risultasse di root, vite girerebbe come root: sarebbe il comportamento di
# prima, non un peggioramento.
set -eu

UID_ALBERO=$(stat -c %u /var/www)
GID_ALBERO=$(stat -c %g /var/www)

echo "vite parte come uid=${UID_ALBERO} gid=${GID_ALBERO} (proprietario di /var/www)"

# `--clear-groups`: senza questo il processo terrebbe i gruppi supplementari di root.
exec setpriv --reuid="$UID_ALBERO" --regid="$GID_ALBERO" --clear-groups npm run dev
