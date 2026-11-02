# Conventions

Legacy code: match the surrounding style and keep changes small. Do not modernise files in passing.

## Code

- Tabs for indentation, braces on their own line, a space before call parentheses: `foo ($bar)`.
- PEAR-style class names that match the path under `src/` (`Neuron_Core_Text` is `src/Neuron/Core/Text.php`), no
  namespaces, Composer classmap autoloading. After adding a class run `composer dump-autoload` here and in the game.
- Singletons via `getInstance()` / `__getInstance()` are the norm.
- Do not add a closing `?>` to new files.

## Database

- `Neuron_DB_Database::getInstance()` returns the `Neuron_DB_MySQL` connection; `Neuron_Core_Database` is an older
  facade over the same thing that game code still uses.
- The layer deliberately keeps PHP 7 mysqli semantics: a failed query throws a plain `Exception` with a message
  starting with `MySQL Error:`, and results come back as strings like before. Tests in `tests/DB/` pin this; keep
  them green.
- No prepared statements: escape every input with `escape()` or cast it to int.

## Security

- Sessions: the cookie hardening and session-id validation in `Neuron_GameServer` are covered by
  `tests/SessionHardeningTest.php`. Do not relax them.
- Login is OpenID Connect with a single-use, verified `state` (`tests/Auth/OpenIDConnectTest.php`) and session regeneration on login.
- Anonymous routes (`api`, `image`, `map`, `gameserver/assets`, `test`) are locked down; asset paths are resolved
  with path-traversal checks (`tests/GameServerAssetRouteTest.php`). Any new route that takes input from the URL
  needs the same care and a test.
- Credits and server-list integrations must stay silent when their configuration is missing.

## Tests

- PHPUnit 11, in `tests/`, mirroring the `src/` area they cover (`tests/DB/`, `tests/Auth/`, `tests/Core/`, ...).
- Add a regression test with every bug fix that can be tested without a browser.
