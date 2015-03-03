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

    public function testSanitize()
    {
        $t = new Topic();
        $a = $t->page(-1, 100);
        $this->assertEquals(1, $a[0]);
        $this->assertEquals(100, $a[1]);
    }
}