if (!Game)
{
	var Game = new Object ();
}

/*
	Ajax timeout trouble
*/
function callInProgress (xmlhttp) 
{
	switch (xmlhttp.readyState) 
	{
		case 1: 
		case 2: 
		case 3:
			return true;
		break;
		
		// Case 4 and 0
		default:
			return false;
		break;
	}
}

function showFailureMessage() 
{
	Game.gui.showConnectionError ();
	//alert('We\'ve lost connection with the server. Please reload the page and try again later.');
}

// Register global responders that will occur on all AJAX requests
Ajax.Responders.register
({
	onCreate: function(request) 
	{
		request['timeoutId'] = window.setTimeout
		(
			function() 
			{
				// If we have hit the timeout and the AJAX request is active, abort it and let the user know
				if (callInProgress(request.transport)) 
				{
					request.transport.abort();
					showFailureMessage();
								
					// Run the onFailure method if we set one up when creating the AJAX object
					if (request.options['onFailure']) 
					{
						request.options['onFailure'](request.transport, request.json);
					}
				}
			},
			45000 // 45 seconds timeout
		);
	},
	onComplete: function(request) 
	{	
		// Clear the timeout, the request completed ok
		window.clearTimeout(request['timeoutId']);
		
		if (request.getStatus () == 200)
		{
			Game.gui.hideConnectionError ();
		}
		else
		{
			showFailureMessage();
		}
	},
	onException : function (request)
	{
		showFailureMessage();
	},
	onFailure : function (request)
	{
		showFailureMessage();
	}
});

var CONFIG_GAME_URL;
var CONFIG_TIME_OFFSET;

Prototype.Browser.IE6 = Prototype.Browser.IE && parseInt(navigator.userAgent.substring(navigator.userAgent.indexOf("MSIE")+5)) == 6;
Prototype.Browser.IE7 = Prototype.Browser.IE && parseInt(navigator.userAgent.substring(navigator.userAgent.indexOf("MSIE")+5)) == 7;
Prototype.Browser.IE8 = Prototype.Browser.IE && parseInt(navigator.userAgent.substring(navigator.userAgent.indexOf("MSIE")+5)) == 8;


/*
	List possible parameters:
	=========================
	NOMAP	Don't render the game map
	NOGUI	Don't show graphical user interface
	RENDER	Render method "canvas" or "css"
*/

