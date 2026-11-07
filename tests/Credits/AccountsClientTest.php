<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class AccountsClientTest extends TestCase
{
	private array $history = [];

	private function client (array $responses): Neuron_GameServer_Credits_AccountsClient
	{
		$this->history = [];
		$stack = HandlerStack::create (new MockHandler ($responses));
		$stack->push (Middleware::history ($this->history));

		return new Neuron_GameServer_Credits_AccountsClient (
			'https://accounts.example/', 'cid', 'secret', new Client (['handler' => $stack, 'http_errors' => false])
		);
	}

	public function testCreateRequestSendsBearerAndBody (): void
	{
		$client = $this->client ([new Response (200, [], json_encode (['id' => 7, 'confirmUrl' => 'https://a/credits/request/7/x']))]);
		$result = $client->createRequest ('tok', 42, 100, 'Premium', 'https://g/notify', 'https://g/return', ['purchase' => 3]);

		$this->assertSame (['id' => 7, 'confirmUrl' => 'https://a/credits/request/7/x'], $result);
		$request = $this->history[0]['request'];
		$this->assertSame ('POST', $request->getMethod ());
		$this->assertSame ('/api/1.0/profiles/42/credits/request', $request->getUri ()->getPath ());
		$this->assertSame ('Bearer tok', $request->getHeaderLine ('Authorization'));
		$body = json_decode ((string)$request->getBody (), true);
		$this->assertSame (100, $body['credits']);
		$this->assertSame ('Premium', $body['reason']);
		$this->assertSame ('https://g/notify', $body['notify_url']);
		$this->assertSame ('https://g/return', $body['return']);
		$this->assertSame (['purchase' => 3], $body['extra']);
	}

	public function testGetRequestUsesBasicAuth (): void
	{
		$client = $this->client ([new Response (200, [], json_encode (['status' => 'ACCEPTED'])), new Response (404, [], '{}')]);
		$this->assertSame (['status' => 'ACCEPTED'], $client->getRequest (9));
		$this->assertSame ('Basic ' . base64_encode ('cid:secret'), $this->history[0]['request']->getHeaderLine ('Authorization'));
		$this->assertSame ('/api/1.0/credits/requests/9', $this->history[0]['request']->getUri ()->getPath ());
		$this->assertNull ($client->getRequest (10));
	}

	public function testPersonalProfileIsPicked (): void
	{
		$client = $this->client ([new Response (200, [], json_encode (['items' => [
			['id' => 5, 'personal' => false], ['id' => 6, 'personal' => true]
		]]))]);
		$this->assertSame (6, $client->getPersonalProfileId ('tok'));
	}

	public function testBalanceAndLoginToken (): void
	{
		$client = $this->client ([
			new Response (200, [], json_encode (['price' => ['available' => 250]])),
			new Response (200, [], json_encode (['token' => 'abc', 'expires' => 1]))
		]);
		$this->assertSame (250, $client->getBalance ('tok'));
		$this->assertSame ('abc', $client->getLoginToken ('tok'));
		$this->assertSame ('POST', $this->history[1]['request']->getMethod ());
		$this->assertSame ('/api/1.0/users/me/login-token', $this->history[1]['request']->getUri ()->getPath ());
	}

	public function testRefund (): void
	{
		$client = $this->client ([new Response (200, [], '{"success":true}'), new Response (400, [], '{}')]);
		$this->assertTrue ($client->refund (42, 100, 'why', 7));
		$body = json_decode ((string)$this->history[0]['request']->getBody (), true);
		$this->assertSame (7, $body['credit_request_id']);
		$this->assertSame ('Basic ' . base64_encode ('cid:secret'), $this->history[0]['request']->getHeaderLine ('Authorization'));
		$this->assertFalse ($client->refund (42, 100, 'why', null));
	}

	public function testRefundRequestOutcomes (): void
	{
		$exceeds = json_encode (['error' => ['message' => 'Refund exceeds the purchase: 100 charged, 100 already refunded.']]);
		$client = $this->client ([
			new Response (200, [], '{"success":true}'),
			new Response (400, [], $exceeds),
			new Response (400, [], json_encode (['error' => ['message' => 'credit_request_id is not an accepted purchase on this profile.']])),
			new Response (502, [], ''),
		]);

		$this->assertSame ('refunded', $client->refundRequest (42, 100, 'why', 7));
		$body = json_decode ((string)$this->history[0]['request']->getBody (), true);
		$this->assertSame (['credits' => 100, 'reason' => 'why', 'credit_request_id' => 7], $body);
		$this->assertSame ('/api/1.0/profiles/42/credits/refund', $this->history[0]['request']->getUri ()->getPath ());

		$this->assertSame ('already_refunded', $client->refundRequest (42, 100, 'why', 7));

		try {
			$client->refundRequest (42, 100, 'why', 7);
			$this->fail ('Expected AccountsRejected');
		} catch (Neuron_GameServer_Credits_AccountsRejected $e) {
			$this->assertSame (400, $e->getStatus ());
			$this->assertStringContainsString ('not an accepted purchase', $e->getMessage ());
		}

		$this->expectException (Neuron_GameServer_Credits_AccountsUnavailable::class);
		$client->refundRequest (42, 100, 'why', 7);
	}

	public function testServerErrorThrowsUnavailable (): void
	{
		$client = $this->client ([new Response (503, [], '')]);
		$this->expectException (Neuron_GameServer_Credits_AccountsUnavailable::class);
		$client->getBalance ('tok');
	}

	public function testNetworkErrorThrowsUnavailable (): void
	{
		$client = $this->client ([new GuzzleHttp\Exception\ConnectException ('down', new GuzzleHttp\Psr7\Request ('GET', 'x'))]);
		$this->expectException (Neuron_GameServer_Credits_AccountsUnavailable::class);
		$client->getBalance ('tok');
	}

	public function testCreateRequestClientErrorIsRejected (): void
	{
		$client = $this->client ([new Response (422, [], json_encode (['error' => 'Bad notify url']))]);
		try {
			$client->createRequest ('tok', 42, 100, 'r', 'n', 'u', []);
			$this->fail ('Expected AccountsRejected');
		} catch (Neuron_GameServer_Credits_AccountsRejected $e) {
			$this->assertSame (422, $e->getStatus ());
			$this->assertNotInstanceOf (Neuron_GameServer_Credits_AccountsUnavailable::class, $e);
			$this->assertStringNotContainsString ('tok', str_replace ('Bad notify url', '', $e->getMessage ()));
		}
	}

	public function testGetRequestUnauthorizedIsRejected (): void
	{
		$client = $this->client ([new Response (401, [], '{}')]);
		$this->expectException (Neuron_GameServer_Credits_AccountsRejected::class);
		$client->getRequest (9);
	}

	public function testBuyUrl (): void
	{
		if (!defined ('CATLAB_CREDITS_TYPE_ID')) {
			define ('CATLAB_CREDITS_TYPE_ID', 2);
		}
		$client = new Neuron_GameServer_Credits_AccountsClient ('https://accounts.catlab.eu/', 'cid', 'secret');
		$this->assertSame ('https://accounts.catlab.eu/credits/' . CATLAB_CREDITS_TYPE_ID . '/purchase', $client->getBuyUrl ());
	}

	public function testStorePacksUseClientCredentials (): void
	{
		$client = $this->client ([new Response (200, [], json_encode (['packs' => [['sku' => 'credits.100', 'credits' => '100'], ['bad' => 1], ['sku' => 'credits.500', 'credits' => 500]]]))]);
		$this->assertSame ([['sku' => 'credits.100', 'credits' => 100], ['sku' => 'credits.500', 'credits' => 500]], $client->getStorePacks ());
		$request = $this->history[0]['request'];
		$this->assertSame ('GET', $request->getMethod ());
		$this->assertSame ('/api/1.0/credits/store/packs', $request->getUri ()->getPath ());
		$this->assertSame ('Basic ' . base64_encode ('cid:secret'), $request->getHeaderLine ('Authorization'));
	}

	public function testStorePacksFailuresThrow (): void
	{
		$client = $this->client ([new Response (503, [], '{"error":"unavailable"}')]);
		$this->expectException (Neuron_GameServer_Credits_AccountsUnavailable::class);
		$client->getStorePacks ();
	}

	public function testRegisterStorePurchasePostsTheProofAndReturnsTheAnswerAsIs (): void
	{
		$client = $this->client ([
			new Response (200, [], json_encode (['status' => 'registered', 'credits' => 500, 'transaction_id' => 9, 'balance' => 500])),
			new Response (403, [], json_encode (['error' => 'rejected', 'reason' => 'invalid_purchase'])),
			new Response (503, [], 'not json'),
		]);
		$purchase = ['source' => 'google', 'sku' => 'credits.500', 'store_reference' => 'tok', 'proof' => ['purchaseToken' => 'tok', 'productId' => 'credits.500']];

		$result = $client->registerStorePurchase (42, $purchase);
		$this->assertSame (200, $result['status']);
		$this->assertSame ('registered', $result['body']['status']);
		$request = $this->history[0]['request'];
		$this->assertSame ('POST', $request->getMethod ());
		$this->assertSame ('/api/1.0/profiles/42/credits/store', $request->getUri ()->getPath ());
		$this->assertSame ('Basic ' . base64_encode ('cid:secret'), $request->getHeaderLine ('Authorization'));
		$this->assertSame ($purchase, json_decode ((string)$request->getBody (), true));

		$this->assertSame (['status' => 403, 'body' => ['error' => 'rejected', 'reason' => 'invalid_purchase']], $client->registerStorePurchase (42, $purchase));
		$this->assertSame (['status' => 503, 'body' => []], $client->registerStorePurchase (42, $purchase));
	}

	public function testRegisterStorePurchaseNetworkFailureIsUnavailable (): void
	{
		$client = $this->client ([new \GuzzleHttp\Exception\ConnectException ('timed out', new \GuzzleHttp\Psr7\Request ('POST', 'https://accounts.example/'))]);
		$this->expectException (Neuron_GameServer_Credits_AccountsUnavailable::class);
		$client->registerStorePurchase (42, ['source' => 'google']);
	}
}
