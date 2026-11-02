<?php

/**
 * Test double: a map object with a fixed uoid, relatedness, kind, name and click.
 */
class ExportableObject extends Neuron_GameServer_Map_MapObject
{
	private $uoid;
	private $related;
	private $kind;
	private $name;

	public function __construct($uoid, $related, $kind, $name = null, $click = null)
	{
		$this->uoid = $uoid;
		$this->related = $related;
		$this->kind = $kind;
		$this->name = $name;
		if ($click !== null) {
			$this->observe('click', $click);
		}
		$this->setLocation(new Neuron_GameServer_Map_Location(0, 0));
	}

	public static function withTwoMovements($uoid, $related, $kind, $name = null, $click = null): self
	{
		$o = new self($uoid, $related, $kind, $name, $click);
		$o->addMovement(new Neuron_GameServer_Map_Movement(1000, 1100,
			new Neuron_GameServer_Map_Location(0, 0), new Neuron_GameServer_Map_Location(10, 0)));
		$o->addMovement(new Neuron_GameServer_Map_Movement(1100, 1200,
			new Neuron_GameServer_Map_Location(10, 0), new Neuron_GameServer_Map_Location(10, 10)));
		return $o;
	}

	public function getUOID() { return $this->uoid; }
	public function isRelatedTo($viewer) { return $this->related; }
	public function getKind() { return $this->kind; }
	public function getName() { return $this->name; }
	public function getDisplayObject() { return new Neuron_GameServer_Map_Display_Sprite('army.png'); }
	public function getNeutralDisplayObject() { return new Neuron_GameServer_Map_Display_Sprite('neutral.png'); }
}
