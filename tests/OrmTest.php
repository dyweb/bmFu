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
            $t = Topic::find(2333);
        } catch (\Dy\Orm\Exception\NotFound $e) {
            $this->assertEquals($e->get_primary_key_value(), 2333);
        }
    }

    public function testFind()
    {
        $t = Topic::find(1);
        $this->assertInstanceOf('Topic', $t);
        $this->assertEquals('hangout', $t->name);
    }

    public function testCount()
    {
        $count = Topic::countAll();
        $this->assertTrue($count >= 2);
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
        $this->assertTrue($t->id > 0);
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

    public function testDelete()
    {
        $t = new Topic();
        $t->name = 'hello_delete';
        $t->save();
        $this->assertTrue($t->delete());
        $this->assertNotTrue($t->exists());
    }

    public function testDeleteEmpty()
    {
        $t = new Topic();
        $this->assertNotTrue($t->delete());
        $this->assertNotTrue($t->exists());
    }

    public function testDeleteOrFail()
    {
        $t = new Topic();
        $t->name = 'hello_delete_or_fail';
        $t->save();
        // OK if no exception thrown
        $this->assertTrue($t->deleteOrFail());
        $this->assertNotTrue($t->exists());
    }

    public function testDeleteOrFailEmpty()
    {
        $t = new Topic();
        try {
            $t->delete();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotExists', $e);
        }
    }

    public function testDestroySingle()
    {
        $t = new Topic();
        $t->name = 'hello_destroy';
        $t->save();
        $this->assertEquals(1, Topic::destroy($t->id));
    }

    public function testDestroyNoArg()
    {
        $this->assertEquals(false, Topic::destroy(array()));
    }

    public function testDestroyEmpty()
    {
        $this->assertEquals(false, Topic::destroy(0));
    }

    public function testDestroyWhere()
    {
        $t1 = new Topic();
        $t1->name = 'hello_destroy_where_1';
        $t1->save();
        $start_id = $t1->id;
        $t2 = new Topic();
        $t2->name = 'hello_destroy_where_2';
        $t2->save();
        $t3 = new Topic();
        $t3->name = 'hello_destroy_where_3';
        $t3->save();
        $this->assertEquals(1, Topic::destroyWhere(array('id' => ">= $start_id")));
        $this->assertEquals(2, Topic::destroyWhere(array('id' => ">= $start_id"), 3));
    }

    public function testDestroyWhereIllegalArg()
    {
        try {
            Topic::destroyWhere(array());
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e);
        }

        try {
            Topic::destroyWhere(array('id' => '> 0'), 0);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e);
        }
    }

    public function testDestroyMulti()
    {
        $t1 = new Topic();
        $t1->name = 'hello_destroy_multi1';
        $t1->save();
        $t2 = new Topic();
        $t2->name = 'hello_destroy_multi2';
        $t2->save();
        $t3 = new Topic();
        $t3->name = 'hello_destroy_multi3';
        $t3->save();
        $this->assertEquals(3, Topic::destroy(array($t1->id, $t2->id, $t3->id)));
    }

    public function testDestroyMultiArg()
    {
        $t1 = new Topic();
        $t1->name = 'hello_destroy_multi_arg1';
        $t1->save();
        $t2 = new Topic();
        $t2->name = 'hello_destroy_multi_arg2';
        $t2->save();
        $t3 = new Topic();
        $t3->name = 'hello_destroy_multi_arg3';
        $t3->save();
        $this->assertEquals(3, Topic::destroy($t1->id, $t2->id, $t3->id));
    }

    public function testDestroyOrFailSingle()
    {
        $t = new Topic();
        $t->name = 'hello_destroy_or_fail';
        $t->save();
        $this->assertEquals(1, Topic::destroyOrFail($t->id));
    }

    public function testDestroyOrFailNoArg()
    {
        try {
            Topic::destroyOrFail(array());
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e);
        }
    }

    public function testDestroyOrFailEmpty()
    {
        try {
            Topic::destroyOrFail(0);
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Dy\Orm\Exception\NotDeleted', $e);
        }
    }

    public function testDestroyOrFailMulti()
    {
        $t1 = new Topic();
        $t1->name = 'hello_destroy_or_fail_multi1';
        $t1->save();
        $t2 = new Topic();
        $t2->name = 'hello_destroy_or_fail_multi2';
        $t2->save();
        $t3 = new Topic();
        $t3->name = 'hello_destroy_or_fail_multi3';
        $t3->save();
        $this->assertEquals(3, Topic::destroyOrFail(array($t1->id, $t2->id, $t3->id)));
    }

    public function testDestroyOrFailMultiArg()
    {
        $t1 = new Topic();
        $t1->name = 'hello_destroy_or_fail_multi_arg1';
        $t1->save();
        $t2 = new Topic();
        $t2->name = 'hello_destroy_or_fail_multi_arg2';
        $t2->save();
        $t3 = new Topic();
        $t3->name = 'hello_destroy_or_fail_multi_arg3';
        $t3->save();
        $this->assertEquals(3, Topic::destroyOrFail($t1->id, $t2->id, $t3->id));
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

    public function testWhere()
    {
        Topic::order('-id+name');
        Topic::where(array(
            'id'     => '<=3'
        ));
        $result = Topic::get()->result();
        $this->assertEquals(3, count($result));
        $this->assertEquals(1, $result[2]->id);
    }

    public function testPage()
    {
        Topic::order('-id');
        Topic::paging(3, 1);
        Topic::where('id', '<=3');
        $result = Topic::get()->result();
        $this->assertEquals(1, count($result));
        $this->assertEquals(1, $result[0]->id);
    }

    public function testGetWhere()
    {
        $data = Topic::getWhere(array(
            'order' => '-id',
            'page'  => '3',
            'id'    => '<=3'
        ), array('per_page' => 1));
        $this->assertEquals(3, $data['count']);
        $this->assertEquals(1, $data['result'][0]->id);
    }

}