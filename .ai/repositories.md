# The Dolumar repositories

| Repository | What it is | Deployed as |
|------------|------------|-------------|
| [`dolumar`](https://github.com/CatLabInteractive/dolumar) | The game (`Dolumar_*` classes, templates, translations, cron). Holds the Docker stack, deployment config and the most detailed `.ai/` docs. | Dokku app |
| [`dolumar-engine`](https://github.com/CatLabInteractive/dolumar-engine) (this one) | "Neuron" game-server framework. | Composer dependency of `dolumar` |
| [`dolumar-portal`](https://github.com/CatLabInteractive/dolumar-portal) | Marketing website on the private `catlab-ancient-cms`. Unrelated to this code. | Separate app |

## How an engine change reaches production

1. Change and test it here (through the game's Docker stack), open a PR in this repository.
2. The game's `composer.json` requires this package as `dev-php8`, i.e. the `php8` branch. `master` already contains
   that work. Once the PR is merged, make sure the change is on the branch the game requires.
3. In `dolumar`, run `composer update catlabinteractive/dolumar-engine` and open a PR with the new `composer.lock`.
   Production only picks up the engine commit pinned in that lock file.

If an engine change alters an API the game uses, open both PRs together and link them.
