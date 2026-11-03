function DragCorner(container, handle) 
{
	var container = $(container);
	var handle = $(handle);

	/* Add property to container to store position variables */
	container.moveposition = {x:0, y:0};

	function moveListener(event) 
	{
		event.stop ();
	
		/* Calculate how far the mouse moved */
		var moved = 
		{
			x:(event.pointerX() - container.moveposition.x),
			y:(event.pointerY() - container.moveposition.y)
		};
		
		moved.y = moved.y * -1;
		
		/* Reset container's x/y utility property */
		container.moveposition = 
		{
			x:event.pointerX(), 
			y:event.pointerY()
		};
		
		//console.log (moved.x, moved.y);
		
		/* Border adds to dimensions */
		var borderStyle = container.getStyle('border-width');
		var borderSize = borderStyle.split(' ')[0].replace(/[^0-9]/g,'');
		
		/* Padding adds to dimensions */
		var paddingStyle = container.getStyle('padding');
		var paddingSize = paddingStyle.split(' ')[0].replace(/[^0-9]/g,'');
		
		/* Add things up that change dimensions */
		var sizeAdjust = (borderSize*2) + (paddingSize*2);
		
		sizeAdjust = 2;
		
		/* Update container's size */
		var size = container.getDimensions();
		
		var height = size.height + moved.y - sizeAdjust;
		var width = size.width + moved.x - sizeAdjust;
		
		if (width < 100)
		{
			width = 100;
		}
		
		if (height < 100)
		{
			height = 100;
		}
		
		container.setStyle
		({
			'height': (height) + 'px',
			'width': (width)+'px'
		});
		
		Game.minimap.oScrollMap.resize (width, height);
	}

	/* Listen for 'mouse down' on handle to start the move listener */
	handle.observe('mousedown', function(event) 
	{
		/* Set starting x/y */
		container.moveposition = 
		{
			x:event.pointerX(),
			y:event.pointerY()
		};
	
		/* Start listening for mouse move on body */
		Event.observe(document.body,'mousemove',moveListener);
		
		event.stop ();
	});

	/* Listen for 'mouse up' to cancel 'move' listener */
	Event.observe(document.body,'mouseup', function(event) 
	{
		Event.stopObserving(document.body,'mousemove',moveListener);
	});
}

