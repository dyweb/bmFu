<?php
/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-12-29
 * Time: 下午5:11
 */

namespace Dy\Restful;

/**
 * Class Relation
 *
 * for relation ship table. eg: talk_group_user
 *
 * @package Dy\Restful
 *
 * @todo the cache for relation (or we just disable cache for relations )
 * @todo relations that are not so simple( only have two id and a primary key )
 */
class Relation extends RestBase
{
    // relation is for group table together.
    // 1. one to one
    // 2. one to many
    // 3. many to many

    // relation also got crud. and it should be much easier?...

}