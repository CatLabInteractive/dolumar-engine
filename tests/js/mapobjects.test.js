// Run with: node --test tests/js
// Loads assets/javascript/mapobjects.js with a stubbed Game global and tests the pure parts.
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function load() {
	const ctx = {
		Game: {
			map: {
				getOrthogonalCors: (gx, gy) => [Math.round((gx - gy) * 32), Math.round((gx + gy) * 16)],
				iMapOffsetX: 32,
				iMapOffsetY: 16,
				getURL: (base, query) => base + '?' + query,
				oScrollMap: null,
				iTilesPerRegion: 10,
			},
			core: { sSessionId: null },
		},
		CONFIG_DISPATCH_URL: 'http://x/',
		console,
		setInterval: () => 0,
		clearInterval: () => {},
		setTimeout: (fn) => { ctx.timeouts.push(fn); return ctx.timeouts.length; },
		clearTimeout: () => {},
		timeouts: [],
		document: null,
		Date,
		Math,
	};
	vm.createContext(ctx);
	vm.runInContext(fs.readFileSync(path.join(__dirname, '../../assets/javascript/mapobjects.js'), 'utf8'), ctx);
	ctx.Game.mapObjects.ctx = ctx;	// test access to the stubbed globals
	return ctx.Game.mapObjects;
}

const obj = {
	id: 'b:1', kind: 'attack', x: 10, y: 0,
	paths: [
		{ start: 1000, end: 1100, points: [[0, 0], [10, 0]] },
		{ start: 1100, end: 1200, points: [[10, 0], [10, 10]] },
	],
};

test('positionAt interpolates along the active path', () => {
	const a = load();
	assert.deepStrictEqual(Array.from(a.positionAt(obj, 1050)), [5, 0]);
	assert.deepStrictEqual(Array.from(a.positionAt(obj, 1150)), [10, 5]);
});

test('positionAt before the first path is the first point', () => {
	assert.deepStrictEqual(Array.from(load().positionAt(obj, 500)), [0, 0]);
});

test('positionAt after the last path is the last point', () => {
	assert.deepStrictEqual(Array.from(load().positionAt(obj, 9000)), [10, 10]);
});

test('positionAt with no paths is the reported position', () => {
	assert.deepStrictEqual(Array.from(load().positionAt({ x: 3, y: 4, paths: [] }, 1)), [3, 4]);
});

test('pixelFor centres a 40px sprite on the tile in map content coordinates', () => {
	const a = load();
	// mapIsoJump(2, 1) moves the map div to getOrthogonalCors(-2, 1) = [-96, -16] (plus half the screen), so
	// the tile's corner for (2, 1) sits at [96, 16] in the content and its centre half a tile further, at
	// [128, 32]; minus 20 each for the sprite
	assert.deepStrictEqual(Array.from(a.pixelFor(2, 1)), [108, 12]);
});

test('setObjects keeps known ids, adds new ones, drops missing ones and records the clock offset', () => {
	const a = load();
	a.now = () => 2000; // local clock
	a.createSprite = (o) => ({ id: o.id, removed: false, style: {}, className: 'mapobject' });
	a.removeSprite = (s) => { s.removed = true; };
	a.updateSprite = () => {};
	a.setObjects({ servertime: 1990, poll: 30, objects: [{ id: 'b:1', kind: 'attack', x: 0, y: 0, paths: [] }, { id: 'a:xyz', kind: 'neutral', x: 1, y: 1, paths: [] }] });
	const first = a.objects['b:1'].sprite;
	a.setObjects({ servertime: 2010, poll: 30, objects: [{ id: 'b:1', kind: 'attack', x: 5, y: 0, paths: [] }] });
	assert.strictEqual(a.objects['b:1'].sprite, first, 'sprite reused for a known id');
	assert.strictEqual(a.objects['a:xyz'], undefined, 'missing id removed');
	assert.strictEqual(a.offset, 10);
	assert.strictEqual(a.objects['b:1'].data.x, 5, 'data replaced');
});

