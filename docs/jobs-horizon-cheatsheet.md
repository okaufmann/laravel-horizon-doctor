# Jobs & Horizon — compact cheatsheet

Quick reference when writing queueable jobs/listeners. Details and edge cases live in the official docs (linked below).

## Official documentation

- [Queues & jobs](https://laravel.com/docs/queues) — dispatch API, job classes, failures, batches, rate limits, etc.
- [Horizon](https://laravel.com/docs/horizon) — supervisors, balancing, metrics, tags

## Job class essentials

- Implement `ShouldQueue` (or use a base that already does).
- `handle()` should be **retry-safe** (idempotent or guarded); use `failed()` / `deleteWhenMissingModels` where appropriate.
- Prefer **explicit** queue names for anything non-default so Horizon supervisors and `config/queue.php` stay aligned.

## Ways to set queue, connection, delay, retries

| Approach | Typical use |
|----------|-------------|
| **Public properties** on the job: `$connection`, `$queue`, `$delay`, `$afterCommit`, `$tries`, `$maxExceptions`, `$timeout`, `$backoff`, `$retryUntil` | Defaults for every dispatch of this class |
| **Chain on dispatch**: `SomeJob::dispatch(...)->onConnection()->onQueue()->delay()` | Per-dispatch override |
| **Horizon / Redis** | Supervisor `queue` names must match what workers consume; connection must match `config/queue.php` |

Use **either** stable defaults on the class **or** consistent dispatch chains — mixing without a clear rule is where configs drift.

## `config/queue.php` — Redis connection (per connection name)

These apply to the **queue backend**, not Horizon’s meta Redis (`horizon.use`).

| Key | Unit | Role |
|-----|------|------|
| `driver` | — | Must be `redis` for Horizon workers on this connection. |
| `connection` | name | Which Redis **client** connection (`config/database.php` → `redis.*`) stores queue payloads. |
| `queue` | name | **Default queue name** when a job does not specify one (also used as fallback when a Horizon supervisor’s `queue` list is empty). |
| `retry_after` | **seconds** | After this long a **reserved** job is treated as stale and can be released back to Redis; must be **greater than** your longest real job run (and than worker/job `timeout`). See `Illuminate\Queue\RedisQueue` (`$retryAfter`). |
| `block_for` | **seconds** or `null` | Max seconds to block on Redis while waiting for a job (`null` = non-blocking polling behavior per framework defaults). |
| `after_commit` | bool | Dispatch only after DB transaction commits. |

## `config/horizon.php` — supervisors (`defaults` + `environments.{env}.*`)

Merged per environment; keys are the same shape as `queue:work` / Horizon’s `SupervisorOptions` (Horizon maps `tries` → worker `maxTries`, `processes` → `maxProcesses`).

### Worker process (what each `queue:work` child gets)

| Key | Unit | Role |
|-----|------|------|
| `connection` | name | Key under `config/queue.php` → `connections`. |
| `queue` | name(s) | String or **array** of queue names (Horizon joins with `,` for the worker). |
| `balance` | `false` / `'simple'` / `'auto'` | Queue balancing across names in this supervisor. |
| `autoScalingStrategy` | `'time'` / `'size'` | How `'auto'` scales (time-to-clear vs job count). |
| `maxProcesses` | count | Max worker processes (`processes` is an alias in config). |
| `minProcesses` | count | Floor when auto-scaling (`>= 1`). |
| `maxTime` | **seconds** | Worker exits after this lifetime; `0` = unlimited. |
| `maxJobs` | count | Worker exits after this many jobs; `0` = unlimited. |
| `memory` | **MB** | Worker memory cap (same idea as `queue:work --memory`). |
| `timeout` | **seconds** | Hard cap per **job** run in that worker (child killed afterward). |
| `tries` | count | Default **attempts** if the job does not override (`queue:work --tries`). |
| `nice` | **Unix nice** | Process scheduling priority (integer; **not** seconds). |
| `sleep` | **seconds** | Sleep when no job is available. |
| `rest` | **seconds** | Pause between consecutive jobs in the same worker. |
| `backoff` | **seconds** or list | Delay before retry after exception; array becomes comma-separated for the worker. |
| `force` | bool | Run workers while app is in maintenance mode. |

### Auto-scale tuning (only relevant when `balance` is not `false`)

| Key | Unit | Role |
|-----|------|------|
| `balanceCooldown` | **seconds** | Minimum gap between scaling adjustments. |
| `balanceMaxShift` | count | Max processes added/removed per scaling step. |

### Horizon app-level (same file, not per supervisor)

| Key | Unit | Role |
|-----|------|------|
| `memory_limit` | **MB** | Cap for the **Horizon master** process (not each worker). |
| `waits.{connection:queue}` | **seconds** | Threshold before `LongWaitDetected` fires. |
| `trim.*` | **minutes** | How long Horizon keeps UI history for recent/failed/monitored jobs. |
| `metrics.trim_snapshots.*` | count | Number of metric snapshots to retain. |

## Job class vs worker vs Redis (putting it together)

| Layer | Timeout / retries | Unit |
|-------|-------------------|------|
| **Job** `$timeout` | Max time Laravel asks the worker to allow for **this** job | **seconds** |
| **Horizon `timeout`** | Worker **hard** limit per job (must be ≥ effective job timeout) | **seconds** |
| **Redis `retry_after`** | Reservation / visibility window before job can be released again | **seconds**; must be **>** real job duration (and **>** job & worker timeouts) |
| **Job** `$tries` / `$backoff` / `$maxExceptions` / `$retryUntil` | Retry policy for **application** failures | counts / **seconds** / datetime (per Laravel queue docs) |
| **Horizon `tries`** | Default attempts when the job does not define its own | count |

Wrong ordering (`timeout` ≥ `retry_after`, or supervisor `timeout` too tight) → duplicate runs or killed jobs. Run `php artisan horizon:doctor` in the consuming app to catch several of these mismatches.

## Listeners (`ShouldQueue`)

- Prefer **`#[Queue('name')]`** (class-level) or **`public $queue = '...'`** for the target queue.
- Avoid relying on **`onQueue()` only inside `__construct()`** for queued listeners: the constructor may not run the way you expect when the listener is serialized for the queue. Set queue via attribute/property or when registering the listener.

## Optional job APIs (one-liners)

| Feature | Remember |
|---------|----------|
| **Middleware** | `middleware()` on the job class — reuse for throttling, rate limits, etc. |
| **Unique jobs** | `ShouldBeUnique` (+ cache key / uniqueness id) — not a substitute for idempotent `handle()` |
| **Batches / chains** | `Bus::batch()`, `withChain()` — failure strategy matters for partial completion |
| **Horizon tags** | Implement `tags()` on the job for usable dashboard filtering |

## Dispatch from code

```php
SomeJob::dispatch($payload);
SomeJob::dispatchSync($payload);           // bypass queue
SomeJob::dispatchAfterResponse($payload);  // after HTTP response
dispatch(new SomeJob($payload))->onQueue('reports');
```

Sync driver ignores most queue options — use a real queue connection when testing Horizon-related behavior.
