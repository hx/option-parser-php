<?php

use Hx\OptionParser;
use Hx\OptionParser\Option;

class OptionParserTest extends PHPUnit_Framework_TestCase {

    public function testSwitch() {
        $parser = new OptionParser(['-a']);
        $a = $parser->add('a');
        $this->assertTrue($a->present);
        $this->assertTrue($a->value);
        $this->assertTrue($parser['a']);
        $this->assertCount(1, $parser);
        $this->assertCount(0, $parser->arguments);
        $this->assertcount(1, $parser->options);
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
        $parser = new OptionParser([$variation]);
        $foo = $parser->add('f', 'foo');
        $parser->add('x');
        $this->assertTrue($foo->present);
        $this->assertSame('bar', $foo->value);
        $this->assertTrue($parser['foo']);
        $this->assertTrue($parser['f']);
        $this->assertCount($optionCount, $parser->options);
        $this->assertCount(0, $parser->arguments);
    }
    public function dataValue() {
        return [
            ['--foo=bar', 1],
            ['-xf=bar', 2],
            ['--foo bar', 1],
            ['-f bar', 1]
        ];
    }

}