test('eta is the remaining time of the current path as h:mm', () => {
	const a = load();
	assert.strictEqual(a.eta(obj, 1000), '0:01', 'first path ends at 1100');
	assert.strictEqual(a.eta(obj, 1110), '0:01', 'second path ends at 1200');
	assert.strictEqual(a.eta(obj, 500), '0:10', 'before the first path: its end');
	assert.strictEqual(a.eta(obj, 1199), '0:00');
	assert.strictEqual(a.eta(obj, 9000), '0:00', 'after the last path');
	assert.strictEqual(a.eta({ paths: [{ start: 0, end: 7260, points: [[0,0],[1,1]] }] }, 0), '2:01');
});

test('loadText takes the kind texts from CONFIG_MAPOBJECT_TEXT when the game defines it', () => {
	const a = load();
	assert.deepStrictEqual(Object.assign({}, a.kindText), {});
	a.loadText({ attack: 'Attacking army', open: 'Open' });
	assert.strictEqual(a.kindText.attack, 'Attacking army');
	assert.strictEqual(a.kindText.open, 'Open');
});

// A minimal DOM: elements record children, attributes and listeners.
function fakeDocument() {
	const el = (tag) => ({
		tag, children: [], listeners: {}, style: {}, className: '', attrs: {}, parentNode: null,
		appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
		removeChild(c) { this.children = this.children.filter((x) => x !== c); c.parentNode = null; },
		setAttribute(k, v) { this.attrs[k] = String(v); },
		getAttribute(k) { return this.attrs[k]; },
		addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); },
		fire(type, event) { (this.listeners[type] || []).forEach((fn) => fn(event)); if (type == 'click' && this.onclick) { this.onclick(event); } },
	});
	return { createElement: el, createElementNS: (ns, tag) => el(tag), createTextNode: (t) => ({ text: t }), hidden: false };
}

function loadWithDom() {
	const a = load();
	a.document = fakeDocument();
	return a;
}

test('init polls straight away instead of waiting for the first interval', () => {
	const a = loadWithDom();
	let polls = 0;
	a.poll_ = () => { polls ++; };
	// the layer attaches to the scroll div
	const scrollDiv = a.document.createElement('div');
	a.init({ div: scrollDiv });
	assert.strictEqual(polls, 1);
	assert.strictEqual(scrollDiv.children[0].className, 'mapobjects');
});

test('tick changes the facing without dropping other classes', () => {
	const a = load();
	a.pixelFor = (x, y) => [x, y];
	a.serverNow = () => 1050;
	const sprite = { className: 'mapobject facing-right clickable', style: {} };
	a.objects = { 'b:1': { data: { x: 0, y: 0, paths: [] }, sprite, lastPx: [10, 0] } };
	a.tick();
	assert.ok(sprite.className.indexOf('clickable') != -1, 'clickable kept');
	assert.ok(sprite.className.indexOf('facing-left') != -1, 'faces left when x decreases');
	assert.ok(sprite.className.indexOf('facing-right') == -1);
});

test('a tap (touchend) on a sprite opens its label and does not reach the map', () => {
	const a = loadWithDom();
	a.layer = a.document.createElement('div');
	let toggled = null;
	a.toggleLabel = (id) => { toggled = id; };
	const sprite = a.createSprite({ id: 'b:1', kind: 'attack', sprite: { url: 'x.png' }, name: 'A', click: 'openWindow();', paths: [] });
	let stopped = 0;
	const event = { stopPropagation: () => { stopped ++; }, preventDefault: () => {} };
	sprite.fire('touchstart', event);
	sprite.fire('touchend', event);
	assert.strictEqual(toggled, 'b:1', 'touchend counts as a tap');
	assert.ok(stopped >= 2, 'touch events do not start a map drag');
});

test('no route lines: the layer has no SVG and nothing draws routes', () => {
	const a = loadWithDom();
	a.poll_ = () => {};
	const scrollDiv = a.document.createElement('div');
	a.init({ div: scrollDiv });
	assert.strictEqual(scrollDiv.children[0].children.length, 0);
	assert.strictEqual(a.drawRoutes, undefined);
	assert.strictEqual(a.routeLines, undefined);
});

