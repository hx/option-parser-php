<?php

namespace Hx\OptionParser;

use Hx\OptionParser;
use Hx\OptionParser\Exceptions\InvalidOptionSpec;
use Hx\OptionParser\Exceptions\MultipleValuesNotAllowed;

/**
 * Class Option
 * @package Hx\OptionParser
 * @property-read string $name The long name of the option
 * @property-read string $initial The short (single-digit) name of the option
 * @property-read boolean $present Whether the option was included
 * @property mixed $value Value (or values) passed to the option, or false if negated, or null if omitted
 * @property-read string $description Description of the option (for printing help)
 * @property-read string $valueLabel Value label for the option (for printing help)
 *
 * @property-read bool $REQUIRED The REQUIRED flag's state
 * @property-read bool $FORBID_VALUE The FORBID_VALUE flag's state
 * @property-read bool $REQUIRE_VALUE The REQUIRE_VALUE flag's state
 * @property-read bool $MULTIPLE_VALUES The MULTIPLE_VALUES flag's state
 * @property-read bool $ALLOW_FALSE The ALLOW_FALSE flag's state
 */
class Option {

    /**
     * Set this flag to raise a validation exception if the option
     * is not included.
     */
    const REQUIRED = 0b1;

    /**
     * Set this flag to prevent this option from taking an inline
     * value. It will only be true, false or null if omitted.
     */
    const FORBID_VALUE = 0b10;

    /**
     * Set this flag to require an inline value. A validation exception
     * will be raised if a value is omitted.
     */
    const REQUIRE_VALUE = 0b100;

    /**
     * Set this flag to pass multiple values to the option. {@link $value}
     * will be an array.
     */
    const MULTIPLE_VALUES = 0b1000;

    /**
     * Set this flag to allow the negate prefix for this option.
     */
    const ALLOW_FALSE = 0b10000;

    private $initial;
    private $name;
    private $present = false;
    private $value;

    private $flags = 0;

    private $description;
    private $valueLabel;

    private $parseTrigger;

    public function __construct(array $args, callable $parseTrigger) {

        $this->parseTrigger = $parseTrigger;

        // Suss out args based on their types.
        foreach($args as $index => $arg) {

            // Integers will be flags (one of the constants defined above)
            if(is_int($arg)) {
                $this->flags |= $arg;
            }

            elseif(is_string($arg)) {

                $formats = [

                    // Initials - letters, digits and question mark
                    ['`^[a-z\d?]$`i', 'initial', 'initial'],

                    // Long names - a letter, then letters, numbers and hyphens
                    ['`^[a-z][a-z\d-]+$`i', 'name', 'option name'],

                    // Defaults - start with an equal sign
                    ['`^=(.+)$`', 'value', 'default'],

                    // Value label - inside square brackets
                    ['`^\[(.+)\]$`', 'valueLabel', 'value label'],

                    // Descriptions - anything else over one character
                    ['`.{2,}`', 'description', 'a description']
                ];

                foreach($formats as $format) {

                    list($pattern, $variable, $description) = $format;

                    if(preg_match($pattern, $arg, $matches)) {

                        // The portion of the string with which we're concerned
                        $match = array_pop($matches);

                        // Don't allow two of the same attribute
                        if($this->$variable !== null) {
                            throw new InvalidOptionSpec($args,
                                "Attempted to add $description '$match' after adding $description '{$this->$variable}'.");
                        }

                        // Assign the attribute
                        $this->$variable = $match;

                        // Break the loop
                        continue 2;
                    }
                }

                // If nothing hit 'continue' above, the format isn't recognised
                throw new InvalidOptionSpec($args,
                    "Invalid option spec '$arg'");
            }

            else {
                // We only take strings and integers.
                throw new InvalidOptionSpec($args,
                    "Invalid option type at position #$index.");
            }
        }

        // In case a default was supplied, set presence and cast if necessary
        if($this->value !== null) {
            if($this->FORBID_VALUE) {
                $this->value = (bool) $this->value;
            }
            elseif($this->MULTIPLE_VALUES) {
                $this->value = [$this->value];
            }
        }

        // Some validation. Must have a name or initial:
        if($this->initial === null && $this->name === null) {
            throw new InvalidOptionSpec($args,
                'Please specify an initial or a long name (or both)');
        }

        // Ensure multi-value options have pushable arrays
        if($this->MULTIPLE_VALUES && !is_array($this->value)) {
            $this->value = [];
        }

    }

    public function __get($name) {
        if(in_array($name, ['name', 'initial', 'present', 'value', 'description', 'valueLabel'])) {
            if($this->parseTrigger && ($name === 'present' || $name === 'value')) {
                call_user_func($this->parseTrigger);
                $this->parseTrigger = null;
            }
            return $this->$name;
        }
        if($name === strtoupper($name) && ($int = constant(__CLASS__ . "::$name"))) {
            return (bool) ($this->flags & $int);
        }
        throw new \Exception("Property $name is not defined on class " . __CLASS__);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws MultipleValuesNotAllowed
     */
    public function __set($name, $value) {
        if($name === 'value') {
            if(is_array($this->value)) {
                $this->value[] = $value;
            }
            elseif(!$this->present || (is_string($value) && $this->value === true)) {
                $this->value = $value;
            }
            else {
                throw new MultipleValuesNotAllowed;
            }
            $this->present = true;
        }
    }

    /**
     * Returns the value of the option, or if the value is boolean, returns
     * the name (or initial if no name exists) for true and a blank string for
     * false or not present.
     * @return null|string
     */
    public function __toString() {
        if(is_string($this->value)) {
            return $this->value;
        }
        elseif($this->value) {
            return $this->name ?: $this->initial;
        }
        else {
            return '';
        }
    }

}
