/**
 * Game.mapObjects: the moving-objects layer of the map (in Dolumar: marching armies).
 *
 * The server (map/objects) sends, per object, a list of straight paths with
 * absolute timestamps. This layer polls it, keeps one sprite per object inside
 * the scrolling map div, and moves the sprites along their paths once a second.
 * Everything that needs the DOM goes through small methods (createSprite,
 * removeSprite, ...) so the maths can be tested without a browser.
 */
Game.mapObjects =
{
	'SPRITE_SIZE' : 40,
	'DEFAULT_POLL' : 30,

	'objects' : {},		// id -> { data: <server object>, sprite: <element>, lastPx: [x, y] }
	'offset' : 0,		// servertime - local time, in seconds
	'pollInterval' : 30,	// seconds between polls, as the server last asked
	'layer' : null,
	'routes' : null,		// <svg> with one polyline per revealed route
	'document' : null,	// test hook; the browser's document when null
	'label' : null,
	'tickTimer' : null,
	'pollTimer' : null,
	'pollDebounce' : null,	// timer of a poll() waiting to run
	'seq' : 0,			// number of the latest request; older answers are ignored
	'kindText' : {},	// filled by the game template: kind -> translated text

	'now' : function ()
	{
		return Date.now () / 1000;
	},

	'serverNow' : function ()
	{
		return Game.mapObjects.now () + Game.mapObjects.offset;
	},

	'doc' : function ()
	{
		return Game.mapObjects.document || document;
	},

	'scrollMap' : function ()
	{
		return Game.map.oScrollMap;
	},

	/* Setup: called once the TiledImageViewer exists */
	'init' : function (scrollMap)
	{
		scrollMap = scrollMap || Game.map.oScrollMap;
		if (Game.mapObjects.layer || !scrollMap)
		{
			return;
		}

		var doc = Game.mapObjects.doc ();

		var layer = doc.createElement ('div');
		layer.className = 'mapobjects';
		scrollMap.div.appendChild (layer);
		Game.mapObjects.layer = layer;

		// Routes are SVG polylines: no bitmap to size to the whole route
		var routes = doc.createElementNS ('http://www.w3.org/2000/svg', 'svg');
		routes.setAttribute ('class', 'mapobjects-routes');
		routes.setAttribute ('width', 1);
		routes.setAttribute ('height', 1);
		layer.appendChild (routes);
		Game.mapObjects.routes = routes;

		if (typeof (CONFIG_MAPOBJECT_TEXT) != 'undefined')
		{
			Game.mapObjects.loadText (CONFIG_MAPOBJECT_TEXT);
		}

		Game.mapObjects.tickTimer = setInterval (Game.mapObjects.tick, 1000);
		Game.mapObjects.setPollInterval (Game.mapObjects.DEFAULT_POLL);

		// The first region commit ran before the viewer existed: ask now
		Game.mapObjects.poll_ ();
	},

	/* Translated texts per kind (plus 'open'), emitted by the game's header template */
	'loadText' : function (texts)
	{
		for (var k in texts)
		{
			Game.mapObjects.kindText[k] = texts[k];
		}
	},

	'setPollInterval' : function (seconds)
	{
		if (Game.mapObjects.pollTimer && Game.mapObjects.pollInterval == seconds)
		{
			return;
		}

		if (Game.mapObjects.pollTimer)
		{
			clearInterval (Game.mapObjects.pollTimer);
		}

		Game.mapObjects.pollInterval = seconds;
		Game.mapObjects.pollTimer = setInterval (Game.mapObjects.poll_, seconds * 1000);
	},

	/*
		Ask the server for the objects touching the loaded regions. Region
		commits come in bursts (drag end, jump, resize), so the request is
		debounced a little.
	*/
	'poll' : function ()
	{
		if (Game.mapObjects.pollDebounce)
		{
			return;
		}

		Game.mapObjects.pollDebounce = setTimeout (function ()
		{
			Game.mapObjects.pollDebounce = null;
			Game.mapObjects.poll_ ();
		}, 100);
	},

	/* The HTTP call, separated so it can be stubbed */
	'request' : function (url, body, onSuccess)
	{
		new Ajax.Request
		(
			url,
			{
				method : 'post',
				postBody : body,
				onSuccess : function (transport)
				{
					onSuccess (transport.responseJSON);
				}
			}
		);
	},

	'poll_' : function ()
	{
		var scrollMap = Game.mapObjects.scrollMap ();
		if (!scrollMap || Game.mapObjects.doc ().hidden)
		{
			return;
		}

		var tiles = scrollMap.loadedMapTiles;
		var sPost = '';
		var i = 0;
		for (var key in tiles)
		{
			sPost += 'regions[' + i + '][0]=' + tiles[key][0] + '&';
			sPost += 'regions[' + i + '][1]=' + tiles[key][1] + '&';
			i ++;
		}

		if (i == 0)
		{
			return;
		}

		var seq = ++ Game.mapObjects.seq;

		Game.mapObjects.request
		(
			Game.map.getURL
			(
				CONFIG_DISPATCH_URL + 'map/objects/',
				'tiles=' + Game.map.iTilesPerRegion + (Game.core.sSessionId != null ? '&session_id=' + Game.core.sSessionId : '')
			),
			sPost,
			function (json)
			{
				// A slower, older answer must not overwrite a newer one
				if (seq != Game.mapObjects.seq)
				{
					return;
				}

				if (json && typeof (json.servertime) != 'undefined')
				{
					Game.mapObjects.setObjects (json);
				}
			}
		);
	},

	/* Replace the object set with what the server sent */
	'setObjects' : function (json)
	{
		Game.mapObjects.offset = json.servertime - Game.mapObjects.now ();

		if (json.poll)
		{
			Game.mapObjects.setPollInterval (json.poll);
		}

		var seen = {};
		for (var i = 0; i < json.objects.length; i ++)
		{
			var data = json.objects[i];
			seen[data.id] = true;

			if (Game.mapObjects.objects[data.id])
			{
				Game.mapObjects.objects[data.id].data = data;
				Game.mapObjects.updateSprite (Game.mapObjects.objects[data.id]);
			}
			else
			{
				Game.mapObjects.objects[data.id] =
				{
					'data' : data,
					'sprite' : Game.mapObjects.createSprite (data),
					'lastPx' : null
				};
			}
		}

		for (var id in Game.mapObjects.objects)
		{
			if (!seen[id])
			{
				Game.mapObjects.removeSprite (Game.mapObjects.objects[id].sprite);
				delete Game.mapObjects.objects[id];
			}
		}

		// An open label for an object that is gone
		if (Game.mapObjects.label && !seen[Game.mapObjects.label.getAttribute ('data-id')])
		{
			Game.mapObjects.removeSprite (Game.mapObjects.label);
			Game.mapObjects.label = null;
		}

		Game.mapObjects.drawRoutes ();
		Game.mapObjects.tick ();
	},

	/* Tile position of an object at server time t */
	'positionAt' : function (obj, t)
	{
		var paths = obj.paths || [];
		if (paths.length == 0)
		{
			return [obj.x, obj.y];
		}

		var first = paths[0];
		var last = paths[paths.length - 1];

		if (t < first.start)
		{
			return [first.points[0][0], first.points[0][1]];
		}

		for (var i = 0; i < paths.length; i ++)
		{
			var p = paths[i];
			if (t >= p.start && t < p.end)
			{
				var a = p.points[0];
				var b = p.points[p.points.length - 1];
				var f = (p.end > p.start) ? (t - p.start) / (p.end - p.start) : 1;
				return [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f];
			}
		}

		var end = last.points[last.points.length - 1];
		return [end[0], end[1]];
	},

	/* Pixel position (top-left of the sprite) for a tile position */
	'pixelFor' : function (x, y)
	{
		var px = Game.map.getOrthogonalCors (x * -1, y);
		var half = Game.mapObjects.SPRITE_SIZE / 2;
		return [px[0] - half, px[1] - half];
	},

	/* Remaining time of the object's current path (the next arrival), as h:mm */
	'eta' : function (obj, t)
	{
		var paths = obj.paths || [];
		if (paths.length == 0)
		{
			return '';
		}

		var end = paths[paths.length - 1].end;
		for (var i = 0; i < paths.length; i ++)
		{
			if (t < paths[i].end)
			{
				end = paths[i].end;
				break;
			}
		}

		var seconds = Math.max (0, end - t);
		var h = Math.floor (seconds / 3600);
		var m = Math.floor ((seconds % 3600) / 60);
		return h + ':' + (m < 10 ? '0' : '') + m;
	},

	/* Move every sprite to where it is now */
	'tick' : function ()
	{
		var t = Game.mapObjects.serverNow ();

		for (var id in Game.mapObjects.objects)
		{
			var o = Game.mapObjects.objects[id];
			if (!o.sprite)
			{
				continue;
			}

			var pos = Game.mapObjects.positionAt (o.data, t);
			var px = Game.mapObjects.pixelFor (pos[0], pos[1]);

			if (o.lastPx && px[0] != o.lastPx[0])
			{
				Game.mapObjects.setFacing (o.sprite, px[0] > o.lastPx[0] ? 'facing-right' : 'facing-left');
			}

			o.sprite.style.left = px[0] + 'px';
			o.sprite.style.top = px[1] + 'px';
			o.lastPx = px;
		}
	},

	/* Swap the facing class, keeping every other class on the sprite */
	'setFacing' : function (sprite, facing)
	{
		if (sprite.className.indexOf (facing) != -1)
		{
			return;
		}

		var classes = sprite.className.split (' ');
		var out = [];
		for (var i = 0; i < classes.length; i ++)
		{
			if (classes[i] != '' && classes[i].indexOf ('facing-') != 0)
			{
				out.push (classes[i]);
			}
		}
		out.push (facing);
		sprite.className = out.join (' ');
	},

	/*
		Make an element tappable on top of the map. The map's own mousedown and
		touchstart handlers start a drag and cancel the click that would follow,
		so stop those from bubbling and treat touchend as the tap.
	*/
	'attachTap' : function (el, handler)
	{
		var stop = function (event)
		{
			if (event && event.stopPropagation) { event.stopPropagation (); }
		};

		el.addEventListener ('mousedown', stop, false);
		el.addEventListener ('mouseup', stop, false);
		el.addEventListener ('touchstart', stop, false);
		el.addEventListener ('touchend', function (event)
		{
			stop (event);
			if (event && event.preventDefault) { event.preventDefault (); }	// no synthetic click
			handler (event);
		}, false);
		el.onclick = function (event)
		{
			stop (event);
			handler (event);
		};
	},

	/* DOM: one div per object */
	'createSprite' : function (data)
	{
		var div = Game.mapObjects.doc ().createElement ('div');
		div.className = 'mapobject facing-right';
		div.setAttribute ('data-kind', data.kind);
		div.style.width = Game.mapObjects.SPRITE_SIZE + 'px';
		div.style.height = Game.mapObjects.SPRITE_SIZE + 'px';
		div.style.backgroundImage = "url('" + data.sprite.url + "')";
		div.title = data.name || '';

		if (data.click)
		{
			div.className += ' clickable';
			Game.mapObjects.attachTap (div, function ()
			{
				Game.mapObjects.toggleLabel (data.id, div);
			});
		}

		Game.mapObjects.layer.appendChild (div);
		return div;
	},

	'updateSprite' : function (o)
	{
		o.sprite.setAttribute ('data-kind', o.data.kind);
		o.sprite.style.backgroundImage = "url('" + o.data.sprite.url + "')";
		o.sprite.title = o.data.name || '';
	},

	'removeSprite' : function (sprite)
	{
		if (sprite && sprite.parentNode)
		{
			sprite.parentNode.removeChild (sprite);
		}
	},

	/* Pixel polylines of the objects that reveal their path (related ones) */
	'routeLines' : function ()
	{
		var lines = [];

		for (var id in Game.mapObjects.objects)
		{
			var data = Game.mapObjects.objects[id].data;
			if (!data.click || !data.paths || data.paths.length == 0)
			{
				continue;
			}

			var line = [];
			for (var i = 0; i < data.paths.length; i ++)
			{
				var pts = data.paths[i].points;
				for (var j = 0; j < pts.length; j ++)
				{
					line.push (Game.map.getOrthogonalCors (pts[j][0] * -1, pts[j][1]));
				}
			}
			lines.push (line);
		}

		return lines;
	},

	/* Dashed route lines, one SVG polyline each (an SVG has no bitmap size limit) */
	'drawRoutes' : function ()
	{
		var svg = Game.mapObjects.routes;
		if (!svg)
		{
			return;
		}

		while (svg.children && svg.children.length > 0)
		{
			svg.removeChild (svg.children[0]);
		}

		var lines = Game.mapObjects.routeLines ();
		var doc = Game.mapObjects.doc ();
		for (var l = 0; l < lines.length; l ++)
		{
			var points = [];
			for (var k = 0; k < lines[l].length; k ++)
			{
				points.push (lines[l][k][0] + ',' + lines[l][k][1]);
			}

			var polyline = doc.createElementNS ('http://www.w3.org/2000/svg', 'polyline');
			polyline.setAttribute ('points', points.join (' '));
			polyline.setAttribute ('fill', 'none');
			polyline.setAttribute ('stroke', 'rgba(255, 255, 255, 0.7)');
			polyline.setAttribute ('stroke-width', '2');
			polyline.setAttribute ('stroke-dasharray', '6 4');
			svg.appendChild (polyline);
		}
	},

	/* Label with name, kind, ETA and an "open" button */
	'toggleLabel' : function (id, sprite)
	{
		if (Game.mapObjects.label)
		{
			var wasFor = Game.mapObjects.label.getAttribute ('data-id');
			Game.mapObjects.removeSprite (Game.mapObjects.label);
			Game.mapObjects.label = null;
			if (wasFor == id)
			{
				return;
			}
		}

		var o = Game.mapObjects.objects[id];
		if (!o)
		{
			return;
		}

		var doc = Game.mapObjects.doc ();
		var label = doc.createElement ('div');
		label.className = 'mapobject-label';
		label.setAttribute ('data-id', id);

		var kind = Game.mapObjects.kindText[o.data.kind] || o.data.kind;
		var text = doc.createElement ('p');
		text.appendChild (doc.createTextNode ((o.data.name ? o.data.name + ' · ' : '') + kind + ' · ' + Game.mapObjects.eta (o.data, Game.mapObjects.serverNow ())));
		label.appendChild (text);

		var button = doc.createElement ('a');
		button.href = 'javascript:void(0);';
		button.appendChild (doc.createTextNode (Game.mapObjects.kindText['open'] || 'Open'));
		Game.mapObjects.attachTap (button, function ()
		{
			Game.map.onObjectClick (sprite, function () { eval (o.data.click); });
			Game.mapObjects.removeSprite (Game.mapObjects.label);
			Game.mapObjects.label = null;
		});
		label.appendChild (button);

		label.style.left = (parseInt (sprite.style.left) + Game.mapObjects.SPRITE_SIZE) + 'px';
		label.style.top = sprite.style.top;
		Game.mapObjects.attachTap (label, function () {});

		Game.mapObjects.layer.appendChild (label);
		Game.mapObjects.label = label;
	}
};
