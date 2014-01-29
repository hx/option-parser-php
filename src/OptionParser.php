<?php

namespace Hx;

use Exception;
use Hx\OptionParser\Exceptions\MultipleValuesNotAllowed;
use Hx\OptionParser\Option;
use Hx\OptionParser\OptionCollection;
use Hx\OptionParser\Exceptions\InvalidArgument;
use Hx\OptionParser\Exceptions\InvalidOptionSpec;

/**
 * Class OptionParser
 * @package Hx
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
     * @var Option[]|string[]
     */
    private $parseResultsByOrder = [];
    private $cursor = 0;

    /**
     * @var Option[]
     */
    private $parseResultsByName = [];

    /**
     * @var Option[]
     */
    private $parseResultsByInitial = [];

    /**
     * @param array $argv Arguments to parse
     * @param int $flags,... One or more flags as defined by the constants on this class
     * @throws \Exception
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

        // Handle flags if there are more than one argument
        if(func_num_args() > 1) {

            // Loop through all but the first argument
            foreach(array_slice(func_get_args(), 1) as $arg) {

                // Make sure it's an integer
                if(!is_int($arg)) {
                    throw new Exception('Additional arguments must be integers.');
                }

                // Add it to the flag set
                $this->flags |= $arg;
            }
        }
    }

    /**
     * Get the state of the given flag
     * @param int $flag
     * @return bool
     */
    private function hasFlag($flag) {
        return (bool) ($this->flags & $flag);
    }

    /**
     * Single-run method to parse arguments originally passed to constructor
     * @throws InvalidArgument
     * @return $this
     * @todo refactor into smaller procedures once a sequence of events is established
     */
    private function parse() {
        /**
         * @type Option $recentOption
         */
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
                if(preg_match('`^(--[a-z][a-z\d-]+|-[a-z\d?])(=.+)?$`i', $arg, $matches)) {

                    // Ensure the previous option got a value if one was required
                    // TODO: repeat this after the last argument
                    if($recentOption && $recentOption->REQUIRE_VALUE && !is_string($recentOption->value)) {
                        throw new InvalidArgument($argv, $i - 1, "Option '{$argv[$i - 1]}' requires a value.");
                    }

                    // Relieve the recent option
                    $recentOption = null;

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
                            $option = $this->dictionaryByInitial[$initial];
                        }
                    }

                    // Is this an undeclared option?
                    if($option === null) {

                        // Throw if undeclared options aren't allowed
                        if(!$this->hasFlag(self::ALLOW_UNDECLARED)) {
                            throw new InvalidArgument($argv, $i, 'Unknown option');
                        }

                        // Create a new option
                        // TODO: multiples in undeclared? retrieve from $parseResultsByName perhaps?
                        $option = new Option([$name ?: $initial], $this->parseTrigger);
                    }

                    // Add the option to the parser results
                    $this->parseResultsByOrder[] = $option; // May result in duplicate references within this array.
                    if($option->name !== null) {
                        $this->parseResultsByName[$option->name] = $option;
                    }
                    if($option->initial !== null) {
                        $this->parseResultsByInitial[$option->initial] = $option;
                    }

                    // Is false allowed?
                    if($value === false && !$option->ALLOW_FALSE) {
                        throw new InvalidArgument($argv, $i, "The '$arg' option cannot be negated.");
                    }

                    // Make this the 'recent option' if values are allowed
                    if(!$option->FORBID_VALUE) {
                        $recentOption = $option;
                    }

                    // Process a =value
                    if(isset($matches[2])) {

                        // Make sure values are allowed
                        if($option->FORBID_VALUE) {
                            throw new InvalidArgument($argv, $i, "The '$arg' option cannot be assigned a value.");
                        }

                        // Don't allow negation of an assignment
                        if($value === false) {
                            throw new InvalidArgument($argv, $i, "Option negation with value assignment is not allowed.");
                        }

                        // Remove the leading = sign
                        $value = substr($matches[2], 1);
                    }
                    // Assign the value (removing the leading = sign)
                    try {
                        $option->value = $value;

                        // Don't accept any more values if a string was supplied,
                        // unless multiple values are allowed
                        if(is_string($value) && !$option->MULTIPLE_VALUES) {
                            $recentOption = null;
                        }
                    }
                    catch(MultipleValuesNotAllowed $e) {
                        throw new InvalidArgument($argv, $i, "The '$arg' option was specified more than once.");
                    }


                }

                // Not an option; could be a value?
                elseif($recentOption) {

                    // Assign the value.
                    // TODO: repetitious; refactor
                    try {
                        $recentOption->value = $arg;

                        // Remove it as the recent option
                        if(!$recentOption->MULTIPLE_VALUES) {
                            $recentOption = null;
                        }
                    }
                    catch(MultipleValuesNotAllowed $e) {
                        throw new InvalidArgument($argv, $i, "The '{$argv[$i - 1]}' option was specified more than once.");
                    }


                }

                // Must be an argument.
                else {
                    $this->parseResultsByOrder[] = $arg;
                }

            }

        }
        return $this;
    }

    public function offsetGet($offset) {
        $this->parse();
        if(is_string($offset)) {
            $collection = (strlen($offset) === 1)
                ? $this->parseResultsByInitial
                : $this->parseResultsByName;
            if(isset($collection[$offset])) {
                return $collection[$offset]->value;
            }
        }
        elseif(isset($this->parseResultsByOrder[$offset])) {
            return $this->parseResultsByOrder[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $value) {
        throw new Exception('Array access is read-only');
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

    public function current() {
        $this->parse();
        return current($this->parseResultsByOrder);
    }

    public function next() {
        $this->parse();
        next($this->parse()->parseResultsByInitial);
    }

    public function key() {
        $this->parse();
        return key($this->parseResultsByInitial);
    }

    public function valid() {
        $this->parse();
        return $this->key() <= $this->count();
    }

    public function rewind() {
        $this->parse();
        reset($this->parseResultsByInitial);
    }

    public function offsetExists($offset) {
        return $this->offsetGet($offset) !== null;
    }

    public function offsetUnset($offset) {
        $this->offsetSet($offset, null); // Will throw Exception
    }

    public function count() {
        $this->parse();
        return count($this->parseResultsByInitial);
    }

    public function __get($name) {
        if($name === 'arguments') {
            return array_filter($this->parseResultsByOrder, 'is_string');
        }
        throw new Exception("Unknown property $name on " . __CLASS__);
    }
}
