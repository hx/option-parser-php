<?php

namespace Hx;

use Hx\OptionParser\Option;
use Hx\OptionParser\OptionCollection;
use Hx\OptionParser\Exceptions\InvalidOptionSpec;

/**
 * Class OptionParser
 * @package Hx
 * @property-read OptionCollection|Option[] $options
 * @property-read string[] $arguments
 */
class OptionParser implements \ArrayAccess, \Iterator, \Countable {

    /**
     * Set this flag to allow any options to be passed, including
     * those not explicitly added at runtime.
     */
    const ALLOW_UNDECLARED = 0b1;

    public static $falsePrefix = 'no';

    private $dictionaryByOrder = [];
    private $dictionaryByInitial = [];
    private $dictionaryByName = [];

    /**
     * @var callable
     */
    private $parseTrigger;

    /**
     * Whether the options passed at runtime have been parsed
     * @var bool
     */
    private $parsed = false;

    /**
     * @var string[]
     */
    private $argv;

    /**
     * @var int
     */
    private $flags = 0;

    /**
     * @param array $argv Arguments to parse
     * @param int $flags,... One or more flags as defined by the constants on this class
     */
    public function __construct(array $argv = null, $flags = null) {

        // If no args are given, try to use globals
        if($argv === null) {
            if(isset($GLOBALS['argv'])) {
                $argv = $GLOBALS['argv'];
            }
            else {
                $argv = [];
            }
        }
        $this->argv = $argv;

        // This callable is passed to options so they can trigger a parse
        // when their properties are accessed.
        $trigger = function() {
            $this->parse();
        };
        $this->parseTrigger = $trigger->bindTo($this);

        // TODO: handle flags
    }

    /**
     * Single-run method to parse arguments originally passed to constructor
     * @return $this
     */
    private function parse() {
        if(!$this->parsed) {

            $this->parsed = true;

            // Our list of args.
            $argv = $this->argv;

            // Let's normalize a few. We'll work backwards so we can splice and dice as needed.
            $i = count($argv);
            while($i--) {
                $arg = $argv[$i];

                //Expand -xyz=foo to -x -y -z=foo
                if(preg_match('`^-([a-z\d?]{2,})(=.*)?$`i', $arg, $matches)) {
                    $set = array_map(function($x) {
                        return "-$x";
                    }, str_split($matches[1]));
                    if(isset($matches[2])) {
                        $set[count($set) - 1] .= $matches[2];
                    }
                    array_splice($argv, $i, 1, $set);
                }
            }

            // The most recently parsed option, to which value(s) can be added
            $recentOption = null;

            // Traverse the arguments a little. The length
            // may change, so we'll avoid foreach for now
            for($i = 0; $i < count($argv); ++$i) {

                $arg = $argv[$i];

                // Pattern to match options and (optionally) =values
                if(preg_match('`^(--[a-z][a-z\d-]|-[a-z\d?])(=.+)?$`i', $arg, $matches)) {

                    // Start with a value of true, and no option
                    $value = true;
                    $option = null;

                    // Long names
                    if(substr($arg, 0, 2) === '--') {
                        $name = substr($matches[1], 2);
                        $initial = null;

                        // Look for a negate prefix
                        $falsePrefixLength = strlen(self::$falsePrefix) + 1;
                        if(substr($name, 0, $falsePrefixLength) === self::$falsePrefix . '-') {

                            $value = false;
                            $name = substr($name, $falsePrefixLength);
                        }

                        if(isset($this->dictionaryByName[$name])) {
                            $option = $this->dictionaryByName[$name];
                        }
                    }

                    // Initials/short names
                    else {
                        $initial = $arg[1];
                        $name = null;

                        if(isset($this->dictionaryByInitial[$initial])) {
                            $option = $initial;
                        }
                    }

                    // Is this an undeclared option


                    if(isset($matches[2])) {

                    }
                }


            }

        }
        return $this;
    }

    public function offsetGet($offset) {

    }

    public function offsetSet($offset, $value) {

    }

    /**
     * Add an option or information to the option set.
     * @param string $args,... Short options, long options, flags and info
     * @throws InvalidOptionSpec
     * @todo more explanation
     * @return OptionParser\Option
     */
    public function add($args) {

        // If the first arg is an array, treat this as a bulk add.
        if(is_array($args)) {
            $callee = [$this, __METHOD__];
            return array_map(function($args) use($callee) {
                return call_user_func_array($callee, $args);
            }, $args);
        }
        else {
            $args = func_get_args();
        }

        // Check if we're adding help text (a single string arg containing whitespace)
        if(count($args) === 1 && is_string($args[0]) && preg_match('`\s`', $args[0])) {
            return $this->dictionaryByOrder[] = $args[0];
        }

        // The rest of the argument sorting can be deferred to the
        // Option class.
        $option = new Option($args, $this->parseTrigger);

        // Some validation: initial or name already exist?
        if($option->initial !== null && isset($this->dictionaryByInitial[$option->initial])) {
            throw new InvalidOptionSpec($args,
                "Option initial '$option->initial' used more than once.'");
        }

        if($option->name !== null && isset($this->dictionaryByName[$option->name])) {
            throw new InvalidOptionSpec($args,
                "Option name '$option->name' used more than once.'");
        }

        // Add to dictionaries
        $this->dictionaryByName[$option->name]       = $option;
        $this->dictionaryByInitial[$option->initial] = $option;
        $this->dictionaryByOrder[]                   = $option;

        return $option;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current() {
        // TODO: Implement current() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next() {
        // TODO: Implement next() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key() {
        // TODO: Implement key() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid() {
        // TODO: Implement valid() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind() {
        // TODO: Implement rewind() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset) {
        // TODO: Implement offsetExists() method.
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset) {
        // TODO: Implement offsetUnset() method.
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count() {
        // TODO: Implement count() method.
    }
}
