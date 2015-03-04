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

    public function testBoot()
    {
        // all is well
        $topic = new Topic();
    }

    public function testNotFound()
    {
        try {
            $t = Topic::find(233);
        } catch (\Dy\Orm\Exception\NotFound $e) {
            $this->assertEquals($e->get_primary_key_value(), 233);
        }
    }

    public function testFind()
    {
        $t = Topic::find(1);
        $this->assertInstanceOf('Topic', $t);
        $this->assertEquals('hangout', $t->name);
    }

    public function testSaveEmptyFail()
    {
        $t = new Topic();
        try {
            $t->save();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotModified', $e);
        }
    }

    public function testSaveWrongColumnFail()
    {
        $t = new Topic();
        $t->name = 'xiaoming';
        $t->not_exists_column = '233';
        try {
            $t->save();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotSaved', $e);
        }

        $t2 = Topic::find(1);
        $t2->jack = '233';
        try {
            $t2->save();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotSaved', $e);
        }
    }

    public function testSaveId()
    {
        $t = new Topic();
        $t->name = 'xiaoming';
        $t->save();
        $this->assertLessThan($t->id, 2);
    }

    public function testSaveNotModifiedFail()
    {
        $t = Topic::find(1);
        try {
            $t->save();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotModified', $e);
        }
    }

    public function testSaveUpdate()
    {
        $t = Topic::find(3);
        $t->name = 'callmemaybe' . time();
        $t->save();
        $this->assertEquals(3, $t->id);
    }

    public function testSelect()
    {
        Topic::select('create_time');
        Topic::select(array('id', 'name'));
        $t = Topic::find(3);
    }

    public function testWhiteList()
    {
        $t = Topic::find(3);
        try {
            $t->update_time;
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\UnknownColumn', $e);
        }
    }

    public function testOrder()
    {
        Topic::order('-id-name+create_time');
        Topic::order('id, +name, -create_time');
        Topic::get();
    }

    public function testWhere()
    {
        Topic::where(array(
            'select' => 'id,name',
            'order'  => '-id+name',
            'id'     => '<=3'
        ));
        $result = Topic::get()->result();
        $this->assertEquals(3, count($result));
        $this->assertEquals(1, $result[2]->id);
    }

    public function testPage()
    {
        Topic::where(array(
            'order' => '-id',
            'page'  => '3',
            'id'    => '<=3'
        ), array('per_page' => 1));
        $result = Topic::get()->result();
        $this->assertEquals(1, count($result));
        $this->assertEquals(1, $result[0]->id);
    }

}