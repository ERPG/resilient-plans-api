# Benchmark results — Redis cache impact

Referenced from the [README "Going the extra mile"](README.md#going-the-extra-mile) section. See
[`SETUP.md`](SETUP.md#reproducing-the-benchmark-optional) to reproduce these numbers.

## What this measures
Sustained throughput of the `GET /search` endpoint under concurrent load from **localhost**,
comparing the no-Redis path (direct fallback to the database) against the Redis path (warm cache,
responses served from memory).

## What this does NOT measure
- Real network latency (client and server on the same machine)
- Behaviour under mixed traffic or realistic user patterns
- Cold Redis performance (first request with an unpopulated cache)
- Maximum system capacity (the breaking point was not sought)

## Test conditions
- Tool: `wrk 4.2.0` — sustained load, no rate limit (runs as fast as it can)
- Duration: 30 s per scenario
- Endpoint: `GET /search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z`
- Environment: local Docker (PHP-FPM + Nginx + PostgreSQL + Redis on the same machine), `APP_ENV=prod`
- `wrk --latency` reports p50/p75/p90/p99 — there is no native p95, so **p90** is used as a proxy

---

## Without Redis (fallback to DB)

| Concurrency | Req/s | Avg latency | p50    | p90    | Timeouts |
|-------------|-------|-------------|--------|--------|----------|
| c=50        | 48.55 | 695 ms      | 592 ms | 1.03 s | 135      |
| c=200       | 51.00 | 1.02 s      | 1.03 s | 1.78 s | 1 434    |

> Throughput doesn't rise with concurrency: the bottleneck is the PHP-FPM workers, not the
> connections. At c=200 timeouts spike because connections wait more than 2 s for a free worker.

## With Redis (warm cache)

| Concurrency | Req/s    | Avg latency | p50     | p90     | Timeouts |
|-------------|----------|-------------|---------|---------|----------|
| c=50        | 1 191.45 | 41 ms       | 38.78 ms| 52.74 ms| 0        |
| c=200       | 877.36   | 227 ms      | 225 ms  | 272 ms  | 0        |

> c=200 yields less throughput than c=50: more simultaneous connections than available workers queues
> up in FPM. The system doesn't break (0 timeouts) but latency rises.

## Summary (c=50 scenario)

| Metric   | Without Redis | With Redis | Change     |
|----------|---------------|------------|------------|
| Req/s    | 48.55         | 1 191.45   | **×24.5**  |
| p50      | 592 ms        | 38.78 ms   | **−93%**   |
| p90      | 1.03 s        | 52.74 ms   | **−95%**   |
| Timeouts | 135           | 0          | eliminated |
