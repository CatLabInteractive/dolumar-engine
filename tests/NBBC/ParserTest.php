<?php

use PHPUnit\Framework\TestCase;

/**
 * The NBBC classes used PHP 4 style constructors (a method named after the class). PHP 8 no longer calls those, so
 * the lexer never received its input and every parse threw a TypeError. Windows that render BBCode (the guide, chat,
 * messages) then failed, and a failing initial window used to take the rest of the initial windows down with it.
 */
class ParserTest extends TestCase
{
	public function testParsesBasicMarkup(): void
	{
		$html = Neuron_NBBC_Parser::parse("Hello [b]world[/b]\nNext line");

		$this->assertStringContainsString('<b>world</b>', $html);
		$this->assertStringContainsString('Hello', $html);
		$this->assertStringContainsString('Next line', $html);
	}

	public function testParsesPlainText(): void
	{
		$this->assertSame('<div class="text">Just text</div>', Neuron_NBBC_Parser::parse('Just text'));
	}

	public function testConstructorsInitialiseState(): void
	{
		$lexer = new BBCodeLexer('[i]x[/i]');
		$this->assertNotSame(BBCODE_EOI, $lexer->NextToken());

		$bbcode = new BBCode();
		$this->assertNotEmpty($bbcode->GetRule('b'));
	}
}
