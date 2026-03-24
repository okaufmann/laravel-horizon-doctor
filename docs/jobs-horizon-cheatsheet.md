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

## Timeouts & Redis (Horizon)

- Job **`$timeout`** must be **below** the worker’s **hard timeout** (Horizon `timeout` on the supervisor).
- **`retry_after`** in `config/queue.php` (Redis) must be **greater than** the job timeout, or Redis may release the job while it is still running → duplicates / weird retries.

Run `php artisan horizon:doctor` in this package’s consuming app to catch common mismatches.

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
