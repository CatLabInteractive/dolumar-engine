/*
	In-app purchases: open the app's store page from the game.

	Inside the Android/iOS app the game must not link to the web shop on
	accounts.catlab.eu (Apple 3.1.1, Google Play payments policy). Instead the
	game asks its own server for a store ticket (api/store/ticket) and navigates
	to the app's bundled store page, which sells the credit packs through the
	store plugin and registers them through api/store/register. The server side
	is Neuron_GameServer_Credits_StoreEndpoint (engine).
*/
Game.store = {

	'opening' : false,

	// Same check as Game.push.inApp(): the app appends DolumarApp/<version> to the user agent.
	'inApp' : function ()
	{
		return /DolumarApp\//.test (navigator.userAgent);
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

	/*
		Ask for a ticket and go to the store page. Returns false so it can be
		used from an onclick handler.
	*/
	'open' : function ()
	{
		if (Game.store.opening)
		{
			return false;
		}
		Game.store.opening = true;

		fetch (Game.store.url ('api/store/ticket'), { 'method' : 'POST', 'credentials' : 'same-origin' })
			.then (function (response)
			{
				return response.json ().then (function (data)
				{
					if (!response.ok || !data || !data.url)
					{
						throw new Error (data && data.error ? data.error : 'http ' + response.status);
					}
					return data;
				});
			})
			.then (function (data)
			{
				window.location.href = data.url;
			})
			.catch (function (error)
			{
				Game.store.opening = false;
				alert (Game.store.failureText (error && error.message));
			});

		return false;
	},

	'failureText' : function (reason)
	{
		if (reason === 'not_logged_in')
		{
			return 'Please log in again to buy credits.';
		}
		return 'The store cannot be opened right now. Please try again later.';
	}
};