Game.minimap =
{
	'iTileSizeX' : 400,
	'iTileSizeY' : 200,
	
	'iMapOffsetX' : 4,
	'iMapOffsetY' : 2,
	
	'oScrollMap' : null,
	
	'iZoom' : 1,
	
	'btnZoomIn' : null,
	'btnZoomOut' : null,

	/*
		Warning! Mind that the minimap can be initialized multiple times.
	*/	
	'loadMap' : function (x, y)
	{
		var cdiv = $('minimap');
		
		var div = $(document.createElement ('div'));
		div.id = 'minimap_real';
		
		div.style.width = cdiv.style.width;
		div.style.height = cdiv.style.height;
		div.style.position = 'absolute';
		div.style.zIndex = '0';
		div.style.overflow = 'hidden';
		
		cdiv.appendChild (div);
		
		if (div)
		{
			var l = Game.minimap.getOrthogonalCors (x, y);
		
			var minimap = new TiledImageViewer
			(
				div, 
				Game.minimap.getTile, 
				function () {},
				0, 
				0, 
				div.offsetWidth, 
				div.offsetHeight, 
				Game.minimap.iTileSizeX * this.iZoom, 
				Game.minimap.iTileSizeY * this.iZoom, 
				l[0],
				l[1]
			);
		
			minimap.enableControls ();
		
			minimap.setDblclickLocation (Game.minimap.scrollBigMap);
			
			Game.minimap.oScrollMap = minimap;
			
			if (typeof(Game.map) != 'undefined'
				&& Game.map.bIsLoaded)
			{
				// Create viewport
				var windowSize = Game.core.getWindowSize ();
				
				var sx = Math.floor(windowSize[0] / Game.map.nMinimapRatioX);
				var sy = Math.floor(windowSize[1] / Game.map.nMinimapRatioY);
				
				var loc = Game.minimap.getOrthogonalCors (x, y);
				var px = loc[0] * -1;
				var py = loc[1] * -1;
				
				// Move it a bit
				px -= (sx / 2);
				py -= (sy / 2);

				minimap.addInnerHTML ('<div id="minimapViewport" style="top: '+py+'px; left: '+px+'px; width: '+sx+'px; height: '+sy+'px; position: absolute; z-index: 1;">&nbsp;</div>');
			}
			
			if (cdiv.hasClassName ('premium-minimap'))
			{
				var corner = $(document.createElement ('div'));
				//corner.id = 'minimap_drag_handle';
				corner.addClassName ('resize-corner');
				
				corner.setStyle
				({
					'position':'absolute',
					'top': '0px',
					'right' : '0px',
					'z-index' : 1
				});
			
				//cdiv.innerHTML += '<div class="corner" id="dragHandle" style="background: red; position: absolute; z-index: 10000; width: 20px; height: 20px; top: 0px; right: 0px;">&nbsp;</div>';			
				cdiv.appendChild (corner);
				DragCorner($('minimap').parentNode, corner);
				
				// Zoom buttons
				var zoomin = $(document.createElement ('div'));
				zoomin.className = 'zoom-in';
				zoomin.observe ('click', Game.minimap.zoomIn);
				
				Game.minimap.btnZoomIn = zoomin;
				cdiv.appendChild (zoomin);
		
				var zoomout = $(document.createElement ('div'));
				zoomout.className = 'zoom-out';
				zoomout.observe ('click', Game.minimap.zoomOut);

				Game.minimap.btnZoomOut = zoomout;
				cdiv.appendChild (zoomout);
			}
		}
	},
	
	/*
		Fit the minimap viewer to its element again, centred on the viewport box. Needed when
		the minimap was created inside a hidden panel (0 by 0, so it drew no tiles) and is
		shown later, as the mobile layout does.
	*/
	'refreshSize' : function ()
	{
		var viewer = Game.minimap.oScrollMap;
		var div = $('minimap_real');

		if (viewer == null || !div)
		{
			return;
		}

		var w = div.offsetWidth;
		var h = div.offsetHeight;

		if (w <= 0 || h <= 0)
		{
			return;
		}

		viewer.resize (w, h);

		var vp = $('minimapViewport');
		if (vp)
		{
			var cx = parseInt (vp.style.left) + parseInt (vp.style.width) / 2;
			var cy = parseInt (vp.style.top) + parseInt (vp.style.height) / 2;

			if (!isNaN (cx) && !isNaN (cy))
			{
				viewer.jumpTo (-cx, -cy, true);
			}
		}
	},

	'zoomIn' : function ()
	{
		Game.minimap.iZoom += 0.25;
		if (Game.minimap.iZoom > 1.5)
		{
			Game.minimap.iZoom = 1.5;
		}
		
		if (Game.minimap.iZoom == 1.5)
		{
			Game.minimap.btnZoomIn.style.display = 'none';
		}

		Game.minimap.btnZoomOut.style.display = 'block';		
		Game.minimap.rescale ();
	},
	
	'zoomOut' : function ()
	{
		Game.minimap.iZoom -= 0.25;
		if (Game.minimap.iZoom < 0.5)
		{
			Game.minimap.iZoom = 0.5;
		}
		
		if (Game.minimap.iZoom == 0.5)
		{
			Game.minimap.btnZoomOut.style.display = 'none';
		}
		
		Game.minimap.btnZoomIn.style.display = 'block';		
		Game.minimap.rescale ();
	},
	
	'rescale' : function ()
	{
		Game.minimap.oScrollMap.setTilesizes 
		(
			Game.minimap.iTileSizeX * Game.minimap.iZoom, 
			Game.minimap.iTileSizeY * Game.minimap.iZoom
		);
		
		Game.map.rescaleMinimapViewport ();
		
		// Move div
		Game.map.recenterMinimap ();
	},
	
	'getTile' : function (x, y)
	{
		var div = document.getElementById('minimap_real'+x+'p'+y);
		if (div)
		{
			var width = Game.minimap.iZoom * Game.minimap.iTileSizeX;
			var height = Game.minimap.iZoom * Game.minimap.iTileSizeY;
		
			div.innerHTML = '<img src="image/minimap/?x='+x+'&y='+y+'" width="'+width+'" height="'+height+'" />';
		}
	},
	
	'getOrthogonalCors' : function (gx, gy)
	{
		var ox = Game.minimap.iMapOffsetX;
		var oy = Game.minimap.iMapOffsetY;
		
		ox *= Game.minimap.iZoom;
		oy *= Game.minimap.iZoom;
	
		gx = parseInt (gx) * -1;
		gy = parseInt (gy);
	
		//  Game.map.iMapOffsetX, Game.map.iMapOffsetY				
		return new Array 
		(
			Math.round (( gx - gy ) * ox), 
			Math.round (( gx + gy) * oy)
		);
	},
	
	'getIsometricCors' : function (x, y)
	{
		var ox = Game.minimap.iMapOffsetX;
		var oy = Game.minimap.iMapOffsetY;
		
		ox *= Game.minimap.iZoom;
		oy *= Game.minimap.iZoom;
	
		x = (x - ox) / ox ;
		y = y / oy;
	
		return new Array ((x+y)/2, (y-x)/2);
	},
	
	'mapIsoJump' : function (x, y)
	{
		var location = Game.minimap.getOrthogonalCors (x, y);
		Game.minimap.oScrollMap.jumpTo (location[0], location[1]);
	},
	
	'scrollBigMap' : function (x, y)
	{
		try
		{
			var location = Game.minimap.getIsometricCors (x, y);
			Game.map.mapIsoJump (location[0], location[1] * -1);
		}
		catch (e) {}
	}
}
