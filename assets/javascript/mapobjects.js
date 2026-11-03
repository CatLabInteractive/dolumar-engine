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
	'poll' : 30,
	'layer' : null,
	'canvas' : null,
	'label' : null,
	'tickTimer' : null,
	'pollTimer' : null,
	'kindText' : {},	// filled by the game template: kind -> translated text

	'now' : function ()
	{
		return Date.now () / 1000;
	},

	'serverNow' : function ()
	{
		return Game.mapObjects.now () + Game.mapObjects.offset;
	},

	/* Setup: called once the TiledImageViewer exists */
	'init' : function ()
	{
		if (Game.mapObjects.layer || !Game.map.oScrollMap)
		{
			return;
		}

		var layer = document.createElement ('div');
		layer.className = 'mapobjects';
		Game.map.oScrollMap.div.appendChild (layer);
		Game.mapObjects.layer = layer;

		var canvas = document.createElement ('canvas');
		canvas.className = 'mapobjects-routes';
		layer.appendChild (canvas);
		Game.mapObjects.canvas = canvas;

		if (typeof (CONFIG_MAPOBJECT_TEXT) != 'undefined')
		{
			Game.mapObjects.loadText (CONFIG_MAPOBJECT_TEXT);
		}

		Game.mapObjects.tickTimer = setInterval (Game.mapObjects.tick, 1000);
		Game.mapObjects.setPollInterval (Game.mapObjects.DEFAULT_POLL);
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
		if (Game.mapObjects.pollTimer && Game.mapObjects.poll == seconds)
		{
			return;
		}

		if (Game.mapObjects.pollTimer)
		{
			clearInterval (Game.mapObjects.pollTimer);
		}

		Game.mapObjects.poll = seconds;
		Game.mapObjects.pollTimer = setInterval (Game.mapObjects.poll_, seconds * 1000);
	},

	/* Ask the server for the objects touching the loaded regions */
	'poll' : function ()
	{
		Game.mapObjects.poll_ ();
	},

	'poll_' : function ()
	{
		if (!Game.map.oScrollMap)
		{
			return;
		}

		var tiles = Game.map.oScrollMap.loadedMapTiles;
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

		new Ajax.Request
		(
			Game.map.getURL
			(
				CONFIG_DISPATCH_URL + 'map/objects/',
				'tiles=' + Game.map.iTilesPerRegion + (Game.core.sSessionId != null ? '&session_id=' + Game.core.sSessionId : '')
			),
			{
				method : 'post',
				postBody : sPost,
				onSuccess : function (transport)
				{
					var json = transport.responseJSON;
					if (json && typeof (json.servertime) != 'undefined')
					{
						Game.mapObjects.setObjects (json);
					}
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

	/* Remaining time of the object's last path, as h:mm */
	'eta' : function (obj, t)
	{
		var paths = obj.paths || [];
		if (paths.length == 0)
		{
			return '';
		}

		var seconds = Math.max (0, paths[paths.length - 1].end - t);
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
				var facing = px[0] > o.lastPx[0] ? 'facing-right' : 'facing-left';
				if (o.sprite.className.indexOf (facing) == -1)
				{
					o.sprite.className = 'mapobject ' + facing;
				}
			}

			o.sprite.style.left = px[0] + 'px';
			o.sprite.style.top = px[1] + 'px';
			o.lastPx = px;
		}
	},

	/* DOM: one div per object */
	'createSprite' : function (data)
	{
		var div = document.createElement ('div');
		div.className = 'mapobject facing-right';
		div.setAttribute ('data-kind', data.kind);
		div.style.width = Game.mapObjects.SPRITE_SIZE + 'px';
		div.style.height = Game.mapObjects.SPRITE_SIZE + 'px';
		div.style.backgroundImage = "url('" + data.sprite.url + "')";
		div.title = data.name || '';

		if (data.click)
		{
			div.className += ' clickable';
			div.onclick = function (event)
			{
				Game.mapObjects.toggleLabel (data.id, div);
				if (event) { event.stopPropagation (); }
			};
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

	/* Dashed route lines for the objects that reveal their path */
	'drawRoutes' : function ()
	{
		var canvas = Game.mapObjects.canvas;
		if (!canvas || !canvas.getContext)
		{
			return;
		}

		var minX = 0, minY = 0, maxX = 0, maxY = 0, any = false;
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
					var px = Game.map.getOrthogonalCors (pts[j][0] * -1, pts[j][1]);
					line.push (px);
					if (!any) { minX = maxX = px[0]; minY = maxY = px[1]; any = true; }
					minX = Math.min (minX, px[0]); maxX = Math.max (maxX, px[0]);
					minY = Math.min (minY, px[1]); maxY = Math.max (maxY, px[1]);
				}
			}
			lines.push (line);
		}

		if (!any)
		{
			canvas.width = 0;
			canvas.height = 0;
			return;
		}

		canvas.style.left = minX + 'px';
		canvas.style.top = minY + 'px';
		canvas.width = Math.max (1, maxX - minX);
		canvas.height = Math.max (1, maxY - minY);

		var ctx = canvas.getContext ('2d');
		ctx.strokeStyle = 'rgba(255, 255, 255, 0.7)';
		ctx.lineWidth = 2;
		if (ctx.setLineDash) { ctx.setLineDash ([6, 4]); }

		for (var l = 0; l < lines.length; l ++)
		{
			ctx.beginPath ();
			for (var k = 0; k < lines[l].length; k ++)
			{
				var p = lines[l][k];
				if (k == 0) { ctx.moveTo (p[0] - minX, p[1] - minY); }
				else { ctx.lineTo (p[0] - minX, p[1] - minY); }
			}
			ctx.stroke ();
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

		var label = document.createElement ('div');
		label.className = 'mapobject-label';
		label.setAttribute ('data-id', id);

		var kind = Game.mapObjects.kindText[o.data.kind] || o.data.kind;
		var text = document.createElement ('p');
		text.appendChild (document.createTextNode ((o.data.name ? o.data.name + ' · ' : '') + kind + ' · ' + Game.mapObjects.eta (o.data, Game.mapObjects.serverNow ())));
		label.appendChild (text);

		var button = document.createElement ('a');
		button.href = 'javascript:void(0);';
		button.appendChild (document.createTextNode (Game.mapObjects.kindText['open'] || 'Open'));
		button.onclick = function (event)
		{
			Game.map.onObjectClick (sprite, function () { eval (o.data.click); });
			Game.mapObjects.removeSprite (Game.mapObjects.label);
			Game.mapObjects.label = null;
			if (event) { event.stopPropagation (); }
		};
		label.appendChild (button);

		label.style.left = (parseInt (sprite.style.left) + Game.mapObjects.SPRITE_SIZE) + 'px';
		label.style.top = sprite.style.top;
		label.onclick = function (event) { if (event) { event.stopPropagation (); } };

		Game.mapObjects.layer.appendChild (label);
		Game.mapObjects.label = label;
	}
};
