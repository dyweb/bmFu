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
 */
final class Topic extends Dy\Orm\Model
{
    use Dy\Orm\Page;
    const TABLE_NAME = 'topics';
}