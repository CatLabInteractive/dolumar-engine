/*
	Sheets: how windows are shown in the mobile layout mode.

	Every window becomes a full-screen sheet (title bar with back and
	close buttons, scrolling content). Sheets form a stack: only the top
	non-modal sheet is visible. Modal sheets are centred over a backdrop
	and leave the sheet underneath visible.

	Markup: div#sheets > div.sheet[data-dialog-id] > div.sheet-bar
	(button.sheet-back, span.sheet-title, button.sheet-close) + div.sheet-content.
	Needs Prototype; loaded after gui.js.
*/
if (typeof Game == 'undefined')
{
	var Game = {};
}

Game.sheets = {

	stack : [],

	'container' : function ()
	{
		var container = $('sheets');

		if (!container)
		{
			container = new Element ('div', { 'id' : 'sheets' });
			$('bodyDiv').insert ({ 'top' : container });
		}

		return container;
	},

	/*
		options: {dialogId, title, closable, modal, className, onClose}
		Returns the sheet: {element, content, ...}. element is the div.sheet,
		content the div.sheet-content that receives the window HTML.
	*/
	'open' : function (options)
	{
		options = options || {};

		var container = Game.sheets.container ();
		var closable = options.closable !== false;
		var modal = options.modal === true;

		var element = new Element ('div', { 'class' : 'sheet' });
		if (typeof (options.dialogId) != 'undefined' && options.dialogId !== null)
		{
			element.setAttribute ('data-dialog-id', options.dialogId);
		}

		if (modal)
		{
			element.addClassName ('sheet-modal');
		}

		if (options.className)
		{
			element.addClassName (options.className);
		}

		var bar = new Element ('div', { 'class' : 'sheet-bar' });

		var back = new Element ('button', { 'type' : 'button', 'class' : 'sheet-back', 'aria-label' : 'Back' });
		back.update ('&#8249;');

		var title = new Element ('span', { 'class' : 'sheet-title' });
		title.update (options.title ? options.title : '');

		var close = new Element ('button', { 'type' : 'button', 'class' : 'sheet-close', 'aria-label' : 'Close' });
		close.update ('&#215;');

		if (!closable)
		{
			back.hide ();
			close.hide ();

			// Nothing to show in the bar at all (an untitled message modal).
			if (!options.title)
			{
				bar.hide ();
			}
		}

		bar.appendChild (back);
		bar.appendChild (title);
		bar.appendChild (close);

		var content = new Element ('div', { 'class' : 'sheet-content' });

		element.appendChild (bar);
		element.appendChild (content);

		var sheet = {
			'element' : element,
			'content' : content,
			'dialogId' : (typeof (options.dialogId) != 'undefined') ? options.dialogId : null,
			'modal' : modal,
			'closable' : closable,
			'backdrop' : null,
			'onClose' : (typeof (options.onClose) == 'function') ? options.onClose : null
		};

		var dismiss = function (event)
		{
			if (event)
			{
				Event.stop (event);
			}
			Game.sheets.dismiss (sheet);
		};

		back.observe ('click', dismiss);
		close.observe ('click', dismiss);

		if (modal)
		{
			sheet.backdrop = new Element ('div', { 'class' : 'sheet-backdrop' });
			container.appendChild (sheet.backdrop);
		}

		container.appendChild (element);

		Game.sheets.stack.push (sheet);
		Game.sheets.update ();

		return sheet;
	},

	/*
		Back or close pressed: the same path a desktop Window's onDestroy
		takes. Remove the sheet, then let the window manager forget it.
	*/
	'dismiss' : function (sheet)
	{
		Game.sheets.close (sheet);

		if (sheet.dialogId !== null && Game.gui.getWindowFromDialogId (sheet.dialogId))
		{
			Game.gui.destroyWindow (sheet.dialogId, true);
		}

		if (sheet.onClose)
		{
			sheet.onClose (sheet);
		}
	},

	/*
		Remove a sheet from the DOM and the stack and show the new top.
		Closing a sheet that is already gone does nothing.
	*/
	'close' : function (sheet)
	{
		var index = Game.sheets.stack.indexOf (sheet);

		if (index < 0)
		{
			return;
		}

		Game.sheets.stack.splice (index, 1);

		if (sheet.element.parentNode)
		{
			sheet.element.parentNode.removeChild (sheet.element);
		}

		if (sheet.backdrop && sheet.backdrop.parentNode)
		{
			sheet.backdrop.parentNode.removeChild (sheet.backdrop);
		}

		Game.sheets.update ();
	},

	/*
		Move a sheet to the top of the stack.
	*/
	'focus' : function (sheet)
	{
		var index = Game.sheets.stack.indexOf (sheet);

		if (index < 0)
		{
			return;
		}

		Game.sheets.stack.splice (index, 1);
		Game.sheets.stack.push (sheet);

		// Keep DOM order equal to stack order so later sheets paint on top.
		var container = Game.sheets.container ();
		if (sheet.backdrop)
		{
			container.appendChild (sheet.backdrop);
		}
		container.appendChild (sheet.element);

		Game.sheets.update ();
	},

	/*
		Only the top non-modal sheet is visible; modal sheets always are.
	*/
	'update' : function ()
	{
		var stack = Game.sheets.stack;
		var top = null;

		for (var i = stack.length - 1; i >= 0; i --)
		{
			if (!stack[i].modal)
			{
				top = stack[i];
				break;
			}
		}

		for (var j = 0; j < stack.length; j ++)
		{
			if (stack[j].modal || stack[j] === top)
			{
				stack[j].element.show ();
			}
			else
			{
				stack[j].element.hide ();
			}
		}
	},

	'hideAll' : function ()
	{
		var container = $('sheets');
		if (container)
		{
			container.hide ();
		}
	},

	'showAll' : function ()
	{
		var container = $('sheets');
		if (container)
		{
			container.show ();
		}
	}
};