test('headingAt is the screen angle of the current path, null while not moving', () => {
	const a = load();
	// (0,0)->(10,0) runs to content pixel +320,+160: atan2(160, 320)
	assert.ok(Math.abs(a.headingAt(obj, 1050) - 26.565) < 0.01);
	// (10,0)->(10,10) runs to +320,-160
	assert.ok(Math.abs(a.headingAt(obj, 1150) + 26.565) < 0.01);
	assert.strictEqual(a.headingAt(obj, 500), null, 'not started');
	assert.strictEqual(a.headingAt(obj, 9000), null, 'arrived');
	assert.strictEqual(a.headingAt({ paths: [{ start: 0, end: 100, points: [[3, 3], [3, 3]] }] }, 50), null, 'waiting');
});

test('createSprite adds a heading arrow and tick points it the way the army goes', () => {
	const a = loadWithDom();
	a.layer = a.document.createElement('div');
	a.serverNow = () => 1050;
	const sprite = a.createSprite(Object.assign({ sprite: { url: 'x.png' } }, obj));
	const arrow = sprite.children.find((c) => c.className == 'mapobject-heading');
	assert.ok(arrow, 'arrow element');
	a.objects = { 'b:1': { data: obj, sprite, lastPx: null } };
	a.tick();
	assert.strictEqual(arrow.style.display, 'block');
	assert.strictEqual(arrow.style.transform, 'rotate(26.6deg)');
	a.serverNow = () => 9000;
	a.tick();
	assert.strictEqual(arrow.style.display, 'none', 'hidden once it stands still');
});

test('the label has a close button, so it can be closed without opening the window', () => {
	const a = loadWithDom();
	a.layer = a.document.createElement('div');
	a.ctx.Game.map.onObjectClick = () => { throw new Error('must not open'); };
	a.objects = { 'b:1': { data: Object.assign({ click: 'x()', name: 'A' }, obj), sprite: null } };
	const sprite = { style: { left: '0px', top: '0px' } };
	a.toggleLabel('b:1', sprite);
	const close = a.label.children.find((c) => c.className == 'mapobject-label-close');
	assert.ok(close, 'close button');
	const label = a.label;
	close.fire('click', { stopPropagation: () => {} });
	assert.strictEqual(a.label, null);
	assert.strictEqual(label.parentNode, null, 'removed from the layer');
});

test('closeLabel closes an open label and does nothing without one', () => {
	const a = loadWithDom();
	a.layer = a.document.createElement('div');
	a.closeLabel();
	a.objects = { 'b:1': { data: Object.assign({ click: 'x()', name: 'A' }, obj), sprite: null } };
	a.toggleLabel('b:1', { style: { left: '0px', top: '0px' } });
	a.closeLabel();
	assert.strictEqual(a.label, null);
	assert.strictEqual(a.layer.children.length, 0);
});

test('refresh asks the server straight away, without the debounce', () => {
	const a = load();
	let polls = 0;
	a.poll_ = () => { polls ++; };
	a.poll();
	a.refresh();
	assert.strictEqual(polls, 1);
	assert.strictEqual(a.pollDebounce, null, 'pending debounced poll cancelled');
});

function scrollMapWithTiles() {
	return { div: null, loadedMapTiles: { '0|0': [0, 0] } };
}

test('the poll interval from the server does not replace the poll function', () => {
	const a = load();
	a.setPollInterval(45);
	assert.strictEqual(a.pollInterval, 45);
	assert.strictEqual(typeof a.poll, 'function');
	a.setPollInterval(45);
	assert.strictEqual(typeof a.poll, 'function');
});

test('poll is debounced: several region commits in a row make one request', () => {
	const a = loadWithDom();
	let requests = 0;
	a.request = () => { requests ++; };
	a.scrollMap = scrollMapWithTiles;
	a.poll(); a.poll(); a.poll();
	assert.strictEqual(requests, 0, 'nothing sent yet');
	const pending = a.ctx.timeouts;
	assert.strictEqual(pending.length, 1, 'one timer scheduled');
	pending[0]();
	assert.strictEqual(requests, 1);
});

