<?php

namespace Hx\OptionParser\Exceptions;

class InvalidOptionSpec extends Base {

    private $args;

    public function __construct($args, $message) {
        parent::__construct($message);
        $this->args = $args;
    }

    public function __toString() {
        return parent::__toString() . "\n\nOriginal arguments: " . json_encode($this->args);
    }

}
