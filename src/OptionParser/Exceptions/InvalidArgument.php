<?php

namespace Hx\OptionParser\Exceptions;

class InvalidArgument extends Base {

    public $args;
    public $index;
    public $arg;

    /**
     * @param string[] $args
     * @param int $index
     * @param string $message
     */
    public function __construct(array $args, $index, $message) {
        $this->args = $args;
        $this->index = $index;
        $this->arg = $args[$index];
        parent::__construct($message);
    }

}
