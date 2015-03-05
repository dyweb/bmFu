<?php
/**
 * Created by PhpStorm.
 * User: HJM
 * Date: 2015/3/5
 * Time: 23:00
 */

namespace Dy\Orm\Exception;

final class NotDeleted extends \Exception
{
    private $_primary_key_values = [];

    public function __construct($primary_key_values)
    {
        $primary_key_values_str = implode(',', $primary_key_values);
        parent::__construct("{$primary_key_values_str} was(were) not deleted", 404);
        $this->_primary_key_values = $primary_key_values;
    }

    public function get_primary_key_values()
    {
        return $this->$_primary_key_values;
    }
}