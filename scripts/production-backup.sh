#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
remote=${VYDFLOW_BACKUP_REMOTE:?Set VYDFLOW_BACKUP_REMOTE to remote:bucket/prefix}
remote=${remote%/}
if [[ ! $remote =~ ^[[:alnum:]_-]+:[^/:][^:]*$ ]]; then
    printf 'Configure a named rclone remote and a destination path.\n' >&2
    exit 2
fi
files=(database.dump.age media.tar.age SHA256SUMS)
writers=()
stage=

compose() {
    docker compose --project-directory "$root" \
        --env-file "${VYDFLOW_ENV_FILE:-$root/.env.production.local}" \
        -f "$root/compose.production.yaml" "$@"
}

resume_writers() {
    if (("${#writers[@]}")); then
        compose start "${writers[@]}" || return
        writers=()
    fi
}

clean_stage() {
    local code=$?
    trap - EXIT
    if ! resume_writers; then
        printf 'Unable to restart the services stopped for backup. Check Docker Compose.\n' >&2
        code=1
    fi
    if [[ -n $stage ]]; then
        rm -f -- "${files[@]/#/$stage/}"
        rmdir -- "$stage"
    fi
    exit "$code"
}
trap clean_stage EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

valid_id() {
    [[ $1 =~ ^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{8}$ ]]
}

checksums() {
    (cd -- "$1" && sha256sum -- "${files[0]}" "${files[1]}")
}

verify() {
    local actual
    actual=$(checksums "$1") || return
    if [[ $actual != "$(<"$1/SHA256SUMS")" ]]; then
        printf 'Backup checksum mismatch in %s\n' "$1" >&2
        return 1
    fi
}

send_backup() {
    local directory=$1 id=${1##*/} file
    verify "$directory" || return
    # The manifest is copied last. It marks a pair that can be restored.
    for file in "${files[@]}"; do
        rclone copyto --immutable --checksum "$directory/$file" "$remote/$id/$file"
    done
    rm -- "${files[@]/#/$directory/}"
    rmdir -- "$directory"
    printf 'Encrypted backup transferred: %s\n' "$id"
}

backup() {
    local spool=${VYDFLOW_BACKUP_SPOOL:?Set a private backup spool directory}
    local recipient=${VYDFLOW_BACKUP_RECIPIENT:?Set an age public recipient}
    local running directory service id
    mkdir -p -- "$spool"
    exec 9>"$spool/.lock"
    if ! flock -n 9; then
        printf 'A backup is already running.\n' >&2
        return 1
    fi
    for directory in "$spool"/*; do
        if [[ -d $directory ]] && valid_id "${directory##*/}"; then
            send_backup "$directory"
            return
        fi
    done

    running=$(compose ps --status running --services)
    if ! grep -Fxq database <<< "$running"; then
        printf 'The production database is not running.\n' >&2
        return 1
    fi
    id="$(date -u +%Y%m%dT%H%M%SZ)-$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')"
    stage=$(mktemp -d "$spool/.partial-XXXXXXXX")
    for service in ingress publications background video-edits retention web; do
        if grep -Fxq "$service" <<< "$running"; then
            writers+=("$service")
        fi
    done
    if (("${#writers[@]}")); then
        compose stop "${writers[@]}"
    fi
    compose exec -T database pg_dump -U vydflow -d vydflow -Fc --no-owner --no-privileges \
        | age -r "$recipient" -o "$stage/database.dump.age"
    compose run --rm --no-deps -T --entrypoint tar web -C /app/var/storage -cf - . \
        | age -r "$recipient" -o "$stage/media.tar.age"
    resume_writers

    checksums "$stage" > "$stage/SHA256SUMS"
    directory="$spool/$id"
    mv -T -- "$stage" "$directory"
    stage=
    send_backup "$directory"
}

fetch_backup() {
    local id=$1 destination=$2 file
    if ! valid_id "$id" || [[ -e $destination || -L $destination ]]; then
        printf 'Provide a valid backup ID and a new destination directory.\n' >&2
        return 1
    fi
    stage=$(mktemp -d "$(dirname -- "$destination")/.vydflow-restore-XXXXXXXX")
    rclone copyto "$remote/$id/SHA256SUMS" "$stage/SHA256SUMS"
    for file in "${files[@]:0:2}"; do
        rclone copyto "$remote/$id/$file" "$stage/$file"
    done
    verify "$stage" || return
    mv -T -- "$stage" "$destination"
    stage=
    printf 'Encrypted backup verified: %s\n' "$destination"
}

case "${1:-}" in
    backup) [[ $# == 1 ]] && backup ;;
    fetch) [[ $# == 3 ]] && fetch_backup "$2" "$3" ;;
    *) printf 'Usage: %s backup | fetch ID NEW_DIRECTORY\n' "$0" >&2; exit 2 ;;
esac
