<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 14-12-30
 * Time: 下午1:42
 */
class DummyTest extends PHPUnit_Framework_TestCase
{
    public function testFoo()
    {
        $this->assertEquals('foo', 'foo');
        $this->assertNotEquals('foo', 'bar');
    }
}