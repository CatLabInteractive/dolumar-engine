<?php

use PHPUnit\Framework\TestCase;

class StoreTicketTest extends TestCase
{
	private const NOW = 1800000000;

	public function testATicketReadsBackThePlayerUntilItExpires (): void
	{
		$tickets = new Neuron_GameServer_Credits_StoreTicket ('secret');
		$ticket = $tickets->issue (4242, self::NOW);

		$this->assertMatchesRegularExpression ('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $ticket);
		$this->assertSame (4242, $tickets->read ($ticket, self::NOW));
		$this->assertSame (4242, $tickets->read ($ticket, self::NOW + Neuron_GameServer_Credits_StoreTicket::TTL));
		$this->assertNull ($tickets->read ($ticket, self::NOW + Neuron_GameServer_Credits_StoreTicket::TTL + 1));
	}

	public function testAnotherSecretOrATamperedTicketIsRefused (): void
	{
		$tickets = new Neuron_GameServer_Credits_StoreTicket ('secret');
		$ticket = $tickets->issue (4242, self::NOW);

		$this->assertNull ((new Neuron_GameServer_Credits_StoreTicket ('other'))->read ($ticket, self::NOW));

		list ($payload, $signature) = explode ('.', $ticket);
		$otherPlayer = rtrim (strtr (base64_encode ('4243.' . (self::NOW + 900)), '+/', '-_'), '=');
		$this->assertNull ($tickets->read ($otherPlayer . '.' . $signature, self::NOW));
		$this->assertNull ($tickets->read ($payload . '.' . strrev ($signature), self::NOW));
		$this->assertNull ($tickets->read ($payload, self::NOW));
		$this->assertNull ($tickets->read ('', self::NOW));
		$this->assertNull ($tickets->read ('a.b.c', self::NOW));
		$this->assertNull ($tickets->read ($ticket . '!', self::NOW));
	}

	public function testTheKeyIsNotTheRawSecret (): void
	{
		// Two tickets for the same player never share a signature across secrets,
		// and a signature over the payload with the raw secret is not accepted.
		$tickets = new Neuron_GameServer_Credits_StoreTicket ('secret');
		$payload = '7.' . (self::NOW + 900);
		$encoded = rtrim (strtr (base64_encode ($payload), '+/', '-_'), '=');
		$raw = rtrim (strtr (base64_encode (hash_hmac ('sha256', $payload, 'secret', true)), '+/', '-_'), '=');
		$this->assertNull ($tickets->read ($encoded . '.' . $raw, self::NOW));
	}

	public function testFromConfigNeedsTheClientSecret (): void
	{
		if (defined ('OPENID_CONNECT_SECRET'))
		{
			$this->assertInstanceOf (Neuron_GameServer_Credits_StoreTicket::class, Neuron_GameServer_Credits_StoreTicket::fromConfig ());
		}
		else
		{
			$this->assertNull (Neuron_GameServer_Credits_StoreTicket::fromConfig ());
		}
	}
}
