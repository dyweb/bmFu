<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:03
 */
class TimestampTest extends PHPUnit_Framework_TestCase
{
    public function testSaveId()
    {
        $t = new Tag();
        $t->name = 'xiaoming';
        $t->save();
        $this->assertTrue($t->id > 0);
    }

    public function testSaveNotModifiedFail()
    {
        $t = Tag::find(1);
        try {
            $t->save();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotModified', $e);
        }
    }

    public function testSaveUpdate()
    {
        $t = Tag::find(3);
        $t->name = 'callmemaybe' . time();
        $t->save();
        $this->assertEquals(3, $t->id);
    }

    public function testDelete()
    {
        $t = new Tag();
        $t->name = 'hello_delete';
        $t->save();
        $this->assertTrue($t->forceDelete());
        $this->assertNotTrue($t->exists());
    }

    public function testDeleteEmpty()
    {
        $t = new Tag();
        $this->assertNotTrue($t->delete());
        $this->assertNotTrue($t->exists());
    }

    public function testDeleteOrFail()
    {
        $t = new Tag();
        $t->name = 'hello_delete_or_fail';
        $t->save();
        // OK if no exception thrown
        $this->assertTrue($t->deleteOrFail());
        $this->assertTrue($t->trashed());
    }

    public function testDeleteOrFailEmpty()
    {
        $t = new Tag();
        try {
            $t->delete();
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotExists', $e);
        }
    }

    public function testDestroySingle()
    {
        $t = new Tag();
        $t->name = 'hello_destroy';
        $t->save();
        $this->assertEquals(1, Tag::destroy($t->id));
    }

    public function testDestroyWhere()
    {
        $t1 = new Tag();
        $t1->name = 'hello_destroy_where_1';
        $t1->save();
        $start_id = $t1->id;
        $t2 = new Tag();
        $t2->name = 'hello_destroy_where_2';
        $t2->save();
        $t3 = new Tag();
        $t3->name = 'hello_destroy_where_3';
        $t3->save();
        $this->assertEquals(1, Tag::destroyWhere(array('id' => ">= $start_id")));
        $this->assertEquals(2, Tag::destroyWhere(array('id' => ">= $start_id"), 3));
    }

    public function testDestroyMulti()
    {
        $t1 = new Tag();
        $t1->name = 'hello_destroy_multi1';
        $t1->save();
        $t2 = new Tag();
        $t2->name = 'hello_destroy_multi2';
        $t2->save();
        $t3 = new Tag();
        $t3->name = 'hello_destroy_multi3';
        $t3->save();
        $this->assertEquals(3, Tag::destroy(array($t1->id, $t2->id, $t3->id)));
    }

    public function testGetWhere()
    {
        $data = Tag::getWhere(array(
            'order' => '-id',
            'page'  => '3',
            'id'    => '<=3'
        ), array('per_page' => 1));
        $this->assertEquals(3, $data['count']);
        $this->assertEquals(1, $data['result'][0]->id);
    }

}