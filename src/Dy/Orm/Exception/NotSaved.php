<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-20
 * Time: 上午11:14
 */

namespace Dy\Orm\Exception;


final class NotSaved extends \Exception
{
    public function __construct($msg = '')
    {
        parent::__construct("Record not saved! because {$msg}");
    }
}