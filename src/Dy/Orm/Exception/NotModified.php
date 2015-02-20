<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-20
 * Time: 上午10:58
 */

namespace Dy\Orm\Exception;


final class NotModified extends \Exception
{
    public function __construct()
    {
        parent::__construct('Record is not modified, no need to create or save!');
    }
}