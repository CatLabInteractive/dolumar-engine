if (typeof console == "undefined" || typeof console.log == "undefined") var console = { log: function() {} }; 

// Game map
Game.map = 
{
	'oTiles' : new Object (),
	'oTileData' : new Object (),
	
	'iImagesToLoad' : 0,
	
	'iStartPosX' : 0,
	'iStartPosY' : 0,
	
	'iMapOffsetX' : (CONFIG_MAP_TILESIZE[0] / 2) + 0,
	'iMapOffsetY' :  (CONFIG_MAP_TILESIZE[1] / 2) + 0,
	
	'iMapRegionWidth' : 0,
	'iMapRegionHeight' : 0,
	
	'iScreenWidth' : 0,
	'iScreenHeight' : 0,
	
	'oScrollMap' : null,
	
	'onMapLoad' : function () {},
	
	'aRegionsToLoad' : new Array (),
	'aRegionsToDraw' : new Array (),
	
	'bUseCanvas' : false,
	
	'iRequestCount' : 0,
	'iTilesPerRegion' : 10,
	
	// Calculate the relation between minimap & big map
	'nMinimapRatioX' : 1,
	'nMinimapRatioY' : 1,
	
	'iWaitingQue' : 0,
	
	'bIsLoaded' : false,

	'fStartTimeout' : 0.1,	
	'fTimeoutSeconds' : 0.1,

	// Load the map
	'loadMap' : function (x, y)
	{	
		this.bIsLoaded = true;
	
		// Check for parameter
		if (typeof (PARAM_RENDER) != 'undefined')
		{
			Game.map.bUseCanvas = PARAM_RENDER.toLowerCase() == 'canvas';
		}
		
		if (typeof (PARAM_MARGIN) != 'undefined')
		{
			Game.map.iMapOffsetX += parseInt(PARAM_MARGIN);
			Game.map.iMapOffsetY += parseInt(PARAM_MARGIN);
		}
		
		// Check if canvas is available 
		else
		{
			try
			{
				var objCanvas = document.createElement ('canvas');
				if (objCanvas.getContext('2d'))
				{
					Game.map.bUseCanvas = true;
				}
			}
			catch (error)
			{
				Game.map.bUseCanvas = false;
			}
		}
		
		// Set width & height
		Game.map.iMapRegionWidth = Game.map.iMapOffsetX * Game.map.iTilesPerRegion * 2;
		Game.map.iMapRegionHeight = Game.map.iMapOffsetY * Game.map.iTilesPerRegion * 2;
		
		// Set screen width & height
		var iScreenSize = Game.core.getWindowSize ();
		Game.map.iScreenWidth = iScreenSize[0];
		Game.map.iScreenHeight = iScreenSize[1];
		
		// Set starting position
		Game.map.iStartPosX = x;
		Game.map.iStartPosY = y;
		
		// Load all images
		setTimeout (Game.map.preloadStandardImages, (Game.map.fStartTimeout * 1000));
		
		// Minimap ratio
		Game.map.nMinimapRatioX = Game.map.iMapOffsetX / Game.minimap.iMapOffsetX;
		Game.map.nMinimapRatioY = Game.map.iMapOffsetY / Game.minimap.iMapOffsetY;
		
		// Resize function for the minimap
		window.onresize = function()
		{
			// Resize Viewport
			Game.map.rescaleMinimapViewport ();
		};
	},
	
	'getURL' : function (url, data)
	{
		var sParams
	
		if (typeof (data) == 'undefined')
		{
			sParams = '';
		}
		else
		{
			sParams = data;
		}
	
		if (url.indexOf('?'))
		{
			return url + '&' + sParams
		}
		else
		{
			return url + '?' + sParams
		}
	},
	
	'rescaleMinimapViewport' : function (vp)
	{
		vp = document.getElementById('minimapViewport');
		if (vp)
		{
			var sizes = Game.core.getWindowSize ();
	
			var sx = Math.floor(sizes[0] / Game.map.nMinimapRatioX);
			var sy = Math.floor(sizes[1] / Game.map.nMinimapRatioY);
		
			sx *= Game.minimap.iZoom;
			sy *= Game.minimap.iZoom;
		
			vp.style.width = sx + 'px';
			vp.style.height = sy + 'px';
		}
	},
	
	// Load all images
	'preloadStandardImages' : function ()
	{
		new Ajax.Request
		(
			CONFIG_DISPATCH_URL + 'map/images/' + (Game.core.sSessionId != null ? '?session_id='+Game.core.sSessionId : ''),
			{
				method 		: 'get',
				onSuccess 	: Game.map.onLoadImagesList
			}
		);
	},
	
	// When a list of images is loaded, preload all images
	'onLoadImagesList' : function (transport)
	{
		var json = transport.responseJSON;
		
		var images = new Array ();
		
		json.each
		(
			function (sData)
			{
				//Game.map.preloadImage (sData);
				images.push (sData);
			}
		);
		
		var onDone = function ()
		{
			Game.map.onLoadImagesDone ();
		}
		
		Game.map.preloadImages (images, Game.map.preloadImage, onDone);
	},
	
	'preloadImages' : function (imageList, fLoadFunction, fOnFinish)
	{
		// If no images to preload, call the fOnFinish directly
		if (imageList.length == 0)
		{
			fOnFinish ();
			return;
		}
	
		function loadorder ()
		{
			this.counter = 0;
			
			this.onImageLoad = function ()
			{			
				//alert (this.counter);
				
				if (this.counter > 0)
				{
					this.counter --;
				}

				if (this.counter < 1)
				{
					fOnFinish ();
				}
				
				return this.counter;
			}
			
			this.runOrder = function (imageList)
			{			
				this.counter = imageList.length;
				
				var self = this;
				
				for (var i = 0; i < imageList.length; i ++)
				{
					fLoadFunction (imageList[i], function () { return self.onImageLoad (); });
				}
			}
		}
		
		var myloadorder = new loadorder ();
		myloadorder.runOrder (imageList);
	},
	
	'preloadImage' : function (sUrl, onLoad)
	{
		Game.map.oTiles[sUrl[0]] = new Image ();
		Game.map.oTileData[sUrl[0]] = new Array (sUrl[2], sUrl[3], parseInt(sUrl[4]), parseInt(sUrl[5]), sUrl[6], true);

		// Add event listener (on image load)
		//Event.observe (Game.map.oTiles[sUrl[0]], 'load', Game.map.onLoadImage);
		Game.map.oTiles[sUrl[0]].onload = function ()
		{
			var counter = onLoad ();	
			Game.gui.setLoadingText ('Loading images, '+counter+' left.');
		}

		// Set source
		Game.map.oTiles[sUrl[0]].src = CONFIG_STATIC_GAME_URL + sUrl[1];
		
		//document.body.appendChild (Game.map.oTiles[sUrl[0]]);
	},
	
	'preloadImageJustInTime' : function (imgData, afterLoad)
	{
		// This image is already loading!
		if (typeof (Game.map.oTiles[imgData.id]) != 'undefined')
		{
			// Just append the afterLoad function
			Event.observe 
			(
				Game.map.oTiles[imgData.id],
				'load',
				afterLoad
			);
		}
		
		// Does image is not loaded yet, make a fresh load.
		else
		{	
			Game.map.oTiles[imgData.id] = new Image ();
			
			Game.map.oTileData[imgData.id] = new Array 
			(
				2, 2, 
				1, -1, 
				[[0,0],[2,0],[2,2],[0,2]], 
				false
			);
		
			Event.observe 
			(
				Game.map.oTiles[imgData.id], 
				'load',
				function ()
				{
					Game.map.oTileData[imgData.id][0] = Game.map.oTiles[imgData.id].width;
					Game.map.oTileData[imgData.id][1] = Game.map.oTiles[imgData.id].height;
					
					var difx = (Game.map.iMapOffsetX * 2) - Game.map.oTiles[imgData.id].width;
					difx = difx / 2;
					
					var dify = (Game.map.iMapOffsetY * 2) - Game.map.oTiles[imgData.id].height;
					dify = dify - (difx * 0.5);
					
					Game.map.oTileData[imgData.id][2] = difx;
					Game.map.oTileData[imgData.id][3] = dify;
					
					Game.map.oTileData[imgData.id][4] = [
						[0, 0],
						[Game.map.oTiles[imgData.id].width, 0],
						[Game.map.oTiles[imgData.id].width, Game.map.oTiles[imgData.id].height],
						[0, Game.map.oTiles[imgData.id].height]
					];
			
					Game.map.oTileData[imgData.id][5] = true;
			
					if (typeof (afterLoad) == 'function')
					{
						afterLoad ();
					}
				}
			);
			
			Event.observe
			(
				Game.map.oTiles[imgData.id],
				'error',
				function ()
				{
					alert ('Could not load image: '+imgData.url);
					afterLoad ();
				}
			);
		
			// Set source
			Game.map.oTiles[imgData.id].src = imgData.url;
		}
	},
	
	// Trigger when all images are preloaded
	'onLoadImagesDone' : function ()
	{
		Game.gui.setLoadingText ('Loading map');
	
		var loc = Game.map.getInitialOrthogonalCors
		(
			Game.map.iStartPosX, 
			Game.map.iStartPosY, 
			Game.map.iMapOffsetX, 
			Game.map.iMapOffsetY 
		);
	
		// Create the map object
		Game.map.oScrollMap = new TiledImageViewer
		(
			$('map'), 
			Game.map.drawMapRegion, 
			Game.map.drawMapRegionCommit,
			0, 
			0, 
			Game.map.iScreenWidth, 
			Game.map.iScreenHeight, 
			Game.map.iMapRegionWidth, 
			Game.map.iMapRegionHeight, 
			loc[0],
			loc[1],
			Game.map.recenterMinimap
		);
		
		Game.map.oScrollMap.enableControls ();

		// Map scale: pinch is a mobile-layout feature; a pinch stores its result.
		Game.map.oScrollMap.canPinch = function ()
		{
			return Game.layout.mode () === 'mobile';
		};

		Game.map.oScrollMap.onscale = function (s)
		{
			Game.map.setScale (s);
		};

		// Choosing a location: the mobile layout uses the touch flow (Place/Cancel buttons).
		Game.map.oScrollMap.isTouchLayout = function ()
		{
			return Game.layout.mode () === 'mobile';
		};

		Game.map.applyStoredScale ();

		// The desktop layout always shows the map at scale 1.
		Game.layout.onChange (function ()
		{
			Game.map.applyStoredScale ();
		});
	},

	/* Map scale (mobile layout) */

	'SCALE_STEP' : 0.25,
	'SCALE_STORAGE_KEY' : 'dolumar.mapScale',

	// Set the map scale and remember it in this browser.
	'setScale' : function (s)
	{
		if (Game.map.oScrollMap == null)
		{
			return;
		}

		Game.map.oScrollMap.setScale (s);

		try
		{
			window.localStorage.setItem (Game.map.SCALE_STORAGE_KEY, String (Game.map.oScrollMap.getScale ()));
		}
		catch (e) {}
	},

	'zoomIn' : function ()
	{
		if (Game.map.oScrollMap != null)
		{
			Game.map.setScale (Game.map.oScrollMap.getScale () + Game.map.SCALE_STEP);
		}
	},

	'zoomOut' : function ()
	{
		if (Game.map.oScrollMap != null)
		{
			Game.map.setScale (Game.map.oScrollMap.getScale () - Game.map.SCALE_STEP);
		}
	},

	// Mobile layout: use the stored scale. Desktop layout: scale 1 (only reset if it was changed).
	'applyStoredScale' : function ()
	{
		var viewer = Game.map.oScrollMap;
		if (viewer == null)
		{
			return;
		}

		if (Game.layout.mode () !== 'mobile')
		{
			if (viewer.getScale () !== 1)
			{
				viewer.setScale (1);
			}
			return;
		}

		var stored = null;
		try
		{
			stored = window.localStorage.getItem (Game.map.SCALE_STORAGE_KEY);
		}
		catch (e) {}

		var s = parseFloat (stored);
		if (!isNaN (s) && s !== viewer.getScale ())
		{
			viewer.setScale (s);
		}
	},
	
	/* Region drawing */
	
	// Request one region, but wait until the commit to actually draw it.
	'drawMapRegion' : function (x, y)
	{
		Game.map.aRegionsToLoad.push (new Array (x, y));
	},
	
	// Commit a request, loading all regions at once
	'drawMapRegionCommit' : function ()
	{	
		if (Game.map.aRegionsToLoad.length > 0)
		{
			// Show loading icon
			Game.gui.showLoadingIcon ();
		
			var sPost = "";
			for (var i = 0; i < Game.map.aRegionsToLoad.length; i ++)
			{
				sPost += 'regions['+i+'][0]='+Game.map.aRegionsToLoad[i][0]+'&';
				sPost += 'regions['+i+'][1]='+Game.map.aRegionsToLoad[i][1]+'&';
			}

			// Create the request
			Game.map.iRequestCount ++;
			new Ajax.Request
			(
				Game.map.getURL 
				(
					CONFIG_DISPATCH_URL + 'map/region/', 
					'tiles='+Game.map.iTilesPerRegion+(Game.core.sSessionId != null ? '&session_id='+Game.core.sSessionId : '')
				),
				{
					method 		: 'post',
					onSuccess 	: Game.map.processMapRegionRequest,
					postBody	: sPost
				}
			);
		}
		
		// Don't forget to clean the que
		Game.map.aRegionsToLoad = new Array ();
	},
	
	'isImageLoaded' : function (imgdetails)
	{
		return typeof (Game.map.oTileData[imgdetails.id]) != 'undefined'
			&& Game.map.oTileData[imgdetails.id][5] == true;
	},
	
	/*
		This function is called whenever a region request
		has been loaded. It checks if all images in the
		region request are loaded. If not, it loads the
		images prior to drawing the regions.
	*/
	'loadJustInTimeImages' : function (images, fContinue)
	{
		var toload = new Array ();
		for (var img in images)
		{
			if (!Game.map.isImageLoaded (images[img]))
			{
				toload.push (images[img]);
			}
		}
		
		if (toload.length == 0)
		{
			fContinue ();
		}
		else
		{
			Game.map.preloadImages (toload, Game.map.preloadImageJustInTime, fContinue)
		}
	},
	
	// Process the above call
	'processMapRegionRequest' : function (transport)
	{
		var json = transport.responseJSON;
		
		// Update performance div (debug really)
		var perdiv = $('server_parsetime_map');
		if (perdiv)
		{
			perdiv.innerHTML = json['parsetime'];
		}
		
		Game.core.sSessionId = json['session_id'];

		if (json['images'].length === 0)
		{
			Game.map.startDrawingRegions (json);
			return;
		}
		
		Game.map.loadJustInTimeImages 
		(
			json['images'], 
			function ()
			{
				Game.map.startDrawingRegions (json);
			}
		);

		if (typeof (json.profiler) != 'undefined')
		{
			Game.gui.profiler.add (json.profiler);
		}
	},
	
	'startDrawingRegions' : function (json)
	{
		var regions = json['regions'];
		var aRegionsToDraw = new Array ();
	
		for (var i = 0; i < regions.length; i ++)
		{
			var x = regions[i]['x'];
			var y = regions[i]['y'];
						
			var div = $('map'+x+'p'+y);
			
			if (div)
			{
				//Game.map.queDrawRegion (regions[i], json['images'], div, x, y);
				aRegionsToDraw.push ([ regions[i], json['images'], div, x, y ]);
			}
		}
		
		// Do the drawwing		
		new PeriodicalExecuter
		(
			function (timer) 
			{
				var que = aRegionsToDraw;
			
				// Fetch a region
				var region = que.pop ();
			
				// Stop the timer
				if (que.length == 0)
				{
					// Stop the timer
					timer.stop ();
				}
	
				// Draw the region
				if (region)
				{
					try
					{				
						// Do the draw
						Game.map.doDrawRegion (region[0], region[1], region[2], region[3], region[4]);
					}
					catch (e)
					{
						console.log (e);
					}
				}
			
				// Check for trigger
				if (que.length == 0)
				{				
					// Check if done, trigger function and reset.
					if (Game.map.onMapLoad != null)
					{
						Game.map.onMapLoad ();
						Game.map.onMapLoad = null;
					}
				
					// Hide loading icon
					Game.gui.hideLoadingIcon ();
				}
			
				try
				{
					// Update status
					Game.gui.setLoadingText ('Drawing map... '+aRegionsToDraw.length+' left');
				}
				catch (e) {}

			},
			Game.map.fTimeoutSeconds
		);
	},
	
	// Draw one region from data
	'doDrawRegion' : function (region, images, div, x, y)
	{	
		// Empty div
		div.innerHTML = '';
		
		// Check for canvas support
		if (Game.map.bUseCanvas)
		{
			var canvas = document.createElement('canvas');
		
			canvas.style.width = div.style.width;
			canvas.style.height = div.style.height;
		
			canvas.width = parseInt(div.style.width);
			canvas.height = parseInt(div.style.height);
		
			var ctx = canvas.getContext('2d');
			CanvasTextFunctions.enable(ctx);
			
			div.appendChild (canvas);
			
			var imageDrawContainer = ctx;
			var imageDrawFunction = Game.map.doDrawImage_canvas;
		}
		else
		{
			var imageDrawContainer = div;
			var imageDrawFunction = Game.map.doDrawImage_css;
		}
		
		// Create the image map
		var currentTime = new Date()
		var imagemapid = div.id + '_map_' + currentTime.getTime ();
		
		var imagemap = document.createElement ('map');
		imagemap.id = imagemapid;
		imagemap.name = imagemapid;
		
		var clickables = new Array ();
		
		var offset = 0;

		// thze layers
		var hasMoreLayers = true;
		var layer = 0;
		var i = 0;

		while (hasMoreLayers)
		{
			i = layer;
			layer ++;

			hasMoreLayers = false;

			for (x = -3; x < (Game.map.iTilesPerRegion * 2) + 1; x ++)
			//for (var x in region['tiles'])
			{
				if (x > Game.map.iTilesPerRegion)
				{
					offset = (x - Game.map.iTilesPerRegion + 1) * 2;
				}
				else
				{
					offset = 0;
				}
				
				for (y = 0 - x + offset - 3; y < x - offset + 4; y ++)
				//for (var y in region['tiles'][x])
				{
					if (i >= region['tiles'][x][y].length)
					{
						continue;
					}

					else if (!hasMoreLayers && (i + 1) < region['tiles'][x][y].length)
					{
						hasMoreLayers = true;
					}

					var imgdetails = images[region['tiles'][x][y][i]];
			
					if (typeof (imgdetails) != 'undefined' && imgdetails)
					{
						var f = function ()
						{
							imageDrawFunction (imageDrawContainer, imgdetails, parseInt(x), parseInt(y), 0, 0, true);
						}
				
						if (!Game.map.isImageLoaded (imgdetails))
						{
							console.log ('Image not loaded ('+x+','+y+'): '+imgdetails);
						}
				
						else
						{
							f ();
						}

						// Draw objects on this location.
						if 
						(
							typeof (region['objects'][x]) != 'undefined' && 
							typeof (region['objects'][x][y]) != 'undefined'
						)
						{
							clickables.push 
							(
								Game.map.doDrawObjects 
								(
									region['objects'][x][y], 
									images, 
									imagemap, 
									div,
									imageDrawFunction, 
									imageDrawContainer
								)
							);
						}
					}
					else
					{
						console.log ('Image not found ('+x+','+y+'): '+imgdetails + " (should be " + region['tiles'][x][y] + ")");
					}
				}
			}
		}
		
		// Run trough the stack clickables & append
		for (var i = clickables.length - 1; i >= 0; i --)
		{
			Game.map.parseClickables (imagemap, clickables[i]);
		}

		div.appendChild (imagemap);
		
		// Put a huge image on top of the region
		var overlay = document.createElement ('img');
		overlay.src = CONFIG_GAMESERVER_URL+'images/spacer.gif';
		overlay.style.width = div.style.width;
		overlay.style.height = div.style.height;
		overlay.style.position = 'absolute';
		overlay.style.left = '0px';
		overlay.style.top = '0px';
		overlay.style.zIndex = 10;
		overlay.useMap = '#'+imagemapid;

		div.appendChild (overlay);
	},
	
	'parseClickables' : function (imagemap, clickables)
	{
		// Empty thze stack while parsing.
		while (clickables.length > 0)
		{
			Game.map.parseClickable (imagemap, clickables.pop ());
		}
	},
	
	'parseClickable' : function (imagemap, clickable)
	{
		area = $(document.createElement ('area'));
		area.shape = 'poly';
		area.coords = clickable.coordinates;
		area.href = 'javascript:void(0);';
		eval ("area.observe ('click', "+clickable.onclick+");");
		area.onmouseover = function () { this.style.cursor = 'pointer'; }
		area.onmouseout = function () { this.style.cursor = 'default'; }
		area.title = clickable.title;

		imagemap.appendChild (area);
	},
	
	// Draw one image, using CSS
	'doDrawImage_css' : function (div, imgDetails, x, y, oox, ooy, shiftImage, zindex)
	{		
		var sKey = imgDetails.id;
	
		if (typeof (zindex) == 'undefined')
		{
			zindex = 0;
		}
	
		var oDiv = document.createElement ('div');

		oDiv.style.width = Game.map.oTileData[sKey][0] + 'px';
		oDiv.style.height = Game.map.oTileData[sKey][1] + 'px';
		
		oDiv.style.background = "url('" + Game.map.oTiles[sKey].src + "')";
	
		// Adjust size (according to height & width)
		if (shiftImage)
		{		
			var height = Game.map.oTileData[sKey][1];
			if (height > (Game.map.iMapOffsetY * 2))
			{
				ooy += Math.floor (Game.map.iMapOffsetY * 2 - height);
			}
		}
		
		var position = Game.map.getOrthogonalCors (x, y);
	
		// Set position
		oDiv.style.position = 'absolute';
		
		var iLeft = (position[0] + oox);
		var iTop = (position[1] + ooy);
	
		oDiv.style.left = iLeft + 'px';
		oDiv.style.top = iTop + 'px';
		
		if (zindex > 0)
		{
			oDiv.style.zIndex = zindex;
		}

		div.appendChild (oDiv);

		return [ Game.map.oTileData[sKey][0], Game.map.oTileData[sKey][1], iLeft, iTop, oDiv ];
	},
	
	// Draw one image, using canvas
	'doDrawImage_canvas' : function (ctx, imgDetails, x, y, oox, ooy, shiftImage)
	{
		var sKey = imgDetails.id;
	
		if (typeof (Game.map.oTileData[sKey]) == 'undefined')
		{
			return false;
		}
		
		// Adjust size (according to height & width)
		if (shiftImage)
		{		
			var height = Game.map.oTileData[sKey][1];
			if (height > (Game.map.iMapOffsetY * 2))
			{
				ooy += Math.floor (Game.map.iMapOffsetY * 2 - height);
			}
		}
		
		var position = Game.map.getOrthogonalCors (x, y);
		
		var iLeft = Math.floor (position[0] + oox);
		var iTop = Math.floor (position[1] + ooy);
		
		ctx.drawImage (Game.map.oTiles[sKey], iLeft, iTop, Game.map.oTileData[sKey][0], Game.map.oTileData[sKey][1]);

		return [ Game.map.oTileData[sKey][0], Game.map.oTileData[sKey][1], iLeft, iTop ];
	},
	
	// Draw all objects
	'doDrawObjects' : function (objn, images, imagemap, div, imageDrawFunction, imageDrawContainer)
	{
		// Clickable stack!
		var clickables = new Array ();
	
		for (var oi = 0; oi < objn.length; oi ++)
		{
			if (typeof (images[objn[oi]['m']]) != 'undefined')
			{
				var tx = objn[oi]['tx'];
				var ty = objn[oi]['ty'];
				
				// z-index: 0 will be drawn in the map, higher will be drawn "on top of" the map.
				// z-index must be higher then 0 in order for moveable objects to work.
				var zindex = typeof (objn[oi]['z']) != 'undefined' ? parseInt(objn[oi]['z']) : 0;
	
				var ii = parseFloat (objn[oi]['i']);
				var jj = parseFloat (objn[oi]['j']);
				
				// imageKey is the key used in oTileData and oTile
				var imageKey = images[objn[oi]['m']];
				
				Game.map.drawSingleObject 
				(
					imageKey, 
					objn[oi], 
					div, 
					imageDrawContainer, 
					imageDrawFunction, 
					ii, jj, 
					clickables, 
					zindex,
					imagemap
				);
			}
			else
			{
				//alert ('Object not found: '+images[objn[oi]['m']].id);
				alert ('Object not found: ' + objn[oi]['m']);
			}
		}
		
		return clickables;
	},
	
	/*
		This function is called when an image has to be loaded.
	*/
	'drawSingleObject' : function (imageKey, obj, div, imageDrawContainer, imageDrawFunction, ii, jj, clickables, zindex, imagemap)
	{
		var f = function ()
		{
			var imageData = Game.map.oTileData[imageKey.id];

			if (zindex > 0)
			{
				Game.map.drawSingleObjectOnMap (obj, div, imageDrawContainer, imageDrawFunction, imageKey, ii, jj, imageData, clickables, zindex);
			}
			else
			{
				Game.map.drawSingleObjectInMap (obj, div, imageDrawContainer, imageDrawFunction, imageKey, ii, jj, imageData, clickables);
			}
		}
		
		var e = function ()
		{
			f ();
			Game.map.parseClickables (imagemap, clickables);

			//Game.map.reloadMap ();
		}
		
		if (!Game.map.isImageLoaded (imageKey))
		{
			//Game.map.preloadImageJustInTime (imageKey, e);
		}
		else
		{
			f ();
		}
	},
	
	/*
		Draw an image on top of the map
	*/
	'drawSingleObjectOnMap' : function (obj, div, imageDrawContainer, imageDrawFunction, imageKey, ii, jj, imageData, clickables, zindex)
	{
		// Always use the css function
		var data = Game.map.doDrawImage_css
		(
			div,
			imageKey,
			ii, jj,
			imageData[2], imageData[3],
			false,
			(11 + zindex)
		);
		
		div = data[4];
		
		// We must append the onclick ourselves. Since these
		// might be movable, we can't use the imagemap.
		//eval ("div.onclick = function () { "+obj['c']+" };");
		eval ("div.observe('click', function (event) { Game.map.onObjectClick (Event.element(event), function () {"+obj['c']+"} ); } );");
		div.title = obj['n'];
		div.className += ' clickable movable';
	},	
	
	/*
		Draw an image within the map
	*/
	'drawSingleObjectInMap' : function (obj, div, imageDrawContainer, imageDrawFunction, imageKey, ii, jj, imageData, clickables)
	{
		var iSize = imageDrawFunction 
		(
			imageDrawContainer, 
			imageKey, 
			ii, jj, 
			imageData[2], imageData[3], 
			false
		);
		
		if (iSize != false && typeof (iSize) != 'undefined')
		{
			// Create clickable area (at the imagemap).

			// Make the coordinates
			var cors = "";
			for (var i = 0; i < imageData[4].length; i ++)
			{
				cors += (iSize[2] + imageData[4][i][0])+","
				cors += (iSize[3] + imageData[4][i][1])+",";
			}
			
			var evts = "";
			
			for (var i = 0; i < obj['c'].length; i ++)
			{
				evts += obj['c'][i];
			}
			
			// Add the clickable to the stack
			clickables.push 
			({
				'coordinates' : cors.substr (0, cors.length - 1),
				'onclick' : 'function (event) { Game.map.onObjectClick (Event.element (event), function () { '+evts+' }); }',
				'title' : obj['n']
			});
		}
		else
		{
			alert ('Object not found: '+imageKey);
		}
	},
	
	// Is called on every move, recenter the minimap
	'recenterMinimap' : function (x, y)
	{
		if (typeof (x) == 'undefined' && typeof (y) == 'undefined')
		{
			if (typeof(Game.map.oScrollMap) != 'undefined' && Game.map.oScrollMap != null)
			{
				var location = Game.map.oScrollMap.getLocation ();
				x = location.left;
				y = location.top;
			}
		}
	
		var vp = document.getElementById('minimapViewport');
		if (vp)
		{
			var px = Math.floor(x / Game.map.nMinimapRatioX);
			var py = Math.floor(y / Game.map.nMinimapRatioY);
			
			px *= Game.minimap.iZoom;
			py *= Game.minimap.iZoom;
		
			vp.style.left = px + 'px';
			vp.style.top = py + 'px';
		}
	},
	
	/* Logic */
	
	// Calculate isometric location from give (mouse) position
	'getIsometricCors' : function (x, y)
	{
		x = (x / Game.map.iMapOffsetX) - 1;
		y = (y / Game.map.iMapOffsetY) + 0;
	
		return new Array ((x+y)/2, ((y-x)/ -2)+1);
	},

	// Calculates the first location
	'getInitialOrthogonalCors' : function (x, y, ox, oy)
	{
		var cors = Game.map.getOrthogonalCors (x*-1, y);
		return new Array (cors[0]-ox, cors[1]-oy);
	},
	
	// Calculate the location for a tile
	'getOrthogonalCors' : function (gx, gy)
	{
		gx = parseFloat (gx);
		gy = parseFloat (gy);
	
		//  Game.map.iMapOffsetX, Game.map.iMapOffsetY				
		return new Array 
		(
			Math.round (( gx - gy ) * Game.map.iMapOffsetX), 
			Math.round (( gx + gy) * Game.map.iMapOffsetY)
		);
	},
	
	// Jump function
	'mapIsoJump' : function (x, y)
	{
		if (Game.map.oScrollMap != null)
		{
			var location = Game.map.getOrthogonalCors (x * -1, y);
			Game.map.oScrollMap.jumpTo (location[0], location[1]);
		}
		
		// Check for mini-map
		if (typeof (Game.minimap) != 'undefined' && Game.minimap.oScrollMap != null)
		{
			Game.minimap.mapIsoJump (x, y);
		}
	},
	
	// Reload a given location
	'reloadLocation' : function (x, y)
	{
		if (Game.map.oScrollMap != null)
		{
			var locs = Game.map.getOrthogonalCors (x * -1, y);
	
			var nX = locs[0] / (Game.map.iMapOffsetX * ( 0 - Game.map.iTilesPerRegion * 2));
			var nY = locs[1] / (Game.map.iMapOffsetY * ( 0 - Game.map.iTilesPerRegion * 2));

			var fX = Math.floor (nX);
			var fY = Math.floor (nY);
	
			var cX = Math.ceil (nX);
			var cY = Math.ceil (nY);
	
			var o = new Array ();
	
			// Reload base tile
			o.push (Array (fX, fY));
	
			var mBorder = 0.2;
	
			var r = (cX - nX) <= mBorder;
			var l = (nX - fX) <= mBorder;
			var b = (cY - nY) <= mBorder;
			var t = (nY - fY) <= mBorder;
	
			if (r) { o.push (Array (fX + 1, fY)); }
			if (l) { o.push (Array (fX - 1, fY)); }
			if (b) { o.push (Array (fX, fY + 1)); }
			if (t) { o.push (Array (fX, fY - 1)); }
	
			// Diagonalen
			if (l && t) { o.push (Array (fX - 1, fY - 1)); }
			if (t && r) { o.push (Array (fX + 1, fY - 1)); }
			if (r && b) { o.push (Array (fX + 1, fY + 1)); }
			if (b && l) { o.push (Array (fX - 1, fY + 1)); }
	
			Game.map.oScrollMap.reloadTiles (o);
		}
	},
	
	'reloadMap' : function ()
	{
		Game.map.oScrollMap.reloadAllTiles ();
	},
	
	// Select location
	'selectLocation' : function (fTrigger, onFinish, sImage)
	{
		// Fetch the right image
		if (typeof (onFinish) == 'undefined')
		{
			onFinish = function () { }
		}
		
		// Trigger thze function in thze scrollmap
		if (Game.map.oScrollMap != null)
		{
			if (sImage != null)
			{
				var pointer = 
				({
					'src' : sImage.url,
					'offsetx' : sImage.offsetX,
					'offsety' : sImage.offsetY
				});
			}
			else
			{
				var pointer = null;
			}
		
			Game.map.oScrollMap.selectLocation
			(
				function (x, y)
				{
					// Calculate the right coordinates from orto cors
					var cors = Game.map.getIsometricCors (x, y);
					fTrigger (cors[0], cors[1]);
				},
				onFinish,
				pointer
			);
		}
	},
	
	'move' : function ()
	{
		//alert ('moving.');
		
	},
	
	/*
		This function is called every time
		an object is clicked.
		
		@attribute command: contains a javascript function
	*/
	'onObjectClick' : function (div, command)
	{
		//console.log (div);
		command ();
	}
}
