/*
	Layout mode: 'desktop' or 'mobile'. Plain JS, no dependencies,
	so it can run before anything else is loaded.
*/
if (typeof Game == 'undefined')
{
	var Game = {};
}

Game.layout = {

	BREAKPOINT : 800,

	DESKTOP_TOUCH_VIEWPORT : 'user-scalable=no, initial-scale=0.5, maximum-scale=0.5, minimum-scale=0.5, width=device-width, height=device-height',
	DEFAULT_VIEWPORT : 'width=device-width, initial-scale=1',

	preference : 'desktop',

	current : null,
	callbacks : [],

	'isTouch' : function ()
	{
		return ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
	},

	/*
		0.5 while the desktop-on-touch viewport meta is applied, else 1.
	*/
	'currentScale' : function ()
	{
		var meta = document.getElementById ('viewport');
		var content = meta ? (meta.getAttribute ('content') || '') : '';

		return content.indexOf ('initial-scale=0.5') >= 0 ? 0.5 : 1;
	},

	'mode' : function ()
	{
		var pref = Game.layout.preference;

		if (pref == 'mobile')
		{
			return 'mobile';
		}

		if (pref == 'auto')
		{
			// Under the 0.5-scale viewport innerWidth is doubled; compare the width at scale 1.
			return window.innerWidth * Game.layout.currentScale () < Game.layout.BREAKPOINT ? 'mobile' : 'desktop';
		}

		return 'desktop';
	},

	'init' : function ()
	{
		if (typeof CONFIG_LAYOUT != 'undefined')
		{
			Game.layout.preference = CONFIG_LAYOUT;
		}

		Game.layout.apply ();

		window.addEventListener ('resize', Game.layout.apply, false);
		window.addEventListener ('orientationchange', Game.layout.apply, false);
	},

	'apply' : function ()
	{
		var layout = Game.layout;

		if (!document.body)
		{
			return;
		}

		var mode = layout.mode ();

		var cls = document.body.className.replace (/(^|\s)layout-(desktop|mobile)(?=\s|$)/g, ' ');
		document.body.className = (cls + ' layout-' + mode).replace (/^\s+|\s+$/g, '').replace (/\s{2,}/g, ' ');

		var meta = document.getElementById ('viewport');
		if (meta)
		{
			meta.setAttribute ('content', (mode == 'desktop' && layout.isTouch ()) ? layout.DESKTOP_TOUCH_VIEWPORT : layout.DEFAULT_VIEWPORT);
		}

		var previous = layout.current;
		layout.current = mode;

		if (previous !== null && previous !== mode)
		{
			for (var i = 0; i < layout.callbacks.length; i++)
			{
				layout.callbacks[i] (mode);
			}
		}
	},

	'setPreference' : function (value)
	{
		Game.layout.preference = value;
		Game.layout.apply ();
	},

	'onChange' : function (fn)
	{
		Game.layout.callbacks.push (fn);
	}
};
