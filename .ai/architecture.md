# Architecture

## Layout

| Path | Contents |
|------|----------|
| `src/connect.php`, `src/config.php` | Loaded on every request by the game bootstrap. Define engine constants (`NOW`, `CATLAB_BASEPATH`, `GAMESERVER_ASSET_URL`, mail defaults, ...), session name, admin e-mail list. |
| `src/Neuron/GameServer.php` | The server object. `Neuron_GameServer::bootstrap()` creates it, the game calls `setGame()` and `dispatch()`. `dispatch()` routes on the first path segment (`dialog`, `map`, `image`, `page`, `admin`, `api`, `oauth2`, `gameserver`, `time`, `test`, home). |
| `src/Neuron/GameServer/` | Game-server building blocks: `Player` and `Server`, `Windows/` (base `Window` class and generic windows such as chat, messages, account), `Pages/` (full pages and `Pages/Admin/`), `Map/` (map renderer and map objects), `Mappers/` (players, chat, updates), `Models/`, `Interfaces/` (the contracts a game implements, chiefly `Neuron_GameServer_Interfaces_Game`), `Logable/`, `View/`. |
| `src/Neuron/Core/` | Utilities: `Template`, `Text` (translations), `Tools`, `Login`, `Memcache`, `Cache`, `Lock`, `Registry`, `PGen`, legacy `Database` facade. |
| `src/Neuron/DB/` | Database layer: abstract `Neuron_DB_Database`, `Neuron_DB_MySQL` (mysqli), `Neuron_DB_Result` (array-accessible result). |
| `src/Neuron/Auth/` | Login: `OpenIDConnect` (current login, on `league/oauth2-client`), legacy `OpenID`, `OAuth`, `APIAccess`. |
| `src/Neuron/Forum/`, `src/Neuron/NBBC/` | Forum and the BBCode parser. |
| `src/Neuron/Profiler/`, `src/Neuron/ErrorHandler/`, `src/Neuron/Exceptions/` | Diagnostics and errors. |
| `src/OAuth/` | Vendored legacy OAuth library. |
| `src/BBGS/`, `src/BrowserGamesHub/` | Integrations with old CatLab services (credits, notifications, statistics). Those services are dead; code paths must stay no-ops when unconfigured. |
| `templates/` | Engine templates (PHP), used when the game does not override them. |
| `languages/` | Engine translation files (`.lng`, INI style). |
| `assets/` | Client-side JavaScript, CSS and images, served by the `gameserver/assets/*` route. |
| `dumps/gameserver.sql` | Schema for the engine's own tables (the game's `dump/dump.sql` includes them). |
| `tests/` | PHPUnit tests; `tests/bootstrap.php` defines DB constants from `DATABASE_URL`. |

## How a game plugs in

A game implements `Neuron_GameServer_Interfaces_Game` (see `Dolumar_Game` in the game repo): it returns its map,
server, initial windows, and resolves window, page and admin-page names to its own classes. When the game returns
nothing for a name, the engine falls back to its own `Neuron_GameServer_Windows_*` / `Neuron_GameServer_Pages_*`.

Windows are the core UI unit. Every window extends `Neuron_GameServer_Windows_Window`, configures itself in
`setSettings()`, renders in `getContent()`, handles posts in `processInput()` and polling in `getRefresh()`. The
`dialog` route turns client AJAX calls into window calls and returns XML.

## Configuration

The engine reads configuration from PHP constants that the game's bootstrap defines from environment variables
(`DB_*`, `MEMCACHE_*`, `EMAIL_SMTP_*`, `OPENID_CONNECT_*`, `ABSOLUTE_URL`, ...). Most engine constants are guarded with
`if (!defined(...))`, so the game can override them by defining them first.
