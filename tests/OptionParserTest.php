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
     * @param string[] $args Input args
     * @param string[] $foo What foo should be
     * @dataProvider dataMultipleValues
     */
    public function testMultipleValues($args, $foo) {
        $parser = new OptionParser($args);
        $parser->add([
            ['foo', Option::REQUIRE_VALUE, Option::MULTIPLE_VALUES],
            ['bar', Option::REQUIRE_VALUE, Option::MULTIPLE_VALUES]
        ]);
        $this->assertEquals($foo, $parser['foo']);
    }
    public function dataMultipleValues() {
        return [
            [
                ['--foo', 'a', 'b', 'c'],
                ['a', 'b', 'c']
            ],
            [
                ['--foo', 'a', 'b', '--foo', 'c'],
                ['a', 'b', 'c']
            ],
            [
                ['--bar', 'x', '--foo', 'a', '--bar', 'y', '--foo', 'b', 'c'],
                ['a', 'b', 'c']
            ]
        ];
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

    public function testSummary() {
        $parser = new OptionParser([]);
        $parser->add('Here is how we want it to look.');
        $parser->add(' ');
        $parser->add('a', 'alpha', 'Something with alpha');
        $parser->add('b', 'beta', Option::ALLOW_FALSE, 'Something with beta that also allows false values, and has an explanation long enough to wrap');
        $parser->add('c', '[SOMETHING]', 'Something with a value label');
        $parser->add(' ');
        $parser->add("A separator line.");
        $parser->add(' ');
        $parser->add('d', 'delta', '[THIS|THAT]', 'Something with a value label that presents multiple possibilities, and therefore should be wrapped in square brackets.');
        $parser->add('echo', 'Something with no initial.');
        $parser->add('foxtrot', '[FOX]', 'Something with no initial, and a value label.');

        $expected = <<< EOF
Here is how we want it to look.

  -a, --alpha              Something with alpha
  -b, --[no]-beta          Something with beta that also
                           allows false values, and has an
                           explanation long enough to wrap
  -c  SOMETHING            Something with a value label

A separator line.

  -d, --delta [THIS|THAT]  Something with a value label that
                           presents multiple possibilities,
                           and therefore should be wrapped
                           in square brackets.
      --echo               Something with no initial.
      --foxtrot FOX        Something with no initial, and a
                           value label.

EOF;

        $this->assertSame($expected, $parser->summary(2, 2, 60));
    }

}
