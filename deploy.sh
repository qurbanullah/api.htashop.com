#!/usr/bin/env bash
#
# Build the API image and roll the Swarm service onto it.
#
# This runs ON THE SWARM MANAGER, normally arriving over stdin from
# .github/workflows/deploy.yml:
#
#   ssh "$USER@$HOST" "cd '$APP_DIR' && bash -s" < deploy.sh
#
# The manager keeps the checkout this builds from; the script updates it first, so
# what is built is what was pushed. The stack itself (networks, secrets, db, redis,
# typesense, haproxy) is deployed separately — see deploy/ — and this only moves one
# service.
#
# Migrations are NOT run here. The image's entrypoint runs them on boot, which
# means each replica does it once at startup; see DEPLOYMENT.md for what that
# implies during a rollout.
#
#   ./deploy.sh --no-build      roll the existing image (already built)
#   ./deploy.sh --no-smoke      skip the post-rollout check
#   ./deploy.sh --help
#
# Environment:
#
#   APP_DIR              checkout to build from (defaults to this script's directory)
#   BRANCH               branch to deploy (default: main)
#   STACK_NAME           (default: htashop)
#   SERVICE_NAME         (default: api)
#   SMOKE_URL            e.g. https://api.htashop.com/up
#
set -euo pipefail

BRANCH="${BRANCH:-main}"
STACK_NAME="${STACK_NAME:-htashop}"
SERVICE_NAME="${SERVICE_NAME:-api}"

SWARM_SERVICE_NAME="${STACK_NAME}_${SERVICE_NAME}"
IMAGE_NAME="${STACK_NAME}-${SERVICE_NAME}:latest"

DO_BUILD=1
DO_SMOKE=1

step()  { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$1"; }
info()  { printf '    %s\n' "$1"; }
warn()  { printf '\033[1;33mwarning:\033[0m %s\n' "$1" >&2; }
die()   { printf '\033[1;31merror:\033[0m %s\n' "$1" >&2; exit 1; }

usage() {
    cat <<'USAGE'
Build the API image and roll the Swarm service onto it.

  --no-build   skip the build and roll the existing image
  --no-smoke   skip the post-rollout smoke test
  -h, --help   show this message

With no options this updates the checkout, builds, rolls the service and checks it.
See DEPLOYMENT.md.
USAGE
    exit 0
}

for argument in "$@"; do
    case "$argument" in
        --no-build) DO_BUILD=0 ;;
        --no-smoke) DO_SMOKE=0 ;;
        -h|--help)  usage ;;
        *)          die "unknown option: $argument (try --help)" ;;
    esac
done

# Where this script lives. It normally arrives over stdin, in which case there is no
# path to derive and the caller is expected to have changed into the checkout.
if [ -n "${BASH_SOURCE[0]:-}" ] && [ -f "${BASH_SOURCE[0]}" ]; then
    SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
else
    SCRIPT_DIR="$PWD"
fi

APP_DIR="${APP_DIR:-$SCRIPT_DIR}"

# ---------------------------------------------------------------------------
# Preflight
#
# Everything checkable is checked before the service is touched: a failed rollout
# costs a rollback and a window of reduced capacity.
# ---------------------------------------------------------------------------

step "Checking the environment"

cd "$APP_DIR" || die "cannot enter $APP_DIR"
[ -f Dockerfile ] || die "$APP_DIR has no Dockerfile, so it is not the build context"
[ -f artisan ]    || die "$APP_DIR does not look like the application (no artisan)"

command -v git >/dev/null 2>&1 || die "git not found"
command -v docker >/dev/null 2>&1 || die "docker not found"

docker info >/dev/null 2>&1 || die "cannot talk to the Docker daemon"

SWARM_STATE="$(docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null || echo unknown)"
[ "$SWARM_STATE" = "active" ] \
    || die "this node is not part of a Swarm ($SWARM_STATE), so there is no service to roll"

# Checked before building: a missing service or a bad NAME would otherwise be
# discovered after several minutes of building.
docker service inspect "$SWARM_SERVICE_NAME" >/dev/null 2>&1 \
    || die "no service named '$SWARM_SERVICE_NAME'.
  The stack must be deployed first (see deploy/), or STACK_NAME/SERVICE_NAME are wrong."

EXPECTED_REPLICAS="$(docker service inspect "$SWARM_SERVICE_NAME" \
    --format '{{.Spec.Mode.Replicated.Replicas}}' 2>/dev/null || echo '?')"

git remote get-url origin >/dev/null 2>&1 \
    || die "$APP_DIR has no 'origin' remote, so it is not the application's checkout"

info "app:     $APP_DIR"
info "service: $SWARM_SERVICE_NAME ($EXPECTED_REPLICAS replicas)"
info "image:   $IMAGE_NAME"
info "branch:  $BRANCH"

