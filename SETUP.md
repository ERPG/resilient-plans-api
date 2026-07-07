# Setup — running & testing the service

Everything runs in Docker. You need **Docker + Docker Compose** and nothing else installed on the host.

## Run

```bash
make run
```

Builds and starts the whole stack in one command: PHP-FPM + Nginx (app), PostgreSQL, and Redis.
The app boots in `prod` mode with migrations applied and the Symfony cache warmed. The API is then
available at `http://localhost:8000`.

Useful variants:

```bash
make down    # stop everything
make logs    # tail container logs
make shell   # shell into the app container
```

## Sync the provider feed

The search endpoint only reads from the local database, so you need at least one sync to have data:

```bash
make sync
```

This runs `app:sync-plans`: it fetches the provider feed, keeps only `sell_mode=online` plans, and
does an idempotent upsert into the local store. It is **trigger-agnostic** — in production the
environment scheduler (cron, K8s CronJob, EventBridge) invokes it; there is no cron or worker inside
the stack. If the provider is unreachable it exits non-zero without crashing anything else.

## Query the endpoint

```bash
curl -s "http://localhost:8000/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z" | jq .
```

Response envelope (contract):

```json
{
  "data": {
    "events": [
      {
        "id": "a5f2c1e0-...-uuid",
        "title": "Plan title",
        "start_date": "2021-06-30",
        "start_time": "21:00:00",
        "end_date": "2021-06-30",
        "end_time": "22:00:00",
        "min_price": 15.0,
        "max_price": 30.0
      }
    ]
  },
  "error": null
}
```

Invalid input (missing/unparseable dates, `starts_at >= ends_at`) returns HTTP 400 with
`{"data": null, "error": {"code": "invalid_request", "message": "..."}}`.

Because the endpoint never calls the provider, it keeps returning `200` from the local store even
when the provider is down.

## Test

```bash
make test
```

Rebuilds an isolated `fever_test` database from scratch (drop → create → migrate) and runs the full
suite (unit + integration). No Redis required: the test environment binds the search cache to an
in-memory array adapter.

## Configuration

Environment variables (see `.env`; overridden per environment):

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_ENV` | Symfony runtime environment | `dev`; the container runs `prod`, the test suite runs `test` |
| `DATABASE_URL` | PostgreSQL DSN | `postgresql://app:app@db:5432/fever?serverVersion=16` |
| `REDIS_URL` | Redis DSN for the search-result cache | `redis://redis:6379` |
| `PROVIDER_BASE_URL` | External provider feed URL | provider challenge endpoint |
| `PROVIDER_TIMEOUT` | Provider HTTP timeout (seconds) — a slow provider can't hang the sync | `5` |

## Reproducing the benchmark (optional)

The throughput numbers in [`benchmark-results.md`](benchmark-results.md) were measured with
[`wrk`](https://github.com/wg/wrk) against the local stack in `prod` mode (OPcache on). To reproduce:

```bash
# Cold / no Redis (fallback to DB): stop Redis first
docker compose stop redis
wrk -t4 -c50 -d30s "http://localhost:8000/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z"

# Warm Redis: start it, prime the cache with one request, then measure
docker compose start redis
curl -s "http://localhost:8000/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z" > /dev/null
wrk -t4 -c50 -d30s "http://localhost:8000/search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z"
```
