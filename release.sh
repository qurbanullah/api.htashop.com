#!/usr/bin/env bash
#
# Verify the working tree, commit it, and push it.
#
# This runs on YOUR MACHINE. GitHub Actions does the deploying. See
# DEPLOYMENT.md for the server side and the required repository secrets.
#
#   ./release.sh "Add poll support to the assistant"
#   ./release.sh --deploy "Ship the payment gateway"   # pushes main, so it deploys
#   ./release.sh --skip-checks "Fix a typo in the footer copy"
#   ./release.sh --no-push "Groundwork, not ready for production"
#
# The workflow has no test job: these checks are the only ones there are, so
# skipping them ships code that nothing has verified.
#
set -euo pipefail

BRANCH="${BRANCH:-main}"
# Deliberately not defaulted to 'php'. Empty means "find something that can run
# the app" — which on this machine is the API container, not the host.
PHP_BIN="${PHP_BIN:-}"
CONTAINER="${CONTAINER:-htashop-api}"

RUN_CHECKS=1
DO_PUSH=1
DEPLOY=0
MESSAGE=""

step() { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$1"; }
info() { printf '    %s\n' "$1"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$1" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$1" >&2; exit 1; }

usage() {
    cat <<'USAGE'
Verify the working tree, commit it, and push it.

GitHub Actions does the rest: a push to main deploys to production (see
DEPLOYMENT.md). Pushing any other branch does not.

  --deploy        push to main, which starts the deploy
  --skip-checks   push without running the checks. Nothing else will run them
  --no-push       commit only
  -h, --help      show this message

Without --deploy the current branch is pushed; a commit message is required when
the tree has changes.
USAGE
    exit 0
}

for argument in "$@"; do
    case "$argument" in
        --deploy)      DEPLOY=1 ;;
        --skip-checks) RUN_CHECKS=0 ;;
        --no-push)     DO_PUSH=0 ;;
        -h|--help)     usage ;;
        -*)            die "unknown option: $argument (try --help)" ;;
        *)             MESSAGE="$argument" ;;
    esac
done

if [ "$DEPLOY" -eq 1 ] && [ "$DO_PUSH" -eq 0 ]; then
    die "--deploy and --no-push contradict each other (--deploy pushes to $BRANCH)"
fi

cd "$(dirname -- "${BASH_SOURCE[0]:-$0}")"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not a git repository"

# ---------------------------------------------------------------------------
# Checks
#
# The suite needs a PHP that can boot this app *and* reach its database. On this
# machine .env points at the docker-internal database host, which the host cannot
# resolve, so the container is the right place to run them. Set PHP_BIN to use a
# host PHP instead.
# ---------------------------------------------------------------------------

run_app() {
    if [ -n "$PHP_BIN" ]; then
        "$PHP_BIN" "$@"
    else
        # Not `docker compose exec -T': `-T' is a compose flag, and `docker exec'
        # has no equivalent (no TTY is allocated unless -t is passed).
        docker exec "$CONTAINER" php "$@"
    fi
}

if [ "$RUN_CHECKS" -eq 1 ]; then
    if [ -n "$PHP_BIN" ]; then
        command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP binary '$PHP_BIN' not found"
    else
        docker exec "$CONTAINER" php -v >/dev/null 2>&1 \
            || die "cannot run PHP in the '$CONTAINER' container.
  Start it (docker compose up -d), or set PHP_BIN to a host PHP that can reach
  the database."

        # Pint's --dirty asks git what changed, and inside the container git
        # refuses the mounted tree ("dubious ownership": the host owns it, the
        # container runs as root). Pint then reports "0 files" and passes without
        # checking anything — a gate that always says yes. This is git's own
        # suggested fix, and it is idempotent.
        docker exec "$CONTAINER" \
            git config --global --add safe.directory "${CONTAINER_APP_DIR:-/var/www/html}" \
            >/dev/null 2>&1 || true
    fi

    # Only the files this release touches. The codebase predates Pint, and the
    # 898 files a full --test reports make it useless as a gate: it would block
    # every release instead of catching new mistakes. Reformatting everything is
    # a change of its own, not a side effect of shipping.
    step "PHP style (files in this release)"
    if ! run_app vendor/bin/pint --test --dirty; then
        die "the files above are not formatted.
  Fix them with: docker exec $CONTAINER php vendor/bin/pint --dirty
  (or, on a host PHP: php vendor/bin/pint --dirty)"
    fi

    step "Test suite"
    run_app artisan test
else
    warn "checks skipped"
    warn "nothing else runs them: pushing to $BRANCH deploys straight to production"
fi

# ---------------------------------------------------------------------------
# Commit and push
#
# Deliberately explicit: a message means "commit everything", no message means
# the tree must already be clean. Nothing is committed by accident.
# ---------------------------------------------------------------------------

step "Publishing to GitHub"

if [ -n "$(git status --porcelain)" ]; then
    if [ -z "$MESSAGE" ]; then
        printf '\n'
        git status --short
        die "the tree has changes. Pass a commit message ('./release.sh \"...\"'), or commit them yourself."
    fi

    info "staging:"
    git status --short | sed 's/^/        /'

    git add -A
    git commit -m "$MESSAGE"
else
    info "nothing to commit"
fi

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
COMMIT="$(git log -1 --format='%h %s')"

if [ "$DO_PUSH" -eq 0 ]; then
    step "Committed, not pushed"
    info "$COMMIT"
    exit 0
fi

# ---------------------------------------------------------------------------
# Push
#
# HEAD:$BRANCH pushes whatever is checked out, from any branch, so --deploy works
# the same whether you are on main or not. Git refuses a non-fast-forward itself,
# which is the wanted behaviour: a diverged main stops here rather than being
# rewritten.
# ---------------------------------------------------------------------------

if [ "$DEPLOY" -eq 1 ]; then
    step "Pushing to $BRANCH"
    git push origin "HEAD:$BRANCH"
    info "$COMMIT"

    if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
        info "your local $CURRENT_BRANCH did not move; 'git fetch' updates $BRANCH"
    fi
else
    step "Pushing $CURRENT_BRANCH"
    git push origin "$CURRENT_BRANCH"
    info "$COMMIT"
fi

# ---------------------------------------------------------------------------
# What happens next
# ---------------------------------------------------------------------------

step "Pushed"

DEPLOYS=0
if [ "$DEPLOY" -eq 1 ] || [ "$CURRENT_BRANCH" = "$BRANCH" ]; then
    DEPLOYS=1
fi

if [ "$DEPLOYS" -eq 0 ]; then
    info "'$CURRENT_BRANCH' is not '$BRANCH', so nothing is deployed."
    info "re-run with --deploy, or open a pull request into $BRANCH, to deploy."
    exit 0
fi

warn "$BRANCH deploys to production; the workflow is starting"

REMOTE_URL="$(git config --get remote.origin.url 2>/dev/null || true)"

case "$REMOTE_URL" in
    git@github.com:*)     REPO_PATH="${REMOTE_URL#git@github.com:}" ;;
    https://github.com/*) REPO_PATH="${REMOTE_URL#https://github.com/}" ;;
    *)                    REPO_PATH="" ;;
esac

if [ -n "$REPO_PATH" ]; then
    info "watch it at https://github.com/${REPO_PATH%.git}/actions"
fi
