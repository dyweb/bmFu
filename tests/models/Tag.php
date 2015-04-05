<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:15
 */

/**
 * Class Tag
 *
 * @property int id
 * @property int status
 * @property string name
 */
final class Tag extends Dy\Orm\Model
{
    use Dy\Orm\Page;
    use Dy\Orm\SoftDelete;
    const TABLE_NAME = 'tags';

    public static $timestamps = false;
    protected static $_white_list = array('id', 'name');
}