# ---------------------------------------------------------------------------
# Code
#
# Fast-forward only. The manager is a build host, not a place to keep local work;
# if it somehow has commits, stop rather than discard something that exists nowhere
# else.
# ---------------------------------------------------------------------------

step "Updating the checkout"

git fetch --prune origin "$BRANCH"

if [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ]; then
    info "already at $(git rev-parse --short HEAD)"
else
    git merge --ff-only "origin/$BRANCH" \
        || die "origin/$BRANCH cannot be fast-forwarded. The checkout has commits that are not on the remote; reconcile them before deploying."
    info "now at $(git log -1 --format='%h %s')"
fi

if [ -n "$(git status --porcelain)" ]; then
    warn "the checkout has uncommitted changes; the image will not match what was pushed"
fi

# ---------------------------------------------------------------------------
# Build
#
# Tagged with the commit as well as 'latest' so a rollback is a one-liner:
#   docker service update --force --image $IMAGE_NAME:<sha> $SWARM_SERVICE_NAME
# ---------------------------------------------------------------------------

SHORT_SHA="$(git rev-parse --short HEAD)"
BUILD_TAG="${STACK_NAME}-${SERVICE_NAME}:${SHORT_SHA}"

if [ "$DO_BUILD" -eq 1 ]; then
    step "Building $BUILD_TAG"

    # No --memory cap: BuildKit ignores it, and on a shared host a fixed 8g ceiling
    # hurts more than it protects.
    docker build -t "$IMAGE_NAME" -t "$BUILD_TAG" .

    info "built $BUILD_TAG"
else
    step "Skipping the build"
    info "rolling $IMAGE_NAME, whatever it currently points at"
fi

# ---------------------------------------------------------------------------
# Roll out
#
# `docker service update` blocks until the rollout converges, and the service's own
# update_config (parallelism 1, delay 30s, monitor 60s, failure_action rollback)
# governs how. A non-zero exit here means Swarm gave up and rolled back.
# ---------------------------------------------------------------------------

step "Rolling $SWARM_SERVICE_NAME"

docker service update --force --image "$IMAGE_NAME" "$SWARM_SERVICE_NAME"

docker service ps "$SWARM_SERVICE_NAME" \
    --filter desired-state=running \
    --format 'table {{.Name}}\t{{.Node}}\t{{.CurrentState}}' \
    || warn "could not read the service's tasks"

RUNNING="$(docker service ps "$SWARM_SERVICE_NAME" \
    --filter desired-state=running --format '{{.CurrentState}}' 2>/dev/null \
    | grep -c '^Running' || true)"

info "$RUNNING of ${EXPECTED_REPLICAS} replicas running"

if [ "$EXPECTED_REPLICAS" != "?" ] && [ "$RUNNING" -lt "$EXPECTED_REPLICAS" ]; then
    die "only $RUNNING of $EXPECTED_REPLICAS replicas are running.
  The rollout reported success but the service is short — check:
    docker service ps $SWARM_SERVICE_NAME
    docker service logs $SWARM_SERVICE_NAME --tail 100"
fi

# ---------------------------------------------------------------------------
# Verify
# ---------------------------------------------------------------------------

if [ "$DO_SMOKE" -eq 1 ] && [ -n "${SMOKE_URL:-}" ]; then
    step "Smoke testing $SMOKE_URL"

    # Retried: the routing mesh and the load balancer in front of it take a moment
    # to notice new tasks, and a single fast failure would be a false alarm.
    SMOKE_OK=0
    for attempt in 1 2 3 4 5; do
        if curl -fsS --max-time 10 -o /dev/null "$SMOKE_URL"; then
            SMOKE_OK=1
            break
        fi

        info "attempt $attempt did not answer; retrying in 5s"
        sleep 5
    done

    if [ "$SMOKE_OK" -eq 1 ]; then
        info "answered"
    else
        warn "$SMOKE_URL did not answer cleanly after 5 attempts"
        warn "the service is running, so this is more likely the proxy than the API:"
        warn "  docker service logs ${STACK_NAME}_haproxy --tail 50"
    fi
elif [ "$DO_SMOKE" -eq 1 ]; then
    info "no SMOKE_URL set, so nothing was checked from outside"
fi

# ---------------------------------------------------------------------------
# Housekeeping
#
# Dangling images only (the previous build), which is what fills a disk on a host
# rebuilt this often. In-use images are never touched, so a rollback target stays.
# ---------------------------------------------------------------------------

step "Pruning dangling images"
docker image prune -f || warn "could not prune images"

step "Deployed ${SWARM_SERVICE_NAME} at ${SHORT_SHA}"
