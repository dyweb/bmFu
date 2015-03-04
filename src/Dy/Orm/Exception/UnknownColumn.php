<?php

namespace Dy\Orm\Exception;

final class UnknownColumn extends \Exception
{
    private $_name = '';

    public function __construct($name)
    {
        parent::__construct("Field {$name} is not found", 404);
        $this->_name = $name;
    }

    public function get_name()
    {
        return $this->_name;
    }
}