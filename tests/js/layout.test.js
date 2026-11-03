// Run with: node --test tests/js/*.test.js
// Loads assets/javascript/layout.js against a stubbed window and document.
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

// A browser of the given width. honoursViewport: a phone or tablet browser, where the
// 0.5-scale viewport meta doubles the layout viewport; a desktop browser ignores it.
function browser({ width, preference, touchFirst, touchEvents, honoursViewport }) {
	const listeners = {};
	const meta = {
		content: 'width=device-width, initial-scale=1',
		getAttribute() { return this.content; },
		setAttribute(name, value) { this.content = value; },
	};
	const scale = () => (honoursViewport && meta.content.indexOf('initial-scale=0.5') >= 0 ? 0.5 : 1);
	const window = {
		get innerWidth() { return width / scale(); },
		addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
		matchMedia: (query) => ({ matches: query === '(hover: none) and (pointer: coarse)' && touchFirst }),
	};
	if (touchEvents) {
		window.ontouchstart = null;
	}
	const document = {
		body: { className: '' },
		documentElement: { get clientWidth() { return width / scale(); } },
		getElementById: (id) => (id === 'viewport' ? meta : null),
	};
	const ctx = { window, document, navigator: { maxTouchPoints: touchEvents ? 5 : 0 }, CONFIG_LAYOUT: preference };
	vm.createContext(ctx);
	vm.runInContext(fs.readFileSync(path.join(__dirname, '../../assets/javascript/layout.js'), 'utf8'), ctx);
	const layout = vm.runInContext('Game.layout', ctx);
	return {
		layout,
		meta,
		resize() { (listeners.resize || []).forEach((fn) => fn()); },
		setWidth(w) { width = w; },
	};
}

test('auto on a touchscreen laptop: desktop at 1000px, and it stays desktop on resize', () => {
	const b = browser({ width: 1000, preference: 'auto', touchFirst: false, touchEvents: true, honoursViewport: false });
	b.layout.init();
	assert.strictEqual(b.layout.current, 'desktop');
	for (let i = 0; i < 4; i++) {
		b.resize();
		assert.strictEqual(b.layout.current, 'desktop');
	}
	assert.strictEqual(b.meta.content, b.layout.DEFAULT_VIEWPORT);
});

test('auto on a touchscreen laptop: mobile under the breakpoint', () => {
	const b = browser({ width: 700, preference: 'auto', touchFirst: false, touchEvents: true, honoursViewport: false });
	b.layout.init();
	assert.strictEqual(b.layout.current, 'mobile');
});

test('auto on a tablet in landscape: desktop with the half-scale viewport, stable on resize', () => {
	const b = browser({ width: 1024, preference: 'auto', touchFirst: true, touchEvents: true, honoursViewport: true });
	b.layout.init();
	assert.strictEqual(b.layout.current, 'desktop');
	assert.strictEqual(b.meta.content, b.layout.DESKTOP_TOUCH_VIEWPORT);
	b.resize();
	b.resize();
	assert.strictEqual(b.layout.current, 'desktop');
});

test('auto on a phone: mobile', () => {
	const b = browser({ width: 390, preference: 'auto', touchFirst: true, touchEvents: true, honoursViewport: true });
	b.layout.init();
	assert.strictEqual(b.layout.current, 'mobile');
	assert.strictEqual(b.meta.content, b.layout.DEFAULT_VIEWPORT);
});

test('desktop preference on a phone keeps the half-scale viewport', () => {
	const b = browser({ width: 390, preference: 'desktop', touchFirst: true, touchEvents: true, honoursViewport: true });
	b.layout.init();
	assert.strictEqual(b.layout.current, 'desktop');
	assert.strictEqual(b.meta.content, b.layout.DESKTOP_TOUCH_VIEWPORT);
});

test('a layout change calls the onChange callbacks once per change', () => {
	const b = browser({ width: 1000, preference: 'auto', touchFirst: false, touchEvents: true, honoursViewport: false });
	b.layout.init();
	const modes = [];
	b.layout.onChange((mode) => modes.push(mode));
	b.setWidth(500);
	b.resize();
	b.resize();
	b.setWidth(1200);
	b.resize();
	assert.deepStrictEqual(modes, ['mobile', 'desktop']);
});
