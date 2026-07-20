# Coding with FrankenPHP: `FRANKENPHP_MODE` & `FRANKENPHP_RESET_KERNEL`

This guide explains how application code must behave under each combination of
HTTP runtime settings. It does **not** apply to the Compose `messenger` service
(`messenger:consume`), which is a separate long-running process.

| Setting | Meaning |
|---|---|
| `FRANKENPHP_MODE=classic` | Boot the app on every HTTP request (PHP-FPM-like). |
| `FRANKENPHP_MODE=worker` | Boot once; keep Kernel/container in memory across requests. |
| `FRANKENPHP_RESET_KERNEL=false` (default) | In worker mode, **reuse** the same Kernel; services are reset via `kernel.reset` / `ResetInterface`. |
| `FRANKENPHP_RESET_KERNEL=true` | In worker mode, **clone** the Kernel after each request (`APP_RUNTIME_MODE=web=1&worker=2`). Fresh container from the cached dump; safer isolation, lower throughput. |

`FRANKENPHP_RESET_KERNEL` only affects **worker** mode. In classic mode it is ignored for request isolation (each request already gets a new process lifecycle via HttpKernelRunner).

Homepage signals:

| Mode | Typical `FRANKENPHP_WORKER` | Typical `APP_RUNTIME_MODE` |
|---|---|---|
| classic | `false` | `n/a` |
| worker + reset false | `true` | `web=1&worker=1` |
| worker + reset true | `true` | `web=1&worker=2` |

---

## Matrix

### 1. `FRANKENPHP_MODE=classic`

**What happens**

- Each request bootstraps (or re-enters) through the normal Runtime path → `HttpKernelRunner`.
- No `frankenphp_handle_request()` loop.
- Process/request boundaries match traditional PHP expectations more closely.

**Coding rules**

- Write code as you would for PHP-FPM: request-scoped state dies with the request **in practice**, but do **not** rely on that if the same code must also run in worker mode.
- Prefer services that are safe in worker mode anyway (`ResetInterface` where needed) so classic ↔ worker stay interchangeable.
- `FRANKENPHP_RESET_KERNEL` has no meaningful isolation effect here; leave it `false`.

**Good for**

- Local debugging, Xdebug, catching “works only when the process dies” bugs.
- Comparing behaviour against worker mode.

---

### 2. `FRANKENPHP_MODE=worker` + `FRANKENPHP_RESET_KERNEL=false` (default worker)

**What happens**

- FrankenPHP sets `FRANKENPHP_WORKER=1` and Symfony uses `FrankenPhpWorkerRunner`.
- The Kernel stays booted; the container is reused.
- After each request, Symfony resets services tagged with `kernel.reset` (including anything implementing `ResetInterface`).
- Static properties, mutable globals, and `$_ENV` mutations can **leak** across requests.

**Coding rules (mandatory for this mode)**

1. **Prefer stateless services.** Inject collaborators; avoid storing request-specific data on `$this` unless you reset it.
2. **Implement `ResetInterface`** on any service that caches per-request data (user, locale, cart, “current entity”, in-memory buffers):

   ```php
   use Symfony\Contracts\Service\ResetInterface;

   final class CartContext implements ResetInterface
   {
       private array $items = [];

       public function reset(): void
       {
           $this->items = [];
       }
   }
   ```

3. **Do not use `static` for request data.** Function `static $cache`, class static properties, and singletons survive across requests.
4. **Do not write request-specific data into `$_ENV`.** FrankenPHP does not reset `$_ENV` between worker requests; superglobals like `$_GET`/`$_POST`/`$_SERVER` are reset.
5. **Doctrine:** use the EntityManager from DI; avoid long-lived identity-map assumptions. Clear or rely on framework resets; do not keep entities on services across requests without `reset()`.
6. **Closures / event listeners** registered at runtime onto shared services can accumulate — register via DI/events, not ad-hoc per request on a shared object without cleanup.
7. **Files / resources:** close handles; do not leave open streams or PDO connections in custom code outside DI-managed services.
8. **`FRANKENPHP_LOOP_MAX`:** workers recycle after N requests (default `500`) to bound memory leaks; treat this as a safety net, not a substitute for correct resets.

**Good for**

- Production performance (highest RPS when the app is reset-clean).
- Teams that audit state and use `ResetInterface` consistently.

---

### 3. `FRANKENPHP_MODE=worker` + `FRANKENPHP_RESET_KERNEL=true`

**What happens**

- Same worker loop, but after each request the runner **clones** the Kernel.
- Symfony’s `Kernel::__clone()` resets `booted` / `container`; the next request boots a **new container** from the dumped container file (not a full cold Composer autoload of the whole app).
- `APP_RUNTIME_MODE` becomes `web=1&worker=2`.
- Stronger isolation than reset-false; still faster than classic in typical apps, but slower than default worker.

**Coding rules**

1. Still avoid leaking via **process-level** state that clone does **not** clear:
   - PHP `static` variables / static class properties
   - Global variables
   - Mutations to `$_ENV`
   - Extension-level or native singletons outside the container
2. Container-scoped service state is largely isolated by the clone (new container instance).
3. If you store non-resettable state **on the Kernel subclass itself**, override `__clone()` and clear it (see `App\Kernel` docblock).
4. Use this mode when:
   - Migrating a classic app and `ResetInterface` coverage is incomplete
   - You need safer defaults while auditing state leaks
5. Prefer moving back to `RESET_KERNEL=false` once services are properly resettable (better throughput).

**Good for**

- Safer worker adoption / progressive migration.
- Catching container-level state bugs without paying full classic cost.

---

## Decision guide

```
Need max performance + team owns ResetInterface?
  → MODE=worker, RESET_KERNEL=false

Need worker speed but incomplete state hygiene?
  → MODE=worker, RESET_KERNEL=true

Debugging request isolation / “ghost” state?
  → MODE=classic  (compare with worker)

Must work in all environments?
  → Write as if RESET_KERNEL=false worker (strictest shared-process rules)
```

**Project rule:** application code in this repo MUST be safe under  
`FRANKENPHP_MODE=worker` + `FRANKENPHP_RESET_KERNEL=false`.  
That implies it also works in classic and with reset-true.

---

## Checklist before merging HTTP features

- [ ] No request-scoped data in `static` properties or function statics
- [ ] Stateful services implement `ResetInterface` (or are request-scoped via factory patterns that do not leak)
- [ ] No reliance on “process dies after the request”
- [ ] No request secrets written to `$_ENV`
- [ ] Verified homepage flags for the target mode (`FRANKENPHP_WORKER` / `APP_RUNTIME_MODE`)
- [ ] Smoke-tested both `make classic` and `make worker` when the change touches shared services

## References

- [FrankenPHP worker mode](https://frankenphp.dev/docs/worker/)
- [Symfony + FrankenPHP](https://frankenphp.dev/docs/symfony/)
- [Symfony `ResetInterface`](https://github.com/symfony/contracts/blob/main/Service/ResetInterface.php)
- Runtime: `Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner`
- Spec: [`specs/001-frankenphp-boilerplate/spec.md`](../specs/001-frankenphp-boilerplate/spec.md)
