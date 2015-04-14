<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:15
 */

/**
 * Class Topic
 *
 * @property int id
 * @property string name
 * @property string create_time
 * @property string update_time
 */
final class Topic extends Dy\Orm\Model
{
    const TABLE_NAME = 'topics';

    protected static $_white_list = array('id', 'name', 'create_time');
}