// Game core
Game.core = 
{

	'isMapLoaded' : false,
	
	'sCurHash' : location.hash,
	
	'sSessionId' : RUNTIME_SESSION_ID,
	
	'events' : new Array (),

	'initialize' : function ()
	{
		Game.layout.init ();

		// Fix the IE6 cache bug
		try
		{
			document.execCommand('BackgroundImageCache', false, true);
		}
		catch (err) {}
		
		// Calculate the timezone
		Game.core.setTimezoneCookie ();
		
		// Hide those horible effects
		Windows.overlayShowEffectOptions = null;
		Windows.overlayHideEfectOptions = null;
		
		// Set config time offset
		var d = new Date()
		CONFIG_TIME_OFFSET = (d.getTimezoneOffset() / 60) * -1;
		
		Game.core.observeKonami ();
		
		Game.core.observeProfiler ();
		
		Game.core.trigger ('onInitialized');
		
		Game.core.loadMap ();
		Game.core.loadGui ();
	},
	
	'loadMap' : function ()
	{
		// Create map div
		var div = document.createElement ('div');
		div.id = 'map';
		div.style.width = '100%';
		div.style.height = '100%';
		div.style.overflow = 'hidden';
		div.style.position = 'absolute';
		div.style.left = '0px';
		div.style.top = '0px';
		div.style.border = '0px none black';
		$('bodyDiv').appendChild (div);
		
		// Draw map
		if (typeof(PARAM_NOMAP) == 'undefined')
		{			
			$('bodyDiv').style.visibility = 'hidden';
			Game.core.drawMap ();
		}
	},
	
	'loadGui' : function ()
	{
		// Draw interface
		if (typeof(PARAM_NOGUI) == 'undefined')
		{
			Game.gui.initialize ();
		}
	},
	
	'getMapLocation' : function ()
	{
		// Get default settings
		var locx = typeof(PARAM_X) == 'undefined' ? 0 : parseInt(PARAM_X);
		var locy = typeof(PARAM_Y) == 'undefined' ? 0 : parseInt(PARAM_Y);
		
		var hash = Game.core.getHashLocation ();
		
		if (hash)
		{
			locx = hash[0];
			locy = hash[1];
		}
		
		return Array (locx, locy);
	},
	
	'drawMap' : function ()
	{
		// Fetch map location
		var loc = Game.core.getMapLocation ();
		
		// Loading dialog
		Game.gui.showLoadingDialog ();
		
		Game.map.onMapLoad = function ()
		{
			Game.gui.closeLoadingDialog ();
			$('bodyDiv').style.visibility = 'visible';
			
			Game.core.trigger ('mapLoad');
		}
	
		// Load map
		Game.map.loadMap (loc[0], loc[1]);
		
		Game.core.isMapLoaded = true;
		
		// Jump map if change in hash is detected
		new PeriodicalExecuter (Game.core.checkHashChange, 0.5);
	},
	
	'getWindowSize' : function ()
	{
		var div = $('bodyDiv');
		return new Array (div.offsetWidth, div.offsetHeight);
	},
	
	'getHashLocation' : function ()
	{
		var hash=location.hash;
		if (hash)
		{
			lastProcessedHash = hash;
			var cors = hash.substr(1).split (",");
			if (cors.length == 2)
			{
				locx = parseInt (cors[0]);
				locy = parseInt (cors[1]);
				
				return Array (locx, locy);
			}
			else
			{
				return false;
			}
		}
		return false;
	},
	
	/* Check for changes in the hash and jump the map if changes found */
	'checkHashChange' : function ()
	{
		if (Game.core.isMapLoaded && location.hash != Game.core.sCurHash)
		{
			var loc = Game.core.getHashLocation ();
			Game.map.mapIsoJump (loc[0], loc[1]);
			
			Game.core.sCurHash = location.hash;
		}
	},
	
	'setTimezoneCookie' : function ()
	{
		var now = new Date();
		var later = new Date();
		
		// Set time for how long the cookie should be saved
		later.setTime(now.getTime() + 365 * 24 * 60 * 60 * 1000);
		
		// Set cookie for the time zone offset in minutes
		Game.core.setCookie("time_zone_offset", now.getTimezoneOffset(), later, "/");
		
		// Create two new dates
		var d1 = new Date();
		var d2 = new Date();
		
		// Date one is set to January 1st of this year
		// Guaranteed to not be in DST
		d1.setDate(1);
		d1.setMonth(1);
		
		// Date two is set to July 1st of this year
		// Guaranteed to be in DST if DST exists for this time zone
		d2.setDate(1);
		d2.setMonth(7);
		
		// If time zone offsets match, no DST exists for this time zone
		if(parseInt(d1.getTimezoneOffset())==parseInt(d2.getTimezoneOffset()))
		{
			Game.core.setCookie ("time_zone_dst", "0", later, "/");
		}
		// DST exists for this time zone - check if it is currently active
		else 
		{
			// Current date is still before or after DST, not containing DST
			if(parseInt(d1.getTimezoneOffset())==parseInt(now.getTimezoneOffset()))
			{
				Game.core.setCookie ("time_zone_dst", "0", later, "/");
			}
			// DST is active right now with the current date
			else {
				Game.core.setCookie ("time_zone_dst", "1", later, "/");
			}
		}
	},
	
	'setCookie' : function (c_name,value,expiredays)
	{
		var exdate=new Date();
		exdate.setDate(exdate.getDate()+expiredays);
		document.cookie=c_name+ "=" +escape(value)+
			((expiredays==null) ? "" : ";expires="+exdate.toGMTString());
	},
	
	'leadingZero' : function (iValue)
	{
		if (iValue < 10)
		{
			iValue = '0'+iValue;
		}
		return iValue;
	},
	
	'observe' : function (trigger, callback)
	{
		Game.core.events.push
		(
			{
				'trigger' : trigger,
				'callback' : callback
			}
		);
	},
	
	'trigger' : function (trigger)
	{
		for (var i = 0; i < Game.core.events.length; i ++)
		{
			if (Game.core.events[i].trigger == trigger)
			{
				Game.core.events[i].callback ();
			}
		}
	},
	
	'observeKonami' : function ()
	{
		var konamiCode = '38,38,40,40,37,39,37,39,66,65';
		var keyPressed = [];
	
		Event.observe 
		(
			document, 
			'keydown', 
			function (e)
			{
				var keyCode = e.keyCode;
				keyPressed.push(keyCode);
				
				if (keyPressed.length > 10)
				{
					keyPressed.shift ();
				}
				
				if (0 <= keyPressed.toString().indexOf(konamiCode)) 
				{
					keyPressed = [];
					Game.core.trigger ('onKonamiCode');
				}
			}
		);
	},
	
	'observeProfiler' : function ()
	{
		Event.observe 
		(
			document, 
			'keydown', 
			function (e)
			{
				var keyCode = e.keyCode;
				if (keyCode == 113)
				{
					var profiler = $('profiler');
					if (profiler)
					{
						profiler.toggle ();
					}
				}
			}
		);
	},
	
	'massInclude' : function (filenames, fOnFinish)
	{
		function loadorder ()
		{
			this.counter = 0;
			
			this.onLoad = function ()
			{			
				//alert (this.counter);
				
				this.counter --;

				if (this.counter == 0)
				{
					fOnFinish ();
				}
				
				return this.counter;
			}
			
			this.runOrder = function (filenames)
			{			
				this.counter = filenames.length;
				
				var self = this;
				
				for (var i = 0; i < filenames.length; i ++)
				{
					Game.core.include (filenames[i], function () { return self.onLoad (); });
				}
			}
		}
		
		var myloadorder = new loadorder ();
		myloadorder.runOrder (filenames);
	},
	
	'include' : function (filename, onload)
	{
		/*
		var e = document.createElement("script");
		e.src = CONFIG_GAME_URL + 'client/javascript/' + filename;
		e.type="text/javascript";
		
		e.onload = onload;
		
		document.getElementsByTagName("head")[0].appendChild(e); 
		*/
		
		LazyLoader.load (CONFIG_GAME_URL + 'gameserver/javascript/' + filename, onload);
	}
}

