# AGENTS.md

Instructions for AI coding agents working in this repository. Keep this file short: it is the entry point,
and the detailed documentation lives in [`.ai/`](.ai/README.md).

## What this is

`dolumar-engine` is "Neuron", the PHP game-server framework that powers the browser game
[Dolumar](https://github.com/CatLabInteractive/dolumar). It is a Composer library (`catlabinteractive/dolumar-engine`),
not an application: it cannot run on its own. The game repository requires it and is where you run and test it.

It runs on PHP 8.4 (minimum 8.4.27) with MySQL and Memcached. The code dates from 2008 onwards; the PHP 8 port
kept its behaviour on purpose.

## Read before working

All AI-facing documentation is in `.ai/`. Start with [`.ai/README.md`](.ai/README.md), then read what applies:

- [`.ai/architecture.md`](.ai/architecture.md): what the engine provides and where it lives.
- [`.ai/development.md`](.ai/development.md): how to run the tests (through the game's Docker stack) and lint.
- [`.ai/conventions.md`](.ai/conventions.md): code style, DB access, security rules, tests.
- [`.ai/repositories.md`](.ai/repositories.md): how this repo relates to `dolumar` and `dolumar-portal`, and how an
  engine change reaches production.

The game's own `.ai/` directory documents the request flow, deployment and game-side conventions in more depth.

## Ground rules

- Work on a feature branch and open a pull request. Never commit directly to `master`.
- Keep behaviour stable. The game depends on exact return types and semantics of the DB layer, sessions and
  templates; changing them silently changes a live game.
- Public API changes (class names, method signatures used by `Dolumar_*` code) need a matching change in the game
  repository. Check the game for callers before renaming or removing anything.
- No secrets in the repository. Configuration arrives as constants defined by the game's bootstrap.
- New behaviour and every bug fix in testable code come with a PHPUnit test in `tests/`.

## Quick commands

Run from a checkout of `dolumar` with this repository at `../dolumar-engine`:

```
docker compose up -d --build
docker compose exec -w /engine web composer install
docker compose exec -w /engine web vendor/bin/phpunit
docker compose exec -w /engine web vendor/bin/phpcs
```
