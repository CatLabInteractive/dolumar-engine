// Run with: node --test tests/js
// Loads assets/javascript/armies.js with a stubbed Game global and tests the pure parts.
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
				oScrollMap: null,
				iTilesPerRegion: 10,
			},
			core: { sSessionId: null },
		},
		CONFIG_DISPATCH_URL: 'http://x/',
		console,
		setInterval: () => 0,
		clearInterval: () => {},
		document: null,
		Date,
		Math,
	};
	vm.createContext(ctx);
	vm.runInContext(fs.readFileSync(path.join(__dirname, '../../assets/javascript/armies.js'), 'utf8'), ctx);
	return ctx.Game.armies;
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
	a.createSprite = (o) => ({ id: o.id, removed: false, style: {}, className: 'army' });
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

test('eta formats the remaining seconds of the last path as h:mm', () => {
	const a = load();
	assert.strictEqual(a.eta(obj, 1000), '0:03');
	assert.strictEqual(a.eta(obj, 1199), '0:00');
	assert.strictEqual(a.eta({ paths: [{ start: 0, end: 7260, points: [[0,0],[1,1]] }] }, 0), '2:01');
});
