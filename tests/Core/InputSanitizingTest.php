<?php

use PHPUnit\Framework\TestCase;

class InputSanitizingTest extends TestCase
{
	public function testValidLanguageTagsAreKept(): void
	{
		foreach (['en', 'nl', 'de', 'fr', 'it', 'pl'] as $tag) {
			$this->assertSame($tag, Neuron_Core_Tools::sanitizeLanguageTag($tag));
		}
	}

	public function testInvalidLanguageTagsAreRejected(): void
	{
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag('../../etc'));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag('<b>'));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag("en\n"));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag('eng'));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag(''));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag(null));
		$this->assertNull(Neuron_Core_Tools::sanitizeLanguageTag(['en']));
	}

	public function testJavascriptParamsAreEncoded(): void
	{
		$out = Neuron_Core_Tools::getJavascriptParamDeclarations([
			'module' => 'map/plainmap',
			'x' => '5',
		]);

		$this->assertSame(
			"var PARAM_MODULE = \"map/plainmap\";\nvar PARAM_X = \"5\";\n",
			$out
		);
	}

	public function testJavascriptParamsCannotBreakOut(): void
	{
		$out = Neuron_Core_Tools::getJavascriptParamDeclarations([
			'a' => "';alert(1)//",
			'b' => '"</script><script>alert(1)</script>',
			'bad key;alert(1);var c' => '1',
			'arr' => ['x'],
		]);

		$this->assertStringNotContainsString('</script>', $out);
		$this->assertStringNotContainsString("'", $out);
		$this->assertStringNotContainsString('alert(1);var', $out);
		$this->assertStringNotContainsString('PARAM_ARR', $out);
		$this->assertStringContainsString('var PARAM_A = "' . '\\' . 'u0027;alert(1)//";', $out);
	}
}
