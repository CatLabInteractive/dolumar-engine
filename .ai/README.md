# AI documentation for dolumar-engine

This directory holds documentation written for AI coding agents (and useful to humans too). The root
[`AGENTS.md`](../AGENTS.md) is the entry point and points here.

| File | Read it when |
|------|--------------|
| [architecture.md](architecture.md) | You need to find where something lives in the engine. |
| [development.md](development.md) | You want to run tests or lint. |
| [conventions.md](conventions.md) | You are about to write or change code. |
| [repositories.md](repositories.md) | You need to know how an engine change reaches the game. |

New design specs and implementation plans go in `.ai/plans/` (create it when needed), not in `docs/`.

## Keeping these docs current

When a change makes something in here wrong, update the relevant file in the same pull request. Keep facts here that
cannot be read quickly from the code; do not copy code.
