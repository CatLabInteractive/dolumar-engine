# Development

The engine has no runtime of its own. Develop it through the game's Docker stack.

## Setup

1. Check out [`dolumar`](https://github.com/CatLabInteractive/dolumar) and this repository side by side
   (`dolumar/` and `dolumar-engine/`), or point `ENGINE_PATH` at this checkout.
2. In `dolumar/`, follow its README to start the stack (`docker compose up -d --build`).
3. The stack mounts this repository at `/engine` and over the game's `vendor/catlabinteractive/dolumar-engine`, so
   edits here are live in the game at http://localhost:8080.
4. Install the engine's own dev dependencies once: `docker compose exec -w /engine web composer install`.

## Checks to run before opening a PR

| Check | Command (from `dolumar/`) |
|-------|---------------------------|
| Unit tests | `docker compose exec -w /engine web vendor/bin/phpunit` |
| PHP 8.4 compatibility | `docker compose exec -w /engine web vendor/bin/phpcs` |
| Game smoke test | `tools/smoke.sh http://localhost:8080` (add `SMOKE_NO_OAUTH=1` without an OIDC provider) |

The map client has a small Node test (`node --test tests/js/armies.test.js`, no dependencies) for the maths in
`assets/javascript/armies.js`.

`phpunit.xml.dist` sets `failOnWarning`, `failOnDeprecation` and `failOnNotice`: a deprecation is a failing test.
Several tests need the stack's MySQL and Memcached (`DATABASE_URL` defaults to `mysql://dolumar:dolumar@mysql/dolumar`).
