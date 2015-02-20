<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:07
 */
namespace Dy\Orm\Exception;

final class NotFound extends \Exception
{
    private $_primary_key_value = -1;

    public function __construct($primary_key_value)
    {
        parent::__construct("{$primary_key_value} is not found", 404);
        $this->_primary_key_value = $primary_key_value;
    }

    public function get_primary_key_value()
    {
        return $this->_primary_key_value;
    }
}