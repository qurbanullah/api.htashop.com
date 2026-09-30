# Deploying the API

The API runs as a **Docker Swarm service**: 3 replicas of `htashop-api`, behind the
stack's HAProxy, on the Contabo VPS. The storefront, admin and manage panels are
separate applications with their own pipelines.

| Where | What |
|---|---|
| Your machine | `./release.sh` — runs the checks, commits, pushes |
| GitHub Actions | `.github/workflows/deploy.yml` — connects to the manager, hands `deploy.sh` to it |
| The manager | `deploy.sh` — updates the checkout, builds the image, rolls the service |

## Releasing

```bash
./release.sh "Add visitor ratings to the assistant"   # checks, commit, push this branch
./release.sh --deploy "Ship the payment gateway"      # checks, commit, push main -> deploys
./release.sh --skip-checks "Fix a typo"               # no checks: pushes straight to production
./release.sh --no-push "Groundwork"                   # commit only
```

**Anything that reaches `main` deploys to production.** `--deploy` is how you get
there from wherever you are; it pushes whatever is checked out to `main`, and git
refuses a non-fast-forward, so a diverged `main` stops rather than being rewritten.

`--deploy` and `--no-push` contradict each other and are rejected.

### The checks are the only gate

The workflow has no test job: it exists to deploy, not to verify. That is a
deliberate trade — a second copy of the suite in CI only made the deploy sit
waiting on it — but it means `release.sh` is the last thing standing between a
commit and production. `--skip-checks` ships code nothing has verified.

Two checks run, both inside the `htashop-api` container because `.env` points at a
docker-internal database host that the machine running the release cannot resolve:

1. **`vendor/bin/pint --test --dirty`** — style, for the files this release
   touches only. The codebase predates Pint and a full `--test` reports **898
   files**, which would block every release instead of catching new mistakes.
   Formatting all of it is a change of its own; until someone does that, the gate
   stays on the diff. Fix a failure with `docker exec htashop-api php vendor/bin/pint --dirty`.
2. **`artisan test`** — the suite.

Set `PHP_BIN` to run them on a host PHP instead (only if that PHP can reach the
database).

## One-time setup

### On the manager

1. A checkout of this repository owned by the deploy user, reachable through
   `DEPLOY_PATH`. `deploy.sh` fast-forwards it on every deploy, so it must not
   accumulate local commits — if it has any, the deploy stops rather than
   discarding them.
2. Docker, on a node that is part of the Swarm (`docker info` → `Swarm: active`).
3. **The stack must already exist.** `deploy.sh` rolls an existing service and
   refuses to invent one, because a half-deployed stack is worse than a failed
   deploy. First-time setup is `deploy/` — networks, secrets, db, redis, typesense,
   gotenberg, haproxy — then the services are rolled one at a time from there.

### In GitHub

Settings → Secrets and variables → Actions:

| Name | Store | Value |
|---|---|---|
| `DEPLOY_HOST` | Secret | Swarm manager hostname or IP |
| `DEPLOY_USER` | Secret | The account that owns the checkout |
| `DEPLOY_SSH_KEY` | Secret | Private key that can log in as that user |
| `DEPLOY_PORT` | either | sshd port; without it the workflow's built-in default is used |
| `DEPLOY_PATH` | Variable | The checkout's directory on the manager |
| `SMOKE_URL` | Variable | `https://api.htashop.com/up` — Laravel's health route |

A Variable or a Secret works for the last three; none are sensitive. The value must
not be empty and the name must match exactly.

## What a deploy does

`deploy.sh` prints a labelled line per phase, so the last one shown is where it
stopped:

1. **Preflight** — Docker answers, the node is in a Swarm, and the service exists.
   Checked before the build, because a bad service name found after six minutes of
   building is expensive.
2. **Checkout** — `git fetch` + `--ff-only`. The manager builds what was pushed.
3. **Build** — the image, tagged both `htashop-api:latest` and
   `htashop-api:<short sha>`.
4. **Roll** — `docker service update --image`, which blocks until the rollout
   converges. The service's own `update_config` governs it: one task at a time, 30s
   apart, monitored for 60s, **rolling back on failure**. A non-zero exit means
   Swarm gave up and reverted.
5. **Verify** — the replica count, then `SMOKE_URL` (retried, because the routing
   mesh takes a moment to notice new tasks).
6. **Housekeeping** — `docker image prune -f` for the previous build's dangling
   layers. Images in use are untouched, so a rollback target survives.

### Rolling back

The previous image is still on the node and still tagged:

```bash
docker service update --force --image htashop-api:<short sha> htashop_api
```

`deploy.sh --no-build` rolls the current `latest` without rebuilding, which is the
quick way back after a bad build.

## Swarm specifics worth knowing

- **Migrations run in the image's entrypoint, not in `deploy.sh`.** Each replica
  therefore attempts them on boot. Laravel records an applied migration, so a
  replica that loses the race retries and proceeds — the entrypoint has retry logic
  for exactly this — but it does mean three containers migrating at once during a
  rollout. Keep migrations **additive** (expand/contract): with
  `parallelism: 1`, the tasks still running the previous image see the new schema.
- **The service exposes `20051-20060:20050`,** a ten-port range for ten replicas,
  so HAProxy reaches replicas directly rather than through the routing mesh. Adding
  replicas beyond that range needs the range widened. HAProxy's `api_backend` must
  match whichever the stack actually uses.
- **`CONTAINER_ID: "{{.Task.Slot}}"`** gives each replica a stable identity, which
  is what makes per-replica logs and metrics readable.
- **Capacity.** The box is 6 vCPU / 12 GB. The API is limited to 0.50 CPU and 512 MB
  per replica — 1.5 CPU and 1.5 GB across three — leaving room for db, redis,
  typesense, gotenberg and haproxy. The image build is the peak memory moment:
  there is deliberately no `--memory` cap on `docker build`, because BuildKit
  ignores it and a fixed ceiling hurts more than it protects.
- **Secrets.** `docker-swarm.yml` mounts Passport keys and the database password
  from Swarm secrets, but `APP_KEY` and `DB_PASSWORD` are also inline in that file,
  in a repository. Worth moving to secrets before the repo is shared further.
