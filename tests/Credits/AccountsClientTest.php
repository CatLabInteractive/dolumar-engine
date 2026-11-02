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

	public function testBuyUrl (): void
	{
		if (!defined ('CATLAB_CREDITS_TYPE_ID')) {
			define ('CATLAB_CREDITS_TYPE_ID', 2);
		}
		$client = new Neuron_GameServer_Credits_AccountsClient ('https://accounts.catlab.eu/', 'cid', 'secret');
		$this->assertSame ('https://accounts.catlab.eu/credits/' . CATLAB_CREDITS_TYPE_ID . '/purchase', $client->getBuyUrl ());
	}
}