test('a response that arrives after a newer request is ignored', () => {
	const a = loadWithDom();
	a.scrollMap = scrollMapWithTiles;
	a.createSprite = () => ({ style: {}, className: '' });
	a.removeSprite = () => {};
	const handlers = [];
	a.request = (url, body, onSuccess) => { handlers.push(onSuccess); };
	a.poll_(); a.poll_();
	handlers[1]({ servertime: 2, poll: 30, objects: [{ id: 'new', kind: 'neutral', x: 0, y: 0, paths: [] }] });
	handlers[0]({ servertime: 1, poll: 30, objects: [{ id: 'old', kind: 'neutral', x: 0, y: 0, paths: [] }] });
	assert.deepStrictEqual(Object.keys(a.objects), ['new']);
});

test('no request while the page is hidden', () => {
	const a = loadWithDom();
	a.document.hidden = true;
	a.scrollMap = scrollMapWithTiles;
	let requests = 0;
	a.request = () => { requests ++; };
	a.poll_();
	assert.strictEqual(requests, 0);
});

test('an open label is closed when its object disappears', () => {
	const a = loadWithDom();
	a.layer = a.document.createElement('div');
	a.createSprite = () => ({ style: {}, className: '', parentNode: null });
	a.removeSprite = (s) => { s.removed = true; };
	a.updateSprite = () => {};
	a.setObjects({ servertime: 1, poll: 30, objects: [{ id: 'b:1', kind: 'attack', x: 0, y: 0, paths: [] }] });
	const label = a.document.createElement('div');
	label.setAttribute('data-id', 'b:1');
	a.label = label;
	a.setObjects({ servertime: 2, poll: 30, objects: [] });
	assert.strictEqual(a.label, null);
	assert.strictEqual(label.removed, true);
});

// Inverse of contentPixel for the test stub (32/16 tiles): content pixel of a tile centre -> tile
function tileAt(px, py) {
	const u = (px - 32) / 32, v = (py - 16) / 16;	// u = x + y, v = x - y
	return [(u + v) / 2, (u - v) / 2];
}

function focusable() {
	const a = load();
	const jumps = [];
	// Record the tile the map centres on: jumpTo receives the negated content pixel of the tile centre
	a.ctx.Game.map.oScrollMap = { jumpTo: (px, py) => jumps.push(tileAt(-px, -py)) };
	a.now = () => 1050;
	a.createSprite = (o) => ({ id: o.id, style: {}, className: 'mapobject' });
	a.removeSprite = () => {};
	a.updateSprite = () => {};
	return { a, jumps };
}

test('focus centres the map on the tile centre of the current position', () => {
	const { a, jumps } = focusable();
	a.setObjects({ servertime: 1050, objects: [obj] });
	a.focus('b:1', 99, 99);
	assert.deepStrictEqual(JSON.parse(JSON.stringify(jumps)), [[5, 0]]);
	assert.strictEqual(a.pendingFocus, null);
});

test('focus on an unknown object jumps to the fallback and follows the object once it loads', () => {
	const { a, jumps } = focusable();
	a.focus('b:1', 7, 8);
	assert.deepStrictEqual(JSON.parse(JSON.stringify(jumps)), [[7, 8]]);
	a.setObjects({ servertime: 1050, objects: [obj] });
	assert.deepStrictEqual(JSON.parse(JSON.stringify(jumps)), [[7, 8], [5, 0]]);
	a.setObjects({ servertime: 1050, objects: [obj] });
	assert.strictEqual(jumps.length, 2, 'the follow-up jump happens once');
});

test('a pending focus expires, so a later poll does not move the map', () => {
	const { a, jumps } = focusable();
	a.focus('b:1', 7, 8);
	a.now = () => 1050 + 120;
	a.setObjects({ servertime: 1050 + 120, objects: [obj] });
	assert.strictEqual(jumps.length, 1);
});
