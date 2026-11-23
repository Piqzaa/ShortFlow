#!/bin/sh
set -eu

threshold=${STORAGE_WARNING_PERCENT:-80}
case "$threshold" in
    ''|*[!0-9]*) echo 'STORAGE_WARNING_PERCENT doit être un entier de 1 à 100.' >&2; exit 2 ;;
esac
if [ "$threshold" -lt 1 ] || [ "$threshold" -gt 100 ]; then
    echo 'STORAGE_WARNING_PERCENT doit être compris entre 1 et 100.' >&2
    exit 2
fi

report=$(df -Pk /app/var/storage)
used=$(printf '%s\n' "$report" | awk 'NR==2 {sub(/%$/, "", $5); print $5}')
case "$used" in
    ''|*[!0-9]*) echo 'Occupation du volume des médias indisponible.' >&2; exit 2 ;;
esac
printf '%s\n' "$report"
if [ "$used" -ge "$threshold" ]; then
    printf 'Volume des médias à %s%% (seuil %s%%).\n' "$used" "$threshold" >&2
    exit 1
fi
