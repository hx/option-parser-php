<?php

use Hx\OptionParser;
use Hx\OptionParser\Option;

class OptionParserTest extends PHPUnit_Framework_TestCase {

    public function testSwitch() {
        $parser = new OptionParser(['-a']);
        $a = $parser->add('a');
        $b = $parser->add('b');
        $this->assertTrue($a->present);
        $this->assertTrue($a->value);
        $this->assertTrue($parser['a']);
        $this->assertCount(1, $parser);
        $this->assertCount(0, $parser->arguments);
        $this->assertFalse($b->present);
        $this->assertNull($b->value);
        $this->assertNull($parser['b']);
    }

    public function testNegate() {
        $parser = new OptionParser(['--no-foo']);
        $f = $parser->add('foo', Option::ALLOW_FALSE);
        $this->assertTrue($f->present);
        $this->assertFalse($f->value);
        $this->assertFalse($parser['foo']);
    }

    /**
     * @param $variation
     * @param $optionCount
     * @dataProvider dataValue
     */
    public function testValue($variation, $optionCount) {
        $parser = new OptionParser(explode(' ', $variation), OptionParser::ALLOW_UNDECLARED);
        $foo = $parser->add('f', 'foo');
        $parser->add('x');
        $this->assertTrue($foo->present);
        $this->assertSame('bar', $foo->value);
        $this->assertSame('bar', $parser['foo']);
        $this->assertSame('bar', $parser['f']);
        $this->assertCount($optionCount, $parser);
        $this->assertCount(0, $parser->arguments);
    }
    public function dataValue() {
        return [
            ['--foo=bar', 1],
            ['-f=bar', 1],
            ['-xf=bar', 2],
            ['--foo bar', 1],
            ['-f bar', 1]
        ];
    }

    public function testDefaultString() {
        $parser = new OptionParser([]);
        $foo = $parser->add('foo', '=bar');
        $this->assertEquals('bar', $foo->value);

        $parser = new OptionParser(['--foo', 'baz']);
        $foo = $parser->add('foo', '=bar');
        $this->assertEquals('baz', $foo->value);

    }

    /**
     * @param $argv
     * @param $default
     * @param $expect
     * @dataProvider dataDefaultBoolean
     */
    public function testDefaultBoolean($argv, $default, $expect) {
        $parser = new OptionParser($argv);
        $parser->add('f', 'foo', "=$default", Option::ALLOW_FALSE, Option::FORBID_VALUE);
        $this->assertSame($expect, $parser['f']);
    }
    public function dataDefaultBoolean() {
        return [
            [[], '0', false],
            [[], '1', true],
            [['--foo'], '0', true],
            [['--no-foo'], '1', false],
        ];
    }

}
