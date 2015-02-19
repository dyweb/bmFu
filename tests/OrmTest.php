<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:03
 */
class OrmTest extends PHPUnit_Framework_TestCase
{
    public function testTableName()
    {
        try {
            $user = new User();
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(), 'Model User must have table name');
        }
    }

    public function testBoot(){
        // all is well
        $topic = new Topic();
    }

    public function testNotFound(){
        try{
            $t = Topic::find(233);
        }catch (\Dy\Orm\Exception\NotFound $e){
            $this->assertEquals($e->get_primary_key_value(),233);
        }
    }

    public function testFind(){
        $t = Topic::find(1);
        $this->assertInstanceOf('Topic',$t);
        $this->assertEquals('hangout',$t->name);
    }
}