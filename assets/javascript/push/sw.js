/*
	Web Push service worker for the Neuron game server.

	Served by the engine at <game url>/sw.js (see Neuron_GameServer::dispatch) so that
	its scope is the whole game. The payload is built by Neuron_GameServer_Push_Sender:
	{ "title": "...", "body": "...", "url": "...", "tag": "...", "icon": "..." }
*/

self.addEventListener('install', function () {
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
	var data = {};
	try {
		data = event.data ? event.data.json() : {};
	} catch (e) {
		data = { body: event.data ? event.data.text() : '' };
	}

	var options = {
		body: data.body || '',
		icon: data.icon || undefined,
		badge: data.badge || undefined,
		data: { url: data.url || self.registration.scope }
	};

	if (data.tag) {
		options.tag = data.tag;
		options.renotify = true;
	}

	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
			// The player is looking at the game right now: the game shows it already.
			for (var i = 0; i < clients.length; i++) {
				if (clients[i].visibilityState === 'visible' && clients[i].focused) {
					return;
				}
			}
			return self.registration.showNotification(data.title || 'Dolumar', options);
		})
	);
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();

	var url = (event.notification.data && event.notification.data.url) || self.registration.scope;

	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
			for (var i = 0; i < clients.length; i++) {
				if (clients[i].url.indexOf(self.registration.scope) === 0 && 'focus' in clients[i]) {
					return clients[i].focus();
				}
			}
			if (self.clients.openWindow) {
				return self.clients.openWindow(url);
			}
		})
	);
});
