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

	public function testClampInt(): void
	{
		$this->assertSame(600, Neuron_Core_Tools::clampInt('600', 1, 800, 800));
		$this->assertSame(800, Neuron_Core_Tools::clampInt('100000', 1, 800, 800));
		$this->assertSame(1, Neuron_Core_Tools::clampInt('-5', 1, 800, 800));
		$this->assertSame(50, Neuron_Core_Tools::clampInt('7', 50, 100, 100));
		$this->assertSame(800, Neuron_Core_Tools::clampInt(null, 1, 800, 800));
		$this->assertSame(1, Neuron_Core_Tools::clampInt('abc', 1, 800, 800));
		$this->assertSame(800, Neuron_Core_Tools::clampInt(['x'], 1, 800, 800));
	}

	public function testSanitizeMapRegions(): void
	{
		$this->assertSame(
			[[1, -2], [3, 4]],
			Neuron_Core_Tools::sanitizeMapRegions([['1', '-2'], [3, "4' OR 1"], 'junk', ['x' => 1]])
		);
		$this->assertCount(100, Neuron_Core_Tools::sanitizeMapRegions(array_fill(0, 5000, [0, 0])));
		$this->assertSame([], Neuron_Core_Tools::sanitizeMapRegions('nope'));
	}
}
