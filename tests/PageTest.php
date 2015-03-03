<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-3-3
 * Time: 下午4:19
 */
class PageTest extends PHPUnit_Framework_TestCase
{
    // see if trait works in autoload
    public function testTrait()
    {
        $t = new Topic();
        $this->assertEquals(true, $t->can_page());
    }
}