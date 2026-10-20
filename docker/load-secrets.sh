#!/usr/bin/env bash
#
# Hydrate Docker-secret indirections: <NAME>_FILE=/path -> <NAME>=<contents>.
#
# The stack passes every credential as a Docker secret behind a *_FILE variable
# (see docker-swarm.yml), so no secret is baked into the image or written into
# the stack file. Something has to turn those paths into the plain variables
# Laravel reads: the container entrypoint does that for its own process tree,
# which covers PID 1 and everything it starts (php-fpm, queue:work,
# schedule:work).
#
# `docker exec` does NOT inherit that runtime environment. It starts a new
# process from the container's configured environment, which only has the
# *_FILE paths - so an ad-hoc `docker exec ... php artisan ...` fails with
# "NOAUTH Authentication required" (Redis) or "Access denied" (MariaDB).
# Hydrate first:
#
#   docker exec <container> bash -c \
#     'source /usr/local/bin/load-secrets; php artisan queue:monitor redis:default'
#
# Sourcing is required: executing this file would export into a child shell and
# change nothing. It is idempotent and safe to source more than once.
#
# Behaviour:
#   - only [A-Z0-9_]*_FILE variables are considered;
#   - exactly one trailing newline is stripped (Docker secrets usually end with
#     one), matching the previous entrypoint implementation;
#   - unreadable or missing files are skipped instead of aborting, so a
#     partially provisioned container still starts and fails on first use.

if [ -z "${BASH_VERSION:-}" ]; then
    printf 'load-secrets: must be sourced from bash (not sh)\n' >&2
    return 1 2>/dev/null || exit 1
fi

load_secrets() {
    local entry name file value

    while IFS= read -r entry; do
        name="${entry%_FILE}"
        file="${!entry}"
        [[ -n "${file}" && -r "${file}" ]] || continue
        value="$(cat "${file}")"
        value="${value%$'\n'}"
        export "${name}=${value}"
        printf '[secrets] loaded %s from %s\n' "${name}" "${file}"
    done < <(env | sed -n 's/^\([A-Z0-9_]*_FILE\)=.*/\1/p')
}

load_secrets
