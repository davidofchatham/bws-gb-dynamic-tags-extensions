#!/usr/bin/env bash
#
# core-structures — page-snapshot check, the wp-litespeed env's drift gate.
#
# A thin wrapper over tools/test/page-snapshots.php, which owns what a snapshot
# compares and what a clean run proves (docs/testbed.md, docs/update-triggers.md).
# This file only answers "which URL, and from where". The env's --isolation run
# finds it by presence and runs it after seeding the whole family and after each
# --only scope, against the one committed baseline each time. It exits nonzero on
# drift and on any page it cannot fetch.
#
# WHERE THE PHP RUNS. In the env's wpcli container, not on the host: the host
# shell this runs in (WSL) has no php, and the container resolves every site
# domain to the web container through Docker's own DNS, so no hosts-file entry is
# needed. The repo is mounted read-only at /plugins/<repo>, which is all a
# comparison needs (a --capture writes, and is a host-side job: see docs/testbed.md).
#
# WHICH URL. The first vhDomain of the site's vhost block in the OLS config, the
# source bin/smoke.sh and the sibling render-surface.sh scripts read, so the URL
# cannot drift from what the server serves. Not the site's own `home` option: a
# page is not reachable on every host of a multi-domain site.
#
# Usage:
#   tools/fixtures/core-structures/snapshot-check.sh --site testbed [--env-root <path>]
#
# --env-root falls back to $WP_LITESPEED_ROOT, then ~/wp-litespeed. Precondition:
# the family is seeded and the committed baseline is in place.
#
set -euo pipefail

# Git Bash (MSYS2) rewrites POSIX-looking args into Windows paths before exec,
# mangling container paths. Disable for this script.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

SITE=''
ENV_ROOT="${WP_LITESPEED_ROOT:-${HOME}/wp-litespeed}"

err(){ echo -e "\033[31m[X]\033[0m ${*}" >&2; exit 1; }

while [ ${#} -gt 0 ]; do
    case "${1}" in
        -s|--site)  shift; SITE="${1:-}" ;;
        --env-root) shift; ENV_ROOT="${1:-}" ;;
        -h|--help)  echo "Usage: snapshot-check.sh --site <site> [--env-root <path>]"; exit 0 ;;
        *) err "Unknown option: ${1}" ;;
    esac
    shift
done

[ -n "${SITE}" ]     || err "--site is required"
[ -d "${ENV_ROOT}" ] || err "env root not found: ${ENV_ROOT} (pass --env-root or set WP_LITESPEED_ROOT)"

# The directory name is the repo's mount name under /plugins.
REPO="$(basename "$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)")"

cd "${ENV_ROOT}"

DOMAINS=$(docker compose exec -T litespeed \
    bash -c "sed -n '/^  member ${SITE} {/,/^  }/p' /usr/local/lsws/conf/httpd_config.conf | grep vhDomain | awk '{print \$2}'" \
    2>/dev/null | tr -d '\r' || true)
[ -n "${DOMAINS}" ] || err "no vhost for site '${SITE}'"
MAIN="${DOMAINS%%,*}"

exec docker compose run --rm --no-deps -T --entrypoint php wpcli \
    "/plugins/${REPO}/tools/test/page-snapshots.php" "--base-url=https://${MAIN}"
