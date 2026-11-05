if (typeof console == "undefined" || typeof console.log == "undefined") var console = { log: function() {} }; 

Game.gui =
{

	'objLoadingDialog' : null,
	
	'aOpenWindows' : new Array (),
	
	'bIsInDialog' : 0,
	'iLoadingIcons' : 0,
	
	'sGuiTheme' : CONFIG_THEME,
	
	'objCountDown' : null,
	'objFastCount' : null,
	
	'aPools' : new Array (),
	'iPollInterval' : new Array (),
	'iPollCountDown' : new Array (),
	
	'aRunningPolls' : new Array (),
	
	'gaTracker' : null,
	'fTracker' : function (sData) {},
	
	'piwikTracker' : null,
	'fPiwiTracker' : function (sData) {},
	
	'lastActivity' : new Date (),
	'maxIdleSeconds' : 60 * 60 * 24 * 7,
	
	'events' : new Array (),
	
	'oDialog' : null,
	
	'appname' : CONFIG_GAME_NAME,

	// To replace the old system
	'profiler' : {
		'add' : function (string)
		{
			if ($('profiler'))
			{
				$('profiler').value = profiler;
			}
		}
	},
	
	'initAnalytics' : function ()
	{
		// Check for google analytics
		if (typeof (GOOGLE_ANALYTICS) != 'undefined')
		{
			try
			{
				Game.gui.gaTracker = _gat._getTracker(GOOGLE_ANALYTICS);
				
				Game.gui.gaTracker._setCustomVar (1, "Server", CONFIG_ENV_SERVERNAME, 2); 
				Game.gui.gaTracker._setCustomVar (2, "ServerID", CONFIG_ENV_SERVERID, 2);
				Game.gui.gaTracker._setCustomVar (3, "Container", CONFIG_ENV_CONTAINER, 2);
				Game.gui.gaTracker._setCustomVar (4, "Domain", CONFIG_GAME_DOMAIN, 2);
				
				Game.gui.gaTracker._setCustomVar (5, "Premium", CONFIG_IS_PREMIUM ? 'premium' : 'member', 1);
				
				Game.gui.fTracker = function (sCall)
				{
					sCall = sCall.toLowerCase ();
					Game.gui.gaTracker._trackPageview (sCall);
				}
			}
			catch (err) {}
		}
		
		// Piwik
		if (typeof (PIWIK_ANALYTICS) != 'undefined')
		{
			try {
				Game.gui.piwiTracker = Piwik.getTracker(pkBaseURL + "piwik.php", PIWIK_ANALYTICS);
				Game.gui.piwiTracker.enableLinkTracking ();
				
				Game.gui.piwiTracker.setCustomVariable (1, "Server", CONFIG_ENV_SERVERNAME, 'page'); 
				Game.gui.piwiTracker.setCustomVariable (2, "ServerID", CONFIG_ENV_SERVERID, 'page');
				Game.gui.piwiTracker.setCustomVariable (3, "Container", CONFIG_ENV_CONTAINER, 'page');
				Game.gui.piwiTracker.setCustomVariable (4, "Domain", CONFIG_GAME_DOMAIN, 'page');
				
				Game.gui.piwiTracker.setCustomVariable (5, "Premium", CONFIG_IS_PREMIUM ? 'premium' : 'member', 'page');
				
				Game.gui.fPiwiTracker = function (sCall)
				{
					sCall = sCall.toLowerCase ();
					Game.gui.piwiTracker.trackPageView (sCall);
				}
				
			} catch( err ) { }
		}
	},

	'initialize' : function ()
	{
		var toOpen = new Array ('game:Initialize');
		var params = new Array ({});
		var inputdata = new Array ({});
		
		if (CONFIG_IS_TESTSERVER)
		{
			toOpen.push ('Help');
			params.push ({'page':'Testserver'});
			inputdata.push ({});
		}
	
		Game.gui.openNewWindow 
		(
			toOpen,
			params,
			inputdata
		);
		
		// Pollers & "real time" features.
		Game.gui.startPollActions ();
		Game.gui.observe ('update', Game.gui.resetElementsCache);
		
		// Close key
		WindowCloseKey.init();

		// Move open windows between Prototype windows and sheets when the layout mode changes.
		if (Game.layout && Game.layout.onChange)
		{
			Game.layout.onChange (Game.gui.relayoutWindows);
		}
		
		// Call "initialize" tracker
		Game.gui.trackIt ('loading');
	},
	
	'trackIt' : function (oData)
	{
		Game.gui.fTracker (oData);
		Game.gui.fPiwiTracker (oData);
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
	
	'openNewWindow' : function (sWindowId, oRequestData, oInputData)
	{
		if (typeof (oInputData) === 'undefined')
		{
			oInputData = {};
		}

		// Window id
		if (!(sWindowId instanceof Array))
		{
			// Single window load: a regular one
			Game.gui.trackIt (sWindowId);
		
			sWindowId = new Array (sWindowId);
			oRequestData = new Array (oRequestData);
			oInputData = new Array (oInputData);
		}
		else
		{
			Game.gui.trackIt ('initialize');
		}
		
		// Loop trough all windows
		var aData = "";
		for (var i = 0; i < sWindowId.length; i ++)
		{
			/*
			if (typeof(oRequestData[i]) == 'undefined')
			{
				oRequestData[i] = new Object ();
			}
			*/
		
			aData += 'openwindow['+i+'][sWindowId]='+sWindowId[i]+'&';
			aData += 'openwindow['+i+'][sRequestData]='+(Object.toJSON(oRequestData[i]))+'&'
			aData += 'openwindow['+i+'][sInputData]='+(Object.toJSON(oInputData[i]))+'&'
		}
		
		// Send the data
		Game.gui.doDialogRequest (aData, null, true);
	},
	
	'doDialogRequest' : function (sAdditionalPost, sPool, bIsActive)
	{	
		if (typeof (sAdditionalPost) == 'undefined' || sAdditionalPost == null)
		{
			sAdditionalPost = "";
		}
		
		if (typeof (sPool) == 'undefined' || sPool == null)
		{
			sPool = 'general';
		}
		
		if (typeof (bIsActive) == 'undefined' || bIsActive == null)
		{
			bIsActive = false;
		}
		
		var date = new Date ();
		
		// Check if we should bother the server with this:
		if (!bIsActive)
		{
			var diff = date.getTime () - Game.gui.lastActivity.getTime();
		
			if (diff > Game.gui.maxIdleSeconds * 1000)
			{
				Game.gui.showConnectionError ('timeout');
				return;
			}
		}
		
		// Seperate counter for the post
		var pi = 0;
		
		sPost = "";
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			// Only process the data from this pool
			if (Game.gui.aOpenWindows[i].sPool == sPool)
			{
				sPost += 'updatewindow['+pi+'][sWindowId]='+Game.gui.aOpenWindows[i].sWindowId+'&';
				sPost += 'updatewindow['+pi+'][sRequestData]='+Game.gui.aOpenWindows[i].sRequestData+'&';
				sPost += 'updatewindow['+pi+'][sInputData]='+Game.gui.aOpenWindows[i].sInputData+'&';
				sPost += 'updatewindow['+pi+'][sDialogId]='+Game.gui.aOpenWindows[i].sDialogId+'&';
			
				// Reset input data
				Game.gui.aOpenWindows[i].sInputData = '{}';
				
				// Increse post id
				pi ++;
			}
		}
		
		// Register this request
		// Put to 0 on 2 ocasions: if it's null (no requests made so far)
		// or if the counter is below zero (due to server issues -> should never happen!)
		if (typeof (Game.gui.aRunningPolls[sPool]) == 'undefined' || 
			Game.gui.aRunningPolls[sPool] < 0)
		{
			Game.gui.aRunningPolls[sPool] = 1;
		}
		
		else
		{
			Game.gui.aRunningPolls[sPool] ++;
		}
		
		// Loading icon
		if (bIsActive)
		{
			Game.gui.showLoadingIcon ();
			
			// Log this action ;-)
			Game.gui.lastActivity = date;
		}
		
		var sUrl = Game.gui.getURL (CONFIG_DISPATCH_URL + 'dialog/',
			(Game.core.sSessionId != null ? 'session_id=' + Game.core.sSessionId : '') 
			+ '&rand=' + date.getTime ());
		
		new Ajax.Request
		(
			sUrl,
			{
				method : 'post',
				postBody : sPost + sAdditionalPost,
				onComplete : function (transport)
				{
					Game.gui.aRunningPolls[sPool] --;
					
					if (bIsActive)
					{
						Game.gui.hideLoadingIcon ();
					}
				},
				onSuccess : function (transport)
				{
					try
					{
						Game.gui.processDialogRequest (transport);
					}
					catch (e)
					{
						var text = transport.responseText;
						
						var pos = text.indexOf('<?xml');
						
						if (pos > 0)
						{
							text = text.substr (0, pos);
						}
						
						if (text.length > 0)
						{
							//alert (text);
						}
					}
				}
			}
		);
	},
	
	'processDialogRequest' : function (transport)
	{		
		var data = transport.responseXML;
		data = data.getElementsByTagName('root')[0];

		var commands = data.getElementsByTagName('command');
		for (var i = 0; i < commands.length; i ++)
		{
			try
			{
				var command = commands[i].attributes.getNamedItem('command').value;
			
				switch (command)
				{
					case 'refresh':

						$('bodyDiv').fade
						(
							{ 
								duration: 5.0,
								afterFinish : function ()
								{
									window.location.reload();
								}
							}
						);

					break;
				}
			}
			catch (e)
			{
				//alert (e.message);
			}
		}
		
		// Fetch the new windows
		var aNewWindows = data.getElementsByTagName('openwindow');
		for (var i = 0; i < aNewWindows.length; i ++)
		{
			try
			{
				var sWindowId = aNewWindows[i].attributes.getNamedItem('windowId').value;
				var sRequestData = aNewWindows[i].getElementsByTagName('requestData')[0].firstChild.data;
			
				if (!Game.gui.isDuplicate (sWindowId, sRequestData))
				{
					Game.gui.doOpenWindow (aNewWindows[i]);
				}
			}
			catch (e)
			{
				//alert (e.message);
			}
		}
		
		// Fetch the updated windows
		var aUpdateWindows = data.getElementsByTagName('updatewindow');
		for (var i = 0; i < aUpdateWindows.length; i ++)
		{
			try
			{
				var sDialogId = aUpdateWindows[i].attributes.getNamedItem('dialogId').value;
				var oWindow = Game.gui.getWindowFromDialogId (sDialogId);
				
				if (oWindow)
				{
					// Update the request data
					oWindow.sRequestData = aUpdateWindows[i].getElementsByTagName('requestData')[0].firstChild.data;
					
					// Check for poll changes
					if (aUpdateWindows[i].attributes.getNamedItem('pollInterval') != null)
					{
						oWindow.iPollInterval = aUpdateWindows[i].attributes.getNamedItem('pollInterval').value;
					}
				
					var oUpdates = aUpdateWindows[i].getElementsByTagName('update');
					for (var j = 0; j < oUpdates.length; j ++)
					{
						try
						{
							Game.gui.processUpdate (oWindow, oUpdates[j]);
						}
						catch (e)
						{
							alert (e);
						}
					}
					
					// Check for onload functions
					var onLoad = aUpdateWindows[i].attributes.getNamedItem('onLoad').value;
					if (typeof(onLoad) != 'undefined')
					{
						try
						{
							Game.gui.execMethod (onLoad, oWindow);
						}
						catch (e)
						{
							alert ('onLoad function "'+onLoad+'" failed:\n' + e.message + '\n' + e.fileName + '\n' + e.lineNumber);
						}
					}
				}
			}
			catch (e)
			{
				//alert (e);
			}
		}
		
		// Fetch some server stuff
		var runtime = data.getElementsByTagName('runtime')[0];
		
		if ($('server_sqlcount') && $('server_parsetime_gui'))
		{
			$('server_sqlcount').innerHTML = runtime.getElementsByTagName('mysqlcount')[0].firstChild.data;
			$('server_parsetime_gui').innerHTML = runtime.getElementsByTagName('parsetime')[0].firstChild.data;
		}
		
		// Update the session ID
		Game.core.sSessionId = runtime.getElementsByTagName('session_id')[0].firstChild.data;
		
		// Update poll interval
		Game.gui.updatePollInterval ();
		
		Game.gui.trigger ('update');
		
		// Profiler
		var profiler = runtime.getElementsByTagName('profiler')[0].firstChild.data;
		Game.gui.profiler.add (profiler);
		
		try
		{
			Lightbox.updateImageList ();
		}
		catch (e)
		{
		}
	},
	
	/*
		Fetch XHTML contentn and import it 
		into the element. This is by far the
		best solution I've found for this (using
		the self defined _importNode method, see above)
	*/
	'setHTMLFromXML' : function (div, content)
	{
		/*
		div.innerHTML = "";
	
		var child,
			i,
			children = content.childNodes,
			imported;
			
		for (i=0; (child = children[i]); i = i + 1) 
		{
			//console.dir(child);
			imported = document._importNode(child, true);
			
			if (imported != null)
			{
				//console.dir(imported);
				div.appendChild(imported);
			}
		}
		*/
		
		try 
		{
			// Gecko-based browsers, Safari, Opera.
			var c = (new XMLSerializer()).serializeToString(content);
		}
		catch (e) 
		{
			try 
			{
				// Internet Explorer.
				var c = content.xml;
			}
			catch (e)
			{
				//Strange Browser ??
				alert('XMLserializer not supported');
				return;
			}
		}
		
		var len = content.nodeName.length + 2;
		var args = 0;
		
		for (var i = 0; i < content.attributes.length; i ++)
		{		
			args += 1 + content.attributes[i].nodeName.length + 1 
				+ content.getAttribute (content.attributes[i].nodeName).length;
		}
		
		c = c.substr (len + args + 2);
		c = c.substr (0, c.length - (len + 1));

		c = c.replace (/<([a-zA-Z]+)([a-zA-Z ="]*)\/>/gi, '<$1$2></$1>');
		c = c.replace (/<br( *)><\/br( *)>/gi, '<br />');
		
		//alert (c);
		
		div.innerHTML = c;
	},
	
	'processUpdate' : function (oWindow, oUpdate)
	{
		var sAction = oUpdate.attributes.getNamedItem('action').value;

		switch (sAction)
		{
			case 'updateContent':
				// Update the content of this div
				var content = oUpdate.getElementsByTagName('content');
				if (content.length > 0 && typeof (content[0].firstChild) != null)
				{
					content = content[0];
				
					Game.gui.setHTMLFromXML (oWindow.div, content);
					Game.gui.onUpdateContent (oWindow.div);
				}
			break;
			
			case 'closeWindow':
				Game.gui.destroyWindow (oWindow);
			break;
			
			case 'scrollmap':
				Game.map.mapIsoJump 
				(
					oUpdate.attributes.getNamedItem('x').value,
					oUpdate.attributes.getNamedItem('y').value
				);
			break;
			
			case 'reloadLocation':
				Game.map.reloadLocation 
				(
					oUpdate.attributes.getNamedItem('x').value,
					oUpdate.attributes.getNamedItem('y').value
				);
			break;
			
			case 'reloadMap':
				Game.map.reloadMap ();
			break;

			case 'refreshMapObjects':
				if (typeof (Game.mapObjects) != 'undefined')
				{
					Game.mapObjects.refresh ();
				}
			break;
			
			case 'addContentToElement':
				// Add content to an object & make it visible (= scroll down or up)
				var content = oUpdate.getElementsByTagName('content')[0];
				//content = (new XMLSerializer()).serializeToString(content);
				
				var elements = oWindow.div.select('.' + oUpdate.attributes.getNamedItem('className').value);
				for (var i = 0; i < elements.length; i ++)
				{	
					var newdiv = document.createElement ('div');
					//newdiv.innerHTML = content;
					Game.gui.setHTMLFromXML (newdiv, content);

					newdiv = $(newdiv);
					
					switch (oUpdate.attributes.getNamedItem('position').value)
					{
						case 'top':

							// remember old scroll
							var oldscroll = $(elements[i]).scrollTop;
							var cheight = 0;

							// remove "loading" elements
							$(elements[i]).select ('.remove-after-insert-before').each (function (value) { 
								cheight += value.getDimensions ().height;

								cheight += value.measure ('padding-top');
								cheight += value.measure ('padding-bottom');
								cheight += value.measure ('margin-top');
								cheight += value.measure ('margin-bottom');

								value.remove (); 
							});

							// insert new content
							elements[i].insertBefore (newdiv, elements[i].firstChild);

							var height = newdiv.getDimensions ().height;

							height += newdiv.measure ('padding-top');
							height += newdiv.measure ('padding-bottom');
							height += newdiv.measure ('margin-top');
							height += newdiv.measure ('margin-bottom');

							var scroll = height - cheight;

							// scroll back to original location
							$(elements[i]).scrollTop = scroll;
							//Game.gui.scrollDivUp (elements[i]);
						break;
						
						// And scroll down
						case 'bottom':
						default:
							// Fetch the div
							var dim = $(elements[i]).getDimensions ();
							var iTop = elements[i].scrollHeight - dim.height;
						
							// only scroll if it's already fully scrolled down.
							var doScroll = parseInt(elements[i].scrollTop) == parseInt(iTop);
							
							if (iTop < 0)
							{
								doScroll = true;
							}
						
							//elements[i].innerHTML += content;
							elements[i].appendChild (newdiv);
							
							if (doScroll == true)
							{
								Game.gui.scrollDivDown (elements[i]);
							}
						break;
					}
				}
			break;
			
			case 'showNewsflash':
				// Temporary remove all content from the div and make a nice scrolling text
				
				var content = oUpdate.getElementsByTagName('content')[0];
				var newdiv = document.createElement ('div');
				
				Game.gui.setHTMLFromXML (newdiv, content);
				
				Game.gui.showNewsflash (oWindow, newdiv);
			break;
			
			case 'alert':
				var msg = oUpdate.getElementsByTagName('message')[0].firstChild.data;
				Game.gui.alertMessage (msg);
			break;
			
			case 'dialog':
				var msg = oUpdate.getElementsByTagName('message')[0].firstChild.data;
				var sLabelOne = oUpdate.getElementsByTagName('label1')[0].firstChild.data;
				var sLabelTwo = oUpdate.getElementsByTagName('label2')[0].firstChild.data;
				var sActionOne = oUpdate.getElementsByTagName('action1')[0].firstChild.data;
				var sActionTwo = oUpdate.getElementsByTagName('action2')[0].firstChild.data;
			
				Game.gui.dialogMessage (oWindow, msg, sLabelOne, sActionOne, sLabelTwo, sActionTwo);
			break;
			
			case 'reloadWindow':
				window.location.reload();
			break;
			
			case 'popup':
				var sUrl = oUpdate.getElementsByTagName('url')[0].firstChild.data;
				var iWidth = oUpdate.getElementsByTagName('width')[0].firstChild.data;
				var iHeight = oUpdate.getElementsByTagName('height')[0].firstChild.data;
			
				Game.gui.openWindow (sUrl, iWidth, iHeight);
			break;
			
			case 'highlight':
				var id = oUpdate.getElementsByTagName('id')[0].firstChild.data;
				
				var element = $(id);
				if (element)
				{					
					var tmp = function (el)
					{
						var slf = el;
						
						slf.addClassName ('click-me');
						Game.gui.onUpdateContent (slf);
					
						slf.observe 
						(
							'click', 
							function (event) 
							{
								slf.removeClassName ('click-me');
								Game.gui.onUpdateContent (slf);
							}
						);	
					}
					
					tmp (element);
				}
			break;
		}
	},
	
	'onUpdateContent' : function (element)
	{
		Game.gui.trigger ('contentChange', element);
	
		try
		{
			var el = element.select ('.focus');
		
			if (el.length > 0)
			{
				Game.gui.scrollIntoView (el[0]);
			}
		}
		catch (e) {}
	},
	
	'scrollIntoView' : function (element)
	{
		element.scrollIntoView ();
	},
	
	'openWindow' : function (sUrl, width, height)
	{
		/*
		return window.open 
		(
			sUrl, 
			'dolumarpopup', 
			'width='+parseInt(iWidth)+',height='+parseInt(iHeight)+',scrollbars=yes,toolbar=no,location=no,menubar=no,directories=no,resizable=yes'
		);
		*/
		
		var size = $('bodyDiv').getDimensions ();
		
		if (typeof (width) != 'undefined' && typeof (height) != 'undefined'
			&& width < size.width && height < size.height)
		{
			// We goan we da hier keer proberen mé van die iframes zie
			win = new Window
			({
				className:	Game.gui.sGuiTheme, 
				width: 		parseInt(width),
				height: 	parseInt(height), 
				destroyOnClose:	true, 
				recenterAuto:	true,
				parent: 	$('bodyDiv'),
				maximizable : false,
				minimizable : false,
				closable : true,
			
				showEffect : Element.show,
				hideEffect : Element.hide
			}); 
		
			win.setURL (sUrl);
		
			win.showCenter(true);
			win.setConstraint(true, {top: 31, bottom: 0, left: 0, right: 0});
			win.toFront();	
		
			return true;
		}
		else
		{
			return window.open 
			(
				sUrl, 
				'catlabguipopup', 
				'width='+parseInt(width)+',height='+parseInt(height)+',scrollbars=yes,toolbar=no,location=no,menubar=no,directories=no,resizable=yes'
			);
		}
	},

	'scrollDivUp' : function (div)
	{
		var dim = $(div).getDimensions ();
		div.scrollTop = 0;
		
		// Make sure this happens on image load aswell
		var imgs = $(div).select('img');
		for (var j = 0; j < imgs.length; j ++)
		{
			imgs[j].observe
			(
				'load',
				function ()
				{
					var dim = $(div).getDimensions ();
					div.scrollTop = 0;
				}
			);
		}
	},
	
	'scrollDivDown' : function (div)
	{
		var dim = $(div).getDimensions ();
		div.scrollTop = div.scrollHeight - dim.height;
		
		// Make sure this happens on image load aswell
		var imgs = $(div).select('img');
		for (var j = 0; j < imgs.length; j ++)
		{
			imgs[j].observe
			(
				'load',
				function ()
				{
					var dim = $(div).getDimensions ();
					div.scrollTop = div.scrollHeight - dim.height;
				}
			);
		}
	},
	
	/*
		Optional hook: newsflashHost (oWindow) returns the element a newsflash scrolls
		over instead of the window's own div (for a game that hides that panel), or null.
	*/
	'newsflashHost' : null,

	'showNewsflash' : function (oWindow, content)
	{
		var host = (typeof (Game.gui.newsflashHost) == 'function') ? Game.gui.newsflashHost (oWindow) : null;
		if (host)
		{
			oWindow = { 'div' : $(host) };
		}

		// Make a nice little div on top of the elemnt
		var newsdiv = $(document.createElement ('div'));
		
		newsdiv.addClassName ('newsflash');
		newsdiv.style.position = 'absolute';
		newsdiv.style.width = oWindow.div.getWidth () + 'px';
		newsdiv.style.height = oWindow.div.getHeight () + 'px';
		newsdiv.style.left = '0px';
		newsdiv.style.top = '0px';
		newsdiv.style.overflow = 'hidden';
		newsdiv.style.display = 'none';
		newsdiv.style.zIndex = 10;
		
		oWindow.div.appendChild (newsdiv);
		
		// First, make this div appear
		Effect.Appear
		(
			newsdiv,
			{
				duration: 0.5,
				afterFinish : function ()
				{
					// Make the moving div
					var tempdiv = $(document.createElement ('div'));
		
					tempdiv.style.position = 'absolute';
					tempdiv.style.left = '0px';
					tempdiv.style.overflow = 'hidden';
					tempdiv.style.width = 'auto';
					tempdiv.style.whiteSpace = 'nowrap';
		
					//tempdiv.innerHTML = content;
					tempdiv.appendChild (content);

					newsdiv.appendChild (tempdiv);
					var tmpDivWidth = tempdiv.getWidth ();
		
					var divWidth = oWindow.div.getWidth ();
					tempdiv.style.left = divWidth + 'px';
					
					var oWindow2 = oWindow;
					var newsdiv2 = newsdiv;
		
					new Effect.Move
					(
						tempdiv,
						{
							x: 0 - tmpDivWidth,
							y: 0,
							mode: 'absolute',
							transition: Effect.Transitions.linear,
							duration: 10,
							afterFinish : function () 
							{ 
								//Make it dissapear
								Effect.Fade
								(
									newsdiv2,
									{
										afterFinish : function ()
										{
											oWindow.div.removeChild (newsdiv);
										}
									}
								)
							}
						}
					);
				}
			}
		);
	},

	'execMethod' : function (string, window)
	{
		if (string == "")
			return;

		var h = string.indexOf('(');

		if (h > -1)
		{
			var newmethod = string.substring (0, h + 1);
			newmethod += "window, " + string.substring (h + 1, string.length);

			eval (newmethod);
		}
		else
		{
			eval (string + '(window);');	
		}
	},
	
	'doOpenWindow' : function (oWindowXML)
	{
		var width = oWindowXML.attributes.getNamedItem('width').value;
		var height = oWindowXML.attributes.getNamedItem('height').value;
		
		var minwidth = oWindowXML.attributes.getNamedItem('minWidth').value;
		var minheight = oWindowXML.attributes.getNamedItem('minHeight').value;
		
		var left = (oWindowXML.attributes.getNamedItem('left').value);
		var right = (oWindowXML.attributes.getNamedItem('right').value);
		var top = (oWindowXML.attributes.getNamedItem('top').value);
		var bottom = (oWindowXML.attributes.getNamedItem('bottom').value);
		
		var title = oWindowXML.attributes.getNamedItem('title').value;
		var className = oWindowXML.attributes.getNamedItem('className').value;
		
		var content = oWindowXML.getElementsByTagName('content')[0];
		
		var sWindowId = oWindowXML.attributes.getNamedItem('windowId').value;
		var sRequestData = oWindowXML.getElementsByTagName('requestData')[0].firstChild.data;
		
		var sDialogId = oWindowXML.attributes.getNamedItem('dialogId').value;
		
		// Check for onload functions
		var onLoad = oWindowXML.attributes.getNamedItem('onLoad').value;
		
		var isClosable = oWindowXML.attributes.getNamedItem('closable').value == 1;
		
		var modal = oWindowXML.attributes.getNamedItem('modal').value == 1;
		var center = oWindowXML.attributes.getNamedItem('center').value == 1;
		
		var fOnLoad = function ()
		{
			try
			{
				Game.gui.execMethod (onLoad, Game.gui.aOpenWindows[key]);
			}
			catch (e)
			{
				alert ('onLoad function "'+onLoad+'" failed:\n' + e + '\n' + e.fileName + '\n' + e.lineNumber);
			}
		}
		
		bottom = bottom > 0 ? parseInt (bottom) : null;
		right = right > 0 ? parseInt (right) : null;
		left = left > 0 ? parseInt (left) : null;
		top = top > 0 ? parseInt (top) : null;

		var div;
		var completeWindow;
		var settings = null;
		var oSheet = null;
	
		if (oWindowXML.attributes.getNamedItem('type').value == 'window')
		{
			settings = {
				className:	Game.gui.sGuiTheme, 
				title: 		title, 
				id: 		Game.gui.nextWindowId (), 
				width: 		parseInt(width),
				height: 	parseInt(height), 
				
				minWidth:	parseInt(minwidth),
				minHeight:	parseInt(minheight),
				
				destroyOnClose:	true, 
				recenterAuto:	true,
				parent: 	$('bodyDiv'),

				onDestroy:	function (element) 
				{ 
					var div = (element.getContent());
					Game.gui.destroyWindow(div.dialogId, true); 
				},
				maximizable : false,
				minimizable : true,
				closable : isClosable,
				
				showEffect : Element.show,
				hideEffect : Element.hide
			};
			
			if (bottom != null) settings.bottom = bottom;
			if (right != null) settings.right = right;
			if (left != null) settings.left = left;
			if (top != null) settings.top = top;
		
			if (Game.layout.mode() === 'mobile')
			{
				oSheet = Game.sheets.open ({
					dialogId :	sDialogId,
					title :		title,
					closable :	isClosable,
					modal :		modal,
					className :	className
				});

				win = null;
				div = oSheet.content;
				completeWindow = oSheet.element;
			}
			else
			{
				win = new Window (settings);
				
				div = $(win.getContent ());
				completeWindow = $(win.element);

				if (className != null)
				{
					completeWindow.addClassName(className);
					win._getWindowBorderSize ();
				}
			}


			Game.gui.setHTMLFromXML (div, content);
			
			//div.update(content);
			//div.appendChild (content);
		}
		
		else if (oWindowXML.attributes.getNamedItem('type').value == 'invisible')
		{
			div = new Element('div');
			div.style.display = 'none';
			$('bodyDiv').appendChild (div);
			win = null;
			completeWindow = div;
		}
		
		// Bar
		else
		{
			// Create new (extended) div
			div = new Element('div');
			completeWindow = div;

			div.style.width = width;
			div.style.height = height;
			div.style.position = 'absolute';
			
			div.addClassName('panel');
			
			Game.gui.setHTMLFromXML (div, content);
			//div.appendChild (content);
			
			$('bodyDiv').appendChild (div);
			
			win = null;
		}
		
		Game.gui.onUpdateContent (div);
		
		div.dialogId = sDialogId;
		
		div.addClassName('guiwindow');
		
		// Check for custom classname
		if (className != null)
		{
			div.addClassName(className);
		}
		
		var iPollInterval = parseInt(oWindowXML.attributes.getNamedItem('pollInterval').value);
		var sPool = oWindowXML.attributes.getNamedItem('pool').value;
		
		var key = Game.gui.aOpenWindows.push
		({
			'sDialogId' : sDialogId,
			'bLoadOnlyOnce' : false,
			'div' : div,
			'sWindowId' : sWindowId,
			'sRequestData' : sRequestData,
			'sRequestDataText' : Object.toJSON(sRequestData),
			'oWindow' : win,
			'oSheet' : oSheet,
			'settings' : settings,
			'className' : className,
			'modal' : modal,
			'center' : center,
			'sInputData' : '{}',
			'iPollInterval' : iPollInterval,
			'sPool' : sPool
		}) - 1;
		
		// Add the pool
		if (typeof (Game.gui.iPollInterval[sPool]) == 'undefined')
		{
			// This is a new pool. add it!
			Game.gui.aPools.push (sPool);
			
			Game.gui.iPollInterval[sPool] =  new Array ();
			Game.gui.iPollCountDown[sPool] = new Array ();
		}
		
		if (win)
		{		
			win.setConstraint(true, {top: 31, bottom: 0, left: 0, right: 0});

			if (center)
			{
				win.showCenter (modal);
			}
			else
			{
				win.show (modal);
			}
			
			win.toFront();	
			
			try
			{
				CSBfleXcroll(win.getContent().id);
			}
			catch (e)
			{
			}
		}
		
		// Check for dialog
		if (Game.gui.oDialog)
		{
			Game.gui.oDialog.toFront ();
		}
		
		// Onload function
		fOnLoad ();
	},
	
	/*
		Called when the layout mode changes: turn every open window into a
		sheet (mode 'mobile') or every sheet back into a Prototype window
		(mode 'desktop'). The window entries themselves stay in
		aOpenWindows with the same dialog ids, so polling is unaffected.
		Panels and invisible windows (no oWindow, no oSheet) are left alone.
	*/
	/*
		A DOM id for a new Prototype window. Without one, window.js uses "window_" plus the
		current time in milliseconds, which repeats when several windows are created in the
		same millisecond (relayoutWindows does exactly that).
	*/
	'iWindowCounter' : 0,

	'nextWindowId' : function ()
	{
		Game.gui.iWindowCounter ++;
		return 'gamewindow_' + Game.gui.iWindowCounter;
	},

	'relayoutWindows' : function (mode)
	{
		var windows = Game.gui.aOpenWindows;

		for (var i = 0; i < windows.length; i ++)
		{
			var entry = windows[i];

			if (mode == 'mobile' && entry.oWindow)
			{
				Game.gui.windowToSheet (entry);
			}
			else if (mode == 'desktop' && entry.oSheet)
			{
				Game.gui.sheetToWindow (entry);
			}
		}

		// Sheets that are not windows (sheetMessage confirms) know how to move themselves.
		if (mode == 'desktop')
		{
			var sheets = Game.sheets.stack.slice ();
			for (var j = 0; j < sheets.length; j ++)
			{
				if (typeof (sheets[j].toDesktop) == 'function')
				{
					sheets[j].toDesktop ();
				}
			}
		}

		if (Game.gui.oDialog)
		{
			Game.gui.oDialog.toFront ();
		}
	},

	/*
		Move the children of the entry's content element into a new content
		element, which then becomes entry.div (with the marks that
		getWindowFromElement and the window CSS need).
	*/
	'moveWindowContent' : function (entry, target)
	{
		target = $(target);

		while (entry.div.firstChild)
		{
			target.appendChild (entry.div.firstChild);
		}

		target.dialogId = entry.sDialogId;
		target.addClassName ('guiwindow');

		if (entry.className)
		{
			target.addClassName (entry.className);
		}

		entry.div = target;
	},

	'windowToSheet' : function (entry)
	{
		var win = entry.oWindow;

		var sheet = Game.sheets.open ({
			dialogId :	entry.sDialogId,
			title :		entry.settings.title,
			closable :	entry.settings.closable,
			modal :		entry.modal,
			className :	entry.className
		});

		Game.gui.moveWindowContent (entry, sheet.content);

		// Destroy the window without forgetting the entry.
		win.options.onDestroy = null;
		if (win.modal)
		{
			// Drop the modal overlay; destroy () alone leaves it behind. With destroyOnClose
			// the hide effect would destroy the window as well, and a second destroy throws.
			win.options.destroyOnClose = false;
			win.doNotNotifyHide = true;
			win.hide ();
		}
		win.destroy ();

		entry.oWindow = null;
		entry.oSheet = sheet;
	},

	'sheetToWindow' : function (entry)
	{
		entry.settings.id = Game.gui.nextWindowId ();

		var win = new Window (entry.settings);

		if (entry.className)
		{
			$(win.element).addClassName (entry.className);
			win._getWindowBorderSize ();
		}

		Game.gui.moveWindowContent (entry, win.getContent ());

		// Remove the sheet without calling destroyWindow.
		Game.sheets.close (entry.oSheet);

		entry.oSheet = null;
		entry.oWindow = win;

		win.setConstraint (true, {top: 31, bottom: 0, left: 0, right: 0});

		if (entry.center)
		{
			win.showCenter (entry.modal);
		}
		else
		{
			win.show (entry.modal);
		}

		win.toFront ();
	},

	'destroyWindow' : function (dialog, isTrigger)
	{
		if (typeof (dialog) == 'string')
		{
			dialog = Game.gui.getWindowFromDialogId (dialog);
		}
		
		if (typeof (isTrigger) == 'undefined')
		{
			isTrigger = false;
		}
		
		// If this window has an actual window, and it's not a trigger, just close the window
		if (!isTrigger && dialog.oWindow != null)
		{
			dialog.oWindow.destroy ();
		}
		else
		{
			if (dialog.oSheet)
			{
				// A sheet has no Window to destroy: remove the sheet (back and
				// close already did that before calling us as a trigger).
				if (!isTrigger)
				{
					Game.sheets.close (dialog.oSheet);
				}
			}
			else if (dialog.oWindow == null)
			{
				try
				{
					$('bodyDiv').removeChild (dialog.div);
				}
				catch (err)
				{}
			}
		
			// Splice!		
			Game.gui.aOpenWindows.splice (dialog.iOpenWindowKey, 1);
		
			// Update poll interval
			Game.gui.updatePollInterval ();
		}
	},
	
	'getWindowFromDialogId' : function (sDialogId)
	{
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			if (Game.gui.aOpenWindows[i].sDialogId == sDialogId)
			{
				var o = Game.gui.aOpenWindows[i];
				o.iOpenWindowKey = i;
				return o;
			}
		}
		return false;
	},
	
	'getWindowsFromWindowId' : function (sWindowId)
	{
		var out = new Array ();
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			if (Game.gui.aOpenWindows[i].sWindowId.toLowerCase() == sWindowId.toLowerCase())
			{
				out.push (Game.gui.aOpenWindows[i]);
			}
		}
		return out;
	},
	
	'isDuplicate' : function (sWindowId, sRequestData)
	{
		sRequestData = Object.toJSON(sRequestData);
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			if 
			(
				(
					Game.gui.aOpenWindows[i].bLoadOnlyOnce == true && 
					Game.gui.aOpenWindows[i].sWindowId.toLowerCase() == sWindowId.toLowerCase()
				)
				|| 
				(
					Game.gui.aOpenWindows[i].sWindowId.toLowerCase() == sWindowId.toLowerCase() &&
					Game.gui.aOpenWindows[i].sRequestDataText == sRequestData
				)
			)
			{
				Game.gui.focusWindow (Game.gui.aOpenWindows[i]);
				return true;
			}
		}
		return false;
	},
	
	/*
		Bring an open window to the front: a Window on desktop, a sheet in mobile mode.
	*/
	'focusWindow' : function (entry)
	{
		if (entry.oWindow)
		{
			entry.oWindow.toFront();
		}
		else if (entry.oSheet)
		{
			Game.sheets.focus (entry.oSheet);
		}
	},
	
	/*
		Loading dialog
	*/
	'showLoadingDialog' : function ()
	{
		if (Game.gui.bIsInDialog <= 0)
		{
			var dialog = Dialog.info 
			(
				"Loading...", 
				{
					width:250, 
					height:45, 
					className:Game.gui.sGuiTheme,
					showEffect : Element.show,
					hideEffect : Element.hide
				}
			); 
			
			Game.gui.oDialog = dialog;
		
			Game.gui.trigger ('showLoadingDialog', dialog);
		}
		
		Game.gui.bIsInDialog += 1;
	},
	
	'setLoadingText' : function (sText)
	{
		if (Game.gui.bIsInDialog > 0)
		{
			try
			{
				Dialog.setInfoMessage ('<strong>Loading '+Game.gui.appname+'</strong><br />' + sText);
			}
			catch (e) {}
		}
	},
	
	'closeLoadingDialog' : function ()
	{
		if (Game.gui.bIsInDialog == 1)
		{
			try
			{
				Dialog.closeInfo ();
				
				Game.gui.trigger ('hideLoadingDialog');
			}
			catch (e) {}
		}
		
		Game.gui.bIsInDialog --;
	},
	
	'showLoadingIcon' : function ()
	{
		Game.gui.iLoadingIcons ++;
		
		if (Game.gui.iLoadingIcons == 1)
		{
			var div = $('loading_signal');
			if (div)
			{
				div.show();
				$('bodyDiv').addClassName ('busy');
			}
		}
	},
	
	'hideLoadingIcon' : function ()
	{
		Game.gui.iLoadingIcons --;
		
		if (Game.gui.iLoadingIcons < 1)
		{
			var div = $('loading_signal');
			if (div)
			{
				div.hide();
				$('bodyDiv').removeClassName ('busy');
			}
		}
	},
	
	'alertMessage' : function (sText)
	{
		Dialog.alert 
		(
			'<p class="alert">'+sText+'</p>',
			{
				okLabel : 'Ok',
				width: '200px',
				height: '80px',
				className: Game.gui.sGuiTheme
			}
		);
	},
	
	'dialogMessage' : function (oWindow, sText, sLabelOne, sActionOne, sLabelTwo, sActionTwo)
	{
		if (typeof (sActionOne) != 'function')
		{
			if (typeof (sActionOne) == 'undefined')
			{
				sActionOne = 'void(0);';
			}
			
			// Prepare the functions: all references to the element
			// (this) should be replaced by the window dialog ID.
			sActionOne = sActionOne.replace (/this/, oWindow.sDialogId);
			var fActionOne = function () { eval (sActionOne); return true; }
		}
		else
		{
			var fActionOne = sActionOne;
		}
		
		if (typeof (sActionTwo) != 'function')
		{
			if (typeof (sActionTwo) == 'undefined')
			{
				sActionTwo = 'void(0);';
			}
			
			// Prepare the functions: all references to the element
			// (this) should be replaced by the window dialog ID.
			sActionTwo = sActionTwo.replace (/this/, oWindow.sDialogId);
			var fActionTwo = function () { eval (sActionTwo); return true; }
		}
		else
		{
			var fActionTwo = sActionTwo;
		}
		
		if (Game.layout.mode() === 'mobile')
		{
			Game.gui.sheetMessage (sText, sLabelOne, fActionOne, sLabelTwo, fActionTwo);
			return;
		}
	
		Dialog.confirm 
		(
			'<p class="alert">'+sText+'</p>',
			{
				okLabel : 'Ok',
				width: '200px',
				height: '80px',
				className: Game.gui.sGuiTheme, 
				onOk : fActionOne,
				onCancel : fActionTwo,
				okLabel : sLabelOne,
				cancelLabel : sLabelTwo
			}
		);
	},
	
	/*
		Mobile mode replacement for Dialog.confirm: a modal sheet with the
		text and two buttons. Either button closes the modal after its action.
	*/
	'sheetMessage' : function (sText, sLabelOne, fActionOne, sLabelTwo, fActionTwo)
	{
		var oSheet = Game.sheets.open ({
			title :		'',
			closable :	false,
			modal :		true,
			className :	'sheet-dialog'
		});
		
		var message = new Element ('p', { 'class' : 'alert sheet-message' });
		message.update (sText);
		
		var actions = new Element ('div', { 'class' : 'sheet-actions' });
		
		var addButton = function (sLabel, fAction)
		{
			var button = new Element ('button', { 'type' : 'button' });
			button.update (sLabel);
			button.observe ('click', function (event)
			{
				Event.stop (event);
				Game.sheets.close (oSheet);
				fAction ();
			});
			actions.appendChild (button);
		};
		
		addButton (sLabelOne, fActionOne);
		addButton (sLabelTwo, fActionTwo);
		
		oSheet.content.appendChild (message);
		oSheet.content.appendChild (actions);

		// Layout back to desktop (relayoutWindows): the same question as a desktop dialog.
		oSheet.toDesktop = function ()
		{
			Game.sheets.close (oSheet);
			Game.gui.dialogMessage (null, sText, sLabelOne, fActionOne, sLabelTwo, fActionTwo);
		};
	},
	
	'confirmAction' : function (sText)
	{
		return confirm (sText);
	},
	
	/* Form handling */
	'submitForm' : function (oForm, additionalData)
	{
		if (typeof (additionalData) == 'undefined')
		{
			additionalData = new Object ();
		}
		
		oForm = Game.gui.getFormFromElement (oForm);
		oForm = $(oForm);
	
		var postdata = oForm.serialize (true);
		for (key in additionalData)
		{
			postdata[key] = additionalData[key]; 
		}
		
		var oWin = Game.gui.getWindowFromElement (oForm);
		oWin.sInputData = encodeURIComponent(Object.toJSON (postdata));
		Game.gui.doDialogRequest (null, oWin.sPool, true);
	},
	
	'getFormFromElement' : function (el)
	{
		if (el.nodeName.toLowerCase () == 'form')
		{
			return el;
		}
		else if (el.parentNode == null)
		{
			return null;
		}
		else
		{
			return Game.gui.getFormFromElement (el.parentNode);
		}
	},
	
	'windowClick' : function (oElement, sJsonInput)
	{
		if (typeof (sJsonInput) == 'object')
		{
			sJsonInput = Object.toJSON(sJsonInput);
		}
	
		if (typeof (oElement) != 'object')
		{
			var window = Game.gui.getWindowFromDialogId (oElement);
		}
		else
		{
			var window = Game.gui.getWindowFromElement (oElement);
		}
		
		window.sInputData = sJsonInput;
		Game.gui.doDialogRequest (null, window.sPool, true);
	},
	
	'getWindowFromElement' : function (oElement)
	{
		var el = $(oElement.parentNode);
	
		while (el)
		{
			if (el.hasClassName('guiwindow'))
			{
				return Game.gui.getWindowFromDialogId (el.dialogId);
			}
		
			else {
				el = $(el.parentNode);
			}
		}
	
		return false;
	},
	
	'selectLocation' : function (element, data, pointer)
	{
		// Fetch the correct window
		var window = Game.gui.getWindowFromElement (element);
		
		if (typeof (data) == 'undefined')
		{
			data = {}
		}
		
		Game.gui.hideAllWindows ();
		
		// Everything is okay
		Game.map.selectLocation 
		(
			// This function will be called with isometric coordinates
			function (ix, iy)
			{
				data.x = ix;
				data.y = iy;
			
				// Set the input data
				window.sInputData = Object.toJSON(datatosend);
				
				Game.gui.doDialogRequest (null, null, true);
			},
			Game.gui.showAllWindows,
			pointer
		);
	},
	
	'hideAllWindows' : function ()
	{
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			var win = Game.gui.aOpenWindows[i];
			if (win.oWindow != null)
			{
				win.oWindow.element.style.display = 'none';
			}
		}
		
		Game.sheets.hideAll ();
	},
	
	'showAllWindows' : function ()
	{
		for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
		{
			var win = Game.gui.aOpenWindows[i];
			if (win.oWindow != null)
			{
				win.oWindow.element.style.display = 'block';
			}
		}	
		
		Game.sheets.showAll ();
	},
	
	/*
		Poll actions
	*/
	
	// Check all windows to see what the lowest poll interval is, per pool.
	// This runs after every dialog response of every pool. It must not restart a pool's countdown that is already
	// running: the chat pool answers every second, and restarting the other countdowns on each answer meant the
	// "general" pool (newsbar, map updater) never polled, so the map never showed new buildings.
	'updatePollInterval' : function ()
	{
		for (var pool = 0; pool < Game.gui.aPools.length; pool ++)
		{
			var sPool = Game.gui.aPools[pool];
			var iInterval = 0;

			for (var i = 0; i < Game.gui.aOpenWindows.length; i ++)
			{
				var iWindowInterval = parseInt (Game.gui.aOpenWindows[i].iPollInterval);

				if
				(
					Game.gui.aOpenWindows[i].sPool == sPool &&
					iWindowInterval > 0 &&
					(iInterval == 0 || iWindowInterval < iInterval)
				)
				{
					iInterval = iWindowInterval;
				}
			}

			Game.gui.iPollInterval[sPool] = iInterval;

			if (iInterval > 0)
			{
				var iCountDown = Game.gui.iPollCountDown[sPool];

				// Start a countdown for a new pool, or shorten one when a faster window opened.
				if (typeof (iCountDown) != 'number' || iCountDown > iInterval)
				{
					Game.gui.iPollCountDown[sPool] = iInterval;
				}
			}
		}
	},
	
	'startPollActions' : function ()
	{
		/*
		Game.gui.objCountDown = new PeriodicalExecuter (Game.gui.doPollActions, 1);
		Game.gui.objFastCount = new PeriodicalExecuter (Game.gui.doFastPollActions, 0.2);
		*/
		
		if (CONFIG_ENV_CONTAINER == 'none' || !Prototype.Browser.IE)
		{
			Game.gui.objCountDown = new PeriodicalExecuter (Game.gui.doPollActions, 1);
			Game.gui.objFastCount = new PeriodicalExecuter (Game.gui.doFastPollActions, 0.33);
		}
		else
		{
			Game.gui.doPollActions ();
			Game.gui.doFastPollActions ();
		
			setTimeout (Game.gui.startPollActions, 1000);
		}
	},
	
	'_elementCache' : {},
	
	'resetElementsCache' : function ()
	{
		Game.gui._elementCache = {};
	},
	
	'getCachedElements' : function (ename, sCallback)
	{
		if (typeof (Game.gui._elementCache[ename]) == 'undefined')
		{
			Game.gui._elementCache[ename] = sCallback ();
		}
		
		return Game.gui._elementCache[ename];
	},
	
	// Periodical triggered function
	'doPollActions' : function ()
	{
		Game.gui.updateCounters ();
		Game.gui.updateClocks ();
		Game.gui.blinkTheBlinkers ();
		Game.gui.highlightTheHighlights ();
		
		// Check for polling
		for (var pool = 0; pool < Game.gui.aPools.length; pool ++)
		{
			if (Game.gui.iPollInterval[Game.gui.aPools[pool]] > 0)
			{
				if (Game.gui.iPollCountDown[Game.gui.aPools[pool]] == 0)
				{
					Game.gui.iPollCountDown[Game.gui.aPools[pool]] = Game.gui.iPollInterval[Game.gui.aPools[pool]];
				
					// Only do an automatic call if there are no other calls running
					if (typeof (Game.gui.aRunningPolls[Game.gui.aPools[pool]]) == 'undefined' ||
						Game.gui.aRunningPolls[Game.gui.aPools[pool]] <= 0)
					{
						Game.gui.doDialogRequest (null, Game.gui.aPools[pool]);
					}
				}
				else
				{
					Game.gui.iPollCountDown[Game.gui.aPools[pool]] --;
				}
			}
		}
	},
	
	'doFastPollActions' : function ()
	{
		Game.gui.updateIncreasing ();
	},
	
	'blinkTheBlinkers' : function ()
	{
		var elements = Game.gui.getCachedElements ('blinkers', function () { return $$('.blink'); });
		for (var i = 0; i < elements.length; i ++)
		{
			// Set opaticity to 0.
			elements[i].setOpacity (1);
			Effect.Pulsate(elements[i], { pulses: 1, duration: 0.5 });
		}
	},
	
	'highlightTheHighlights' : function ()
	{
		var elements = Game.gui.getCachedElements ('click-me', function () { return $$('.click-me'); });
		for (var i = 0; i < elements.length; i ++)
		{
			// Set opaticity to 0.
			elements[i].setOpacity (1);
			Effect.Pulsate(elements[i], { pulses: 1, duration: 0.5 });
		}
	},
	
	// Search for all countdowns and update them
	'updateCounters' : function ()
	{
		//var cs = $$('.counter');
		var cs = Game.gui.getCachedElements ('counters', function () { return $$('.counter'); });
		
		var hasReachedZero = false;
		
		var now = Math.floor ((new Date()).getTime () / 1000);
		
		for (var i = 0; i < cs.length; i ++)
		{
			if (typeof(cs[i].counter) == 'undefined')
			{
				var sp = cs[i].innerHTML.split (':');
				cs[i].counter = parseInt(sp[0], 10) * 3600 + parseInt(sp[1], 10) * 60 + parseInt(sp[2], 10);
				cs[i].iStartDate = Math.floor ((new Date()).getTime() / 1000);
			}
			
			else if (cs[i].counter != false)
			{
				var counter = cs[i].counter - (now - cs[i].iStartDate);
		
				if (counter >= 0)
				{						
					var h = Math.floor (counter / 3600);
					var m = Math.floor ((counter - h * 3600) / 60);
					var s = counter - h * 3600 - m * 60;
			
					if (h < 10) h = '0' + h;
					if (m < 10) m = '0' + m;
					if (s < 10) s = '0' + s;
			
					cs[i].innerHTML = h + ':' + m + ':' + s;
				}
				else
				{
					// Put the counter to false.
					cs[i].counter = false;
					hasReachedZero = true;
					
					// Remove the remove-after-countdown container (if found)
					var container = cs[i].up('.remove-after-countdown');
					if (container)
					{
						container.hide();
					}
				}
			}
		}
		
		if (hasReachedZero)
		{
			Game.gui.doDialogRequest ();
		}
	},
	
	/*
		Search & update all increasings
	*/
	'updateIncreasing' : function ()
	{
		// Limit amount of xpath calls by caching the elements for 10 second.
		var now = new Date ();
		
		/*
		if (Game.gui._uIncreasingCache == null)
		{
			Game.gui._uIncreasingCache = $$('.increasing');
			Game.gui._uIncreasingFreshness = now.getTime ();
		}
		*/
		var increasings = Game.gui.getCachedElements ('increasing', function () { return $$('.increasing'); });
		
		for (var i = 0; i < increasings.length; i ++)
		{
			var increasing = increasings[i];
			
			if 
			(
				typeof (increasing.initial) == 'undefined' || 
				typeof (increasing.startdate) == 'undefined' || 
				typeof (increasing.income) == 'undefined'
			)
			{
				var data = increasing.title.split ('/');
			
				increasing.initial = parseFloat(increasing.innerHTML);
				increasing.startdate = now.getTime ();
				increasing.income = parseFloat(data[0]);
				
				if (typeof (data[1]) != 'undefined')
				{
					increasing.maximum = parseInt (data[1]);
				}
				else
				{
					increasing.maximum = 0;
				}
				
				increasing.removeAttribute ('title');
			}
			
			var timepassed = (now.getTime() - increasing.startdate) / 1000;
			
			//alert (timepassed);
			var oldval = parseFloat(increasing.innerHTML);
			var newval = Math.floor (increasing.initial + ((timepassed / (60*60)) * increasing.income));
			
			if (newval < 0)
			{
				increasing.innerHTML = 0;
			}
			
			else if 
			(
				newval <= increasing.maximum || increasing.maximum == 0 || newval < oldval
			)
			{
				increasing.innerHTML = newval;
			}
		}
	},
	
	/*
		Search & update all clocks
	*/
	'updateClocks' : function ()
	{
		//var clocks = $$('.clock');
		var clocks = Game.gui.getCachedElements ('clocks', function () { return $$('.clock'); });
		
		for (var i = 0; i < clocks.length; i ++)
		{
			var clock = clocks[i];
			
			var now = new Date ();
			var format = clock.attributes.format.value;
		
			// Check for date
			if (typeof (clock.difference) == 'undefined')
			{
				// Fetch the whole day.
				//CONFIG_DATETIME_FORMAT
				
				// Loop trough the format & fetch all important data
				var data =
				{
					'Y' : now.getFullYear (),
					'm' : now.getMonth (),
					'd' : now.getDate (),
					'H' : now.getHours (),
					'i' : now.getMinutes (),
					's' : now.getSeconds ()
				}
				
				var sc = 0;
				var str = clock.innerHTML;
				
				for (var j = 0; j < format.length; j ++)
				{
					var fchar = format.charAt(j);
					switch (fchar)
					{
						case 'Y':
							data[fchar] = str.substr (sc, 4);
							sc += 4;
						break;
						
						case 'm':
						case 'd':
						case 'H':
						case 'i':
						case 's':
							data[fchar] = str.substr (sc, 2);
							sc += 2;
						break;
						
						default:
							sc ++;
						break;
					}
				}
				
				var remDate = new Date ();
				remDate.setFullYear (data['Y']);
				remDate.setMonth ((data['m'] - 1));
				remDate.setDate (data['d']);
				remDate.setHours (data['H']);
				remDate.setMinutes (data['i']);
				remDate.setSeconds (data['s']);
				
				clock.difference = now.getTime() - remDate.getTime();
			}
			
			// Replace inner html
			now.setTime(now.getTime() - clock.difference);
			
			// Replace the HTML
			var output = "";
			
			for (var j = 0; j < format.length; j ++)
			{
				var fchar = format.charAt(j);
				switch (fchar)
				{
					case 'Y':
						output = output + now.getFullYear();
					break;
					
					case 'm':
						output = output + Game.core.leadingZero(now.getMonth() + 1);
					break;
					
					case 'd':
						output = output + Game.core.leadingZero(now.getDate());
					break;
					
					case 'H':
						output = output + Game.core.leadingZero(now.getHours());
					break;
					
					case 'i':
						output = output + Game.core.leadingZero(now.getMinutes());
					break;
					
					case 's':
						output = output + Game.core.leadingZero(now.getSeconds());
					break;
					
					default:
						output = output + fchar;
					break;
				}
			}
			
			clock.innerHTML = output;
		}
	},
	
	/*
		Minimap
	*/
	'toggleMinimap' : function ()
	{
		// Fetch minimap
		var wins = Game.gui.getWindowsFromWindowId ('MiniMap');
		if (wins.length == 0)
		{
			Game.gui.openNewWindow ('MiniMap', {});
		}
		else
		{
			wins.each (function (el) { Game.gui.destroyWindow (el); });
		}
	},
	
	'removeDuplicates' : function (oFieldset, oCurrentElement)
	{
		if (typeof(oCurrentElement) == 'undefined')
		{
			oCurrentElement = false;
		}
	
		var oFieldset = $(oFieldset);
		var selectAreas = oFieldset.select ('select');
		
		for (var i = 0; i < selectAreas.length; i ++)
		{
			var value = selectAreas[i].getValue ();
			if (value != "0")
			{
				// Check all other select boxes to see if this is a duplicate
				for (var j = i + 1; j < selectAreas.length; j ++)
				{
					if (selectAreas[j].getValue () == value)
					{
						if (selectAreas[j] == oCurrentElement)
						{
							selectAreas[i].setValue (0);
							selectAreas[i].fire ('custom:change');
							//selectAreas[i].options[0].selected = true;
						}
						else
						{
							selectAreas[j].setValue (0);
							selectAreas[j].fire ('custom:change');
							//selectAreas[j].options[0].selected = true;
						}
					}
				}
			}
		}
		
		if (oCurrentElement)
		{
			oCurrentElement.fire ('custom:change');
		}
	},
	
	'showConnectionError' : function (sMessage)
	{
		if (!$('connection_error_warning'))
		{
			var msg = 'Lost connection to server...';
			if (typeof (sMessage) != 'undefined')
			{
				switch (sMessage)
				{
					case 'timeout':
						msg = 'Lost connection to server due to inactivity...';
					break;
				}
			}
		
			// Create error connection div
			var div = document.createElement ('div');
			div.id = 'connection_error_warning';
			div.innerHTML = '<p class="false">'+msg+'</p>';
			div.style.position = 'absolute';
			div.style.right = '25px';
			div.style.top = '50px';
			div.className = 'blink';
			div.style.zIndex = 100000000;
			$('bodyDiv').appendChild (div);
		}
	},
	
	'hideConnectionError' : function ()
	{
		if ($('connection_error_warning'))
		{
			var div = $('connection_error_warning');
			div.remove();
		}
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
		var args = $A(arguments);
		trigger = args.shift ();
	
		for (var i = 0; i < Game.core.events.length; i ++)
		{
			if (Game.core.events[i].trigger == trigger)
			{
				try
				{
					Game.core.events[i].callback (args);
				}
				catch (e)
				{
					console.log ('Error when calling trigger: ' + e);
				}
			}
		}
	}
}

Game.gui.initAnalytics ();
