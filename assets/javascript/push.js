/*
	Web Push: subscribe this browser to the player's notifications.

	The server side is Neuron_GameServer_Push_* (engine). Push is off when
	CONFIG_PUSH_PUBLIC_KEY is empty. The browser is only asked for permission
	when the player presses "Turn on" in My Account.
*/
Game.push = {

	'getKey' : function ()
	{
		return typeof (CONFIG_PUSH_PUBLIC_KEY) != 'undefined' ? CONFIG_PUSH_PUBLIC_KEY : '';
	},

	'isSupported' : function ()
	{
		return !!(
			window.isSecureContext &&
			'serviceWorker' in navigator &&
			'PushManager' in window &&
			'Notification' in window
		);
	},

	// The Dolumar app (mobile/ in the game repository) appends DolumarApp/<version>
	// to the user agent. Its WebView has no Web Push; the app will get native push.
	'inApp' : function ()
	{
		return /DolumarApp\//.test (navigator.userAgent);
	},

	// iOS only offers push to a site that was added to the home screen.
	'needsHomeScreen' : function ()
	{
		var ios = /iPad|iPhone|iPod/.test (navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
		var standalone = navigator.standalone === true ||
			(window.matchMedia && window.matchMedia ('(display-mode: standalone)').matches);
		return ios && !standalone && !('PushManager' in window);
	},

	'url' : function (path)
	{
		var url = path;
		if (Game.core && Game.core.sSessionId != null)
		{
			url += '?session_id=' + encodeURIComponent (Game.core.sSessionId);
		}
		return url;
	},

	'register' : function ()
	{
		return navigator.serviceWorker.register ('sw.js');
	},

	'getSubscription' : function ()
	{
		return Game.push.register ().then (function (registration)
		{
			return registration.pushManager.getSubscription ();
		});
	},

	'post' : function (action, subscription)
	{
		return fetch (Game.push.url ('push/' + action), {
			'method' : 'POST',
			'credentials' : 'same-origin',
			'headers' : { 'Content-Type' : 'application/json' },
			'body' : JSON.stringify (subscription)
		}).then (function (response)
		{
			if (!response.ok)
			{
				throw new Error ('push/' + action + ' failed: ' + response.status);
			}
			return response;
		});
	},

	'decodeKey' : function (base64)
	{
		var padding = '='.repeat ((4 - base64.length % 4) % 4);
		var raw = window.atob ((base64 + padding).replace (/-/g, '+').replace (/_/g, '/'));
		var out = new Uint8Array (raw.length);
		for (var i = 0; i < raw.length; i ++)
		{
			out[i] = raw.charCodeAt (i);
		}
		return out;
	},

	// True when the subscription was made with another server key (the keys were changed).
	'hasOtherKey' : function (subscription)
	{
		var current = subscription.options && subscription.options.applicationServerKey;
		if (!current)
		{
			return false;
		}

		var a = new Uint8Array (current);
		var b = Game.push.decodeKey (Game.push.getKey ());
		if (a.length != b.length)
		{
			return true;
		}
		for (var i = 0; i < a.length; i ++)
		{
			if (a[i] != b[i])
			{
				return true;
			}
		}
		return false;
	},

	'subscribe' : function ()
	{
		return Game.push.register ().then (function (registration)
		{
			return registration.pushManager.subscribe ({
				'userVisibleOnly' : true,
				'applicationServerKey' : Game.push.decodeKey (Game.push.getKey ())
			});
		}).then (function (subscription)
		{
			return Game.push.post ('subscribe', subscription).then (function ()
			{
				return subscription;
			});
		});
	},

	/*
		On every page load: tell the server about the existing subscription, so that
		it follows the player who is logged in on this browser right now.
	*/
	'sync' : function ()
	{
		if (!Game.push.getKey () || !Game.push.isSupported () || Notification.permission !== 'granted')
		{
			return;
		}

		Game.push.getSubscription ().then (function (subscription)
		{
			if (!subscription)
			{
				return null;
			}

			if (Game.push.hasOtherKey (subscription))
			{
				return subscription.unsubscribe ().then (Game.push.subscribe);
			}

			return Game.push.post ('subscribe', subscription);
		}).catch (function (e)
		{
			if (window.console) { console.warn (e); }
		});
	},

	'enable' : function (button)
	{
		var container = Game.push.findContainer (button);

		Notification.requestPermission ().then (function (permission)
		{
			if (permission !== 'granted')
			{
				return null;
			}
			return Game.push.subscribe ();
		}).catch (function (e)
		{
			if (window.console) { console.warn (e); }
		}).then (function ()
		{
			Game.push.updateAccount (container);
		});
	},

	'disable' : function (button)
	{
		var container = Game.push.findContainer (button);

		Game.push.getSubscription ().then (function (subscription)
		{
			if (!subscription)
			{
				return null;
			}
			return Game.push.post ('unsubscribe', subscription).catch (function () {}).then (function ()
			{
				return subscription.unsubscribe ();
			});
		}).catch (function (e)
		{
			if (window.console) { console.warn (e); }
		}).then (function ()
		{
			Game.push.updateAccount (container);
		});
	},

	'findContainer' : function (element)
	{
		while (element && !(element.className && (' ' + element.className + ' ').indexOf (' push-settings ') > -1))
		{
			element = element.parentNode;
		}
		return element;
	},

	// onLoad of the My Account window (Neuron_GameServer_Windows_MyAccount).
	'initAccount' : function (oWindow)
	{
		var root = oWindow && oWindow.div ? oWindow.div : document;
		var containers = root.getElementsByClassName ? root.getElementsByClassName ('push-settings') : [];
		for (var i = 0; i < containers.length; i ++)
		{
			Game.push.updateAccount (containers[i]);
		}
	},

	'updateAccount' : function (container)
	{
		if (!container)
		{
			return;
		}

		var status = container.getElementsByClassName ('push-status')[0];
		var enable = container.getElementsByClassName ('push-enable')[0];
		var disable = container.getElementsByClassName ('push-disable')[0];

		var show = function (state, canEnable, canDisable)
		{
			status.innerHTML = '';
			var label = container.getElementsByClassName ('push-text-' + state)[0];
			status.appendChild (document.createTextNode (label ? (label.textContent || label.innerText) : state));
			status.style.display = '';
			enable.style.display = canEnable ? '' : 'none';
			disable.style.display = canDisable ? '' : 'none';
		};

		// Nothing the player can do about push inside the app: hide the section and its heading.
		if (Game.push.inApp ())
		{
			container.style.display = 'none';
			var heading = container.previousElementSibling;
			if (heading && heading.tagName === 'H2')
			{
				heading.style.display = 'none';
			}
			return;
		}

		if (Game.push.needsHomeScreen ())
		{
			show ('ios', false, false);
			return;
		}

		if (!Game.push.isSupported ())
		{
			show ('unsupported', false, false);
			return;
		}

		if (Notification.permission === 'denied')
		{
			show ('blocked', false, false);
			return;
		}

		Game.push.getSubscription ().then (function (subscription)
		{
			if (subscription && Notification.permission === 'granted')
			{
				show ('on', false, true);
			}
			else
			{
				show ('off', true, false);
			}
		}).catch (function ()
		{
			show ('unsupported', false, false);
		});
	}
};

if (window.addEventListener)
{
	window.addEventListener ('load', function () { Game.push.sync (); });
}
