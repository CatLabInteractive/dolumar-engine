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

test('pixelFor negates x like mapIsoJump and centres a 40px sprite', () => {
	const a = load();
	// getOrthogonalCors(-2, 1) = [(-2-1)*32, (-2+1)*16] = [-96, -16]; minus 20 each
	assert.deepStrictEqual(Array.from(a.pixelFor(2, 1)), [-116, -36]);
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
	return { createElement: el, createElementNS: (ns, tag) => el(tag), hidden: false };
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

test('routeLines gives one pixel polyline per object that reveals its path, none for neutral ones', () => {
	const a = load();
	a.objects = {
		'b:1': { data: { click: 'x', paths: obj.paths } },
		'a:1': { data: { kind: 'neutral', paths: [{ start: 0, end: 1, points: [[0, 0], [1, 0]] }] } },
	};
	const lines = a.routeLines();
	assert.strictEqual(lines.length, 1);
	// points: (0,0) (10,0) (10,0) (10,10) through getOrthogonalCors(-x, y)
	assert.deepStrictEqual(JSON.parse(JSON.stringify(lines[0])), [[0, 0], [-320, -160], [-320, -160], [-640, 0]]);
});

test('drawRoutes draws SVG polylines instead of sizing a canvas to the whole route', () => {
	const a = loadWithDom();
	a.routes = a.document.createElementNS('svg', 'svg');
	a.objects = { 'b:1': { data: { click: 'x', paths: obj.paths } } };
	a.drawRoutes();
	assert.strictEqual(a.routes.children.length, 1);
	assert.strictEqual(a.routes.children[0].tag, 'polyline');
	assert.strictEqual(a.routes.children[0].attrs.points, '0,0 -320,-160 -320,-160 -640,0');
	a.objects = {};
	a.drawRoutes();
	assert.strictEqual(a.routes.children.length, 0, 'redraw clears old lines');
});

function scrollMapWithTiles() {
	return { div: null, loadedMapTiles: { '0|0': [0, 0] } };
}

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
