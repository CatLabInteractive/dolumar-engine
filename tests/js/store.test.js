// Run with: node --test tests/js
// Loads assets/javascript/store.js with a stubbed Game global and fetch.
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function load({ userAgent, sessionId, response }) {
	const calls = [];
	const location = { href: 'https://main.dolumar.com/' };
	const alerts = [];
	const ctx = {
		Game: { core: { sSessionId: sessionId } },
		navigator: { userAgent },
		window: { location },
		alert: (text) => alerts.push(text),
		fetch: (url, options) => {
			calls.push({ url, options });
			return Promise.resolve({
				ok: response.status === 200,
				status: response.status,
				json: () => Promise.resolve(response.body),
			});
		},
	};
	ctx.window.fetch = ctx.fetch;
	vm.createContext(ctx);
	vm.runInContext(fs.readFileSync(path.join(__dirname, '../../assets/javascript/store.js'), 'utf8'), ctx);
	return { store: ctx.Game.store, calls, location, alerts };
}

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

test('the app is recognised by its user agent', () => {
	assert.strictEqual(load({ userAgent: 'Mozilla/5.0 DolumarApp/1.0 (android)', response: {} }).store.inApp(), true);
	assert.strictEqual(load({ userAgent: 'Mozilla/5.0 Chrome/130', response: {} }).store.inApp(), false);
});

test('open asks for a ticket with the session and goes to the store page', async () => {
	const url = 'https://localhost/store.html?api=x&ticket=y';
	const { store, calls, location, alerts } = load({ userAgent: 'DolumarApp/1.0 (android)', sessionId: 's3ss', response: { status: 200, body: { ticket: 'y', url } } });

	assert.strictEqual(store.open(), false);
	assert.strictEqual(store.open(), false, 'a second click while the first is pending does nothing');
	await tick(); await tick(); await tick();

	assert.strictEqual(calls.length, 1);
	assert.strictEqual(calls[0].url, 'api/store/ticket?session_id=s3ss');
	assert.strictEqual(calls[0].options.method, 'POST');
	assert.strictEqual(calls[0].options.credentials, 'same-origin');
	assert.strictEqual(location.href, url);
	assert.deepStrictEqual(alerts, []);
});

test('a refused ticket tells the player and lets them try again', async () => {
	const { store, location, alerts, calls } = load({ userAgent: 'DolumarApp/1.0', sessionId: null, response: { status: 401, body: { error: 'not_logged_in' } } });

	store.open();
	await tick(); await tick(); await tick();

	assert.strictEqual(calls[0].url, 'api/store/ticket');
	assert.strictEqual(location.href, 'https://main.dolumar.com/');
	assert.deepStrictEqual(alerts, ['Please log in again to buy credits.']);
	assert.strictEqual(store.opening, false);

	store.open();
	assert.strictEqual(calls.length, 2);
});
