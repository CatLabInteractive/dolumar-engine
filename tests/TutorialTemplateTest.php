<?php

use PHPUnit\Framework\TestCase;

class TutorialTemplateTest extends TestCase
{
	public function testTutorialPageMapsToTemplate(): void
	{
		$this->assertSame('tutorial/Tutorial1.phpt', Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', 'DEN/Tutorial1'));
		$this->assertSame('tutorial/Tutorial12.phpt', Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', 'DEN/Tutorial12'));
	}

	public function testTraversalIsRejected(): void
	{
		$this->assertNull(Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', 'DEN/../../../vendor/x'));
		$this->assertNull(Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', 'DEN/a/b'));
		$this->assertNull(Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', 'DEN/'));
		$this->assertNull(Neuron_GameServer_Windows_Tutorial::getTutorialTemplateName('DEN', ''));
	}
}
