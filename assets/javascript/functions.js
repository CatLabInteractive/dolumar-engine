/*
	These functions provide a simple interface for the XHTML triggers.
*/
function openWindow (sId, sRequestData, inputData)
{
	if (typeof (sRequestData) == 'undefined')
	{
		sRequestData = new Object ();
	}
	
	Game.gui.openNewWindow (sId, sRequestData, inputData);
}

function submitForm (oForm, extraData)
{
	try
	{
		Game.gui.submitForm (oForm, extraData);
	}
	catch (e)
	{
		alert (e.message);
	}
	
	return false;
}

/*
	Old function, not to be used anymore.
*/
function windowAction (element, inputData, sendFormData)
{
	if (typeof (sendFormData) == 'undefined')
	{
		sendFormData = false;
	}

	if (typeof (inputData) == 'object')
	{
		// Correct value.
	}
	else
	{
		try
		{
			var sJson = "{";
		
			var params = inputData.split('&');
			var isEmpty = true;
			for (var i = 0; i < params.length; i ++)
			{
				var data = params[i].split('=');
				if (data.length == 2)
				{
					sJson += '"' + data[0] + '":"' + data[1] + '",';
					isEmpty = false;
				}
			}
		
			if (!isEmpty)
			{
				sJson = sJson.substr (0, sJson.length - 1);
			}
		
			sJson += "}";
			
			inputData = sJson;
		}
		catch (e)
		{
			alert (e);
		}
	
		//return false;
	}
	
	if (sendFormData)
	{
		Game.gui.submitForm (element, inputData);	
	}
	else
	{
		Game.gui.windowClick (element, inputData);
	}
}

function mapIsoJump (x, y)
{
	//Game.map.mapIsoJump (x, y);
	//document.location = CONFIG_GAME_URL + '#' + x + ',' + y;
	var i = document.location.href.indexOf('#');
	var hash = "#" + x + "," + y;
	
	if (i > 0)
	{
		document.location = document.location.href.substr(0,i) + hash;
	}
	else
	{
		document.location = document.location + hash;
	}
		
	Game.core.sCurHash = null;
}

function isoJumpMiniMap ()
{
	alert ('isoJumpMiniMap shouldn\'t be used anymore!');
}

// oPreview (optional): what each spot is worth, shown while choosing (see Game.map.placementPreview).
function selectBuildLocation (element, iBuilding, sRuneClassname, sImage, extraData, sErrorMessage, oPreview)
{
	// Fetch the correct window
	var window = Game.gui.getWindowFromElement (element);
	
	// Check if a selectbox is found (for runes)
	var option = window.div.select ('select.'+sRuneClassname);
	
	if (option && option.length == 1)
	{
		var rune = option[0].getValue();
		if (rune == 'random')
		{
			Game.gui.alertMessage (sErrorMessage);
			return false;
		}
	}
	else
	{
		var rune = 'random';
	}
	
	Game.gui.hideAllWindows ();
	
	// Everything is okay
	Game.map.selectLocation 
	(
		// This function will be called with isometric coordinates
		function (ix, iy)
		{
			// Set the input data
			window.sInputData = Object.toJSON
			({
				'x' : ix,
				'y' : iy,
				'building' : iBuilding,
				'rune' : rune,
				'extraData' : extraData
			});
			
			Game.gui.doDialogRequest (null, null, true);
		},
		Game.gui.showAllWindows,
		sImage,
		oPreview
	);
}

function selectLocation (element, data, sendFormData, imageData)
{
	if (typeof (sendFormData) == 'undefined')
	{
		sendFormData = false;
	}
	
	if (typeof (imageData) == 'undefined')
	{
		imageData = null;
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
			
			windowAction (element, data, sendFormData);
		},
		Game.gui.showAllWindows,
		imageData
	);
}

function selectMoveLocation ()
{
	Game.map.move ();
}

function toggleMinimap ()
{
	Game.gui.toggleMinimap ();
}

function initMinimap (oWindow)
{
	var loc = Game.core.getMapLocation ();
	Game.minimap.loadMap (loc[0], loc[1]);
}

function initBattleWindow (oWindow)
{
	Game.dolumar.initBattleWindow (oWindow);
}

function initBattleSimulator (oWindow)
{
	Game.dolumar.initBattleSimulator (oWindow);
}

function removeDuplicates (oFieldset, oCurrentElement)
{
	Game.gui.removeDuplicates (oFieldset, oCurrentElement);
}

function confirmAction (element, sInputData, sText)
{
	Game.gui.dialogMessage 
	(
		element,
		sText,
		'Ok',
		function ()
		{
			windowAction (element, sInputData);
			return true;
		},
		'Cancel'
	);
}

function closeThisWindow (element)
{
	var dialog = Game.gui.getWindowFromElement (element);
	Game.gui.destroyWindow (dialog);
}

function popupWindow (sUrl, iWidth, iHeight)
{
	Game.gui.openWindow (sUrl, iWidth, iHeight);
}

function scrollDownChat (window, classname)
{
	var elements = window.div.select('.' + classname);
	for (var i = 0; i < elements.length; i ++)
	{
		Game.gui.scrollDivDown (elements[i]);
	}
}

function removeDiv (element)
{
	element.parentNode.removeChild (element);
}