Event.observe (window, 'load', Game.core.initialize, false);

if (!Element.prototype.addEventListener) {
  var oListeners = {};
  function runListeners(oEvent) {
    if (!oEvent) { oEvent = window.event; }
    for (var iLstId = 0, iElId = 0, oEvtListeners = oListeners[oEvent.type]; iElId < oEvtListeners.aEls.length; iElId++) {
      if (oEvtListeners.aEls[iElId] === this) {
        for (iLstId; iLstId < oEvtListeners.aEvts[iElId].length; iLstId++) { oEvtListeners.aEvts[iElId][iLstId].call(this, oEvent); }
        break;
      }
    }
  }
  Element.prototype.addEventListener = function (sEventType, fListener /*, useCapture (will be ignored!) */) {
    if (oListeners.hasOwnProperty(sEventType)) {
      var oEvtListeners = oListeners[sEventType];
      for (var nElIdx = -1, iElId = 0; iElId < oEvtListeners.aEls.length; iElId++) {
        if (oEvtListeners.aEls[iElId] === this) { nElIdx = iElId; break; }
      }
      if (nElIdx === -1) {
        oEvtListeners.aEls.push(this);
        oEvtListeners.aEvts.push([fListener]);
        this["on" + sEventType] = runListeners;
      } else {
        var aElListeners = oEvtListeners.aEvts[nElIdx];
        if (this["on" + sEventType] !== runListeners) {
          aElListeners.splice(0);
          this["on" + sEventType] = runListeners;
        }
        for (var iLstId = 0; iLstId < aElListeners.length; iLstId++) {
          if (aElListeners[iLstId] === fListener) { return; }
        }     
        aElListeners.push(fListener);
      }
    } else {
      oListeners[sEventType] = { aEls: [this], aEvts: [ [fListener] ] };
      this["on" + sEventType] = runListeners;
    }
  };
  Element.prototype.removeEventListener = function (sEventType, fListener /*, useCapture (will be ignored!) */) {
    if (!oListeners.hasOwnProperty(sEventType)) { return; }
    var oEvtListeners = oListeners[sEventType];
    for (var nElIdx = -1, iElId = 0; iElId < oEvtListeners.aEls.length; iElId++) {
      if (oEvtListeners.aEls[iElId] === this) { nElIdx = iElId; break; }
    }
    if (nElIdx === -1) { return; }
    for (var iLstId = 0, aElListeners = oEvtListeners.aEvts[nElIdx]; iLstId < aElListeners.length; iLstId++) {
      if (aElListeners[iLstId] === fListener) { aElListeners.splice(iLstId, 1); }
    }
  };
}