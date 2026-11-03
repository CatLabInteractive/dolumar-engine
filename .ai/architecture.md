# Architecture

## Layout

| Path | Contents |
|------|----------|
| `src/connect.php`, `src/config.php` | Loaded on every request by the game bootstrap. Define engine constants (`NOW`, `CATLAB_BASEPATH`, `GAMESERVER_ASSET_URL`, mail defaults, ...), session name, admin e-mail list. |
| `src/Neuron/GameServer.php` | The server object. `Neuron_GameServer::bootstrap()` creates it, the game calls `setGame()` and `dispatch()`. `dispatch()` routes on the first path segment (`dialog`, `map`, `image`, `page`, `admin`, `api`, `oauth2`, `gameserver`, `time`, `test`, home). |
| `src/Neuron/GameServer/` | Game-server building blocks: `Player` and `Server`, `Windows/` (base `Window` class and generic windows such as chat, messages, account), `Pages/` (full pages and `Pages/Admin/`), `Map/` (map renderer, map objects, and moving objects: `Movement` paths with timestamps, `MovingObjectsExporter` which redacts them per viewer, `Regions` which maps client regions to areas), `Mappers/` (players, chat, updates), `Models/`, `Interfaces/` (the contracts a game implements, chiefly `Neuron_GameServer_Interfaces_Game`), `Logable/`, `View/`. |
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

## Map endpoints

`dispatch()` sends `map/*` to `src/Neuron/GameServer/scripts/map/map.php`: `region` (background tiles and static
objects per client region, cached), `images` (preload list), `update` (changed regions), `plainmap`, and `objects`.
`map/objects` takes the same `regions[]` POST as `region` and answers JSON with the *moving* objects
(`Neuron_GameServer::renderMovingObjects()`): it asks the game's `MapObjectManager::getMovingObjects($areas, $player)`
and exports each object through `Neuron_GameServer_Map_MovingObjectsExporter`. A viewer the object is not related to
(`MapObject::isRelatedTo()`) gets an opaque id, a neutral kind and only the next poll window of the path. Anonymous
visitors get 403. The client side is `assets/javascript/mapobjects.js` (`Game.mapObjects`), a layer inside the scrolling map
div that polls `map/objects` and interpolates sprite positions from the path timestamps.

## How a game plugs in

A game implements `Neuron_GameServer_Interfaces_Game` (see `Dolumar_Game` in the game repo): it returns its map,
server, initial windows, and resolves window, page and admin-page names to its own classes. When the game returns
nothing for a name, the engine falls back to its own `Neuron_GameServer_Windows_*` / `Neuron_GameServer_Pages_*`.

Windows are the core UI unit. Every window extends `Neuron_GameServer_Windows_Window`, configures itself in
`setSettings()`, renders in `getContent()`, handles posts in `processInput()` and polling in `getRefresh()`. The
`dialog` route turns client AJAX calls into window calls and returns XML.

## Layout modes and sheets

The client has two layout modes. `assets/javascript/layout.js` (`Game.layout`) resolves the `layout` preference
(`auto`, `desktop` or `mobile`; default `Neuron_GameServer::LAYOUT_DEFAULT`, stored per player and changed from
the account window with `action=layout`, or overridden for one request with `?layout=`) into `body.layout-mobile`
or `body.layout-desktop`. `auto` switches at a width of 800 CSS pixels. In mobile mode `assets/javascript/sheets.js`
(`Game.sheets`) renders the same windows the `dialog` route returns as full-width stacked sheets instead of
draggable windows; `Game.gui.relayoutWindows(mode)` moves open windows between the two presentations. The map
scale in `assets/javascript/scrollmap.js` is a client-side CSS scale (0.5 to 1.5, pinch or buttons) applied on top
of the tile size the server renders; `Game.map.setScale` stores it in `localStorage` and corrects the width used
for the `auto` breakpoint. The map cancels the default action of `touchstart` so the page never scrolls behind it,
which also stops browsers from turning a tap into a click; `TIV_touchEnd` therefore dispatches a click itself for a
clean tap (one finger, under 10 px of movement, under 500 ms) so image-map areas and movable objects open as on
desktop. The game supplies its own mobile chrome (header, tab bar) on top of these primitives.

## Browser (Web Push) notifications

`Neuron_GameServer_Player::sendNotification()` delivers a message from the game's `notifications` language file
through e-mail (offline players only), the dead BrowserGamesHub channel and, for non-public messages, Web Push.
`sendPushNotification()` sends only the push, for events too frequent for e-mail. Both end in
`queuePushNotification()`, which writes the JSON payload to `n_push_queue` when push is configured, the category
(language file section) is not muted and the player has at least one subscribed browser. Nothing is sent inside a
web request: the game's cron calls `Neuron_GameServer_Push_Sender::flushQueue()` every minute, which delivers
through `minishlink/web-push`, drops messages older than an hour and deletes subscriptions the push service reports
as gone (404/410).

- Configuration: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` constants, optional `PUSH_ICON_URL`
  (`Neuron_GameServer_Push_Config`). Without the keys push is off everywhere and nothing changes.
- Routes: `push/subscribe` and `push/unsubscribe` (`Neuron_GameServer_Push_Endpoint`: logged-in player, POST,
  `application/json`, same Origin, and only endpoints on the known push-service hosts, so the cron never posts to a
  URL a player made up) and `sw.js` (the service worker, `assets/javascript/push/sw.js`, served from the game's root
  so its scope is the whole game).
- Storage: `n_push_subscriptions` (one row per browser endpoint, moved to whoever subscribed last) and `n_push_queue`.
  The schema lives in the game's `dump/`.
- Client: `assets/javascript/push.js` (`Game.push`). The browser is only asked for permission from the
  "Browser notifications" section of My Account; on every page load an existing subscription is re-sent so it
  follows the player logged in on that browser. The service worker skips the notification when a game tab is
  focused. iOS only supports push for a site added to the home screen.
- Categories: the game lists what players can mute with `getPushCategories()` (section => label) and what starts
  muted with `getPushDefaultMuted()`; the choice is stored in the `pushMuted` preference.

## Configuration

The engine reads configuration from PHP constants that the game's bootstrap defines from environment variables
(`DB_*`, `MEMCACHE_*`, `EMAIL_SMTP_*`, `OPENID_CONNECT_*`, `ABSOLUTE_URL`, ...). Most engine constants are guarded with
`if (!defined(...))`, so the game can override them by defining them first.
