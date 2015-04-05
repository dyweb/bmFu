<?php
/**
 * Created by PhpStorm.
 * User: ComMouse
 * Date: 2015/4/5
 * Time: 22:08
 */

class SoftDeleteTest extends PHPUnit_Framework_TestCase
{

    public function testNotFound()
    {
        try {
            $t = SoftTopic::find(2333);
        } catch (\Dy\Orm\Exception\NotFound $e) {
            $this->assertEquals($e->get_primary_key_value(), 2333);
        }
    }

    public function testFind()
    {
        $t = SoftTopic::find(1);
        $this->assertInstanceOf('SoftTopic', $t);
        $this->assertEquals('hangout', $t->name);
    }

    public function testFindDeleted()
    {
        $t = new SoftTopic();
        $t->name = 'test_find_deleted';
        $t->save();
        $id = $t->id;
        $t->delete();
        try {
            SoftTopic::find($id);
            // manually generate an error
            $this->assertEquals(0, 1);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Dy\Orm\Exception\NotFound', $e);
        }

    }

    public function testCount()
    {
        $count = SoftTopic::countAll();
        $this->assertTrue($count >= 2);
    }

    public function testCountWithTrashed()
    {
        $t1 = new SoftTopic();
        $t1->name = 'test_count_with_trashed1';
        $t1->save();
        $t1->delete();
        $t2 = new SoftTopic();
        $t2->name = 'test_count_with_trashed2';
        $t2->save();
        $t2->forceDelete();
        $t3 = new SoftTopic();
        $t3->name = 'test_count_with_trashed3';
        $t3->save();
        SoftTopic::where('id', '>= ' . $t1->id);
        SoftTopic::withTrashed();
        $count = SoftTopic::countAll();
        $this->assertTrue($count == 2);
    }

    public function testCountOnlyTrashed()
    {
        $t1 = new SoftTopic();
        $t1->name = 'test_count_only_trashed1';
        $t1->save();
        $t1->delete();
        $t2 = new SoftTopic();
        $t2->name = 'test_count_only_trashed2';
        $t2->save();
        $t2->forceDelete();
        $t3 = new SoftTopic();
        $t3->name = 'test_count_only_trashed3';
        $t3->save();
        SoftTopic::where('id', '>= ' . $t1->id);
        SoftTopic::onlyTrashed();
        $count = SoftTopic::countAll();
        $this->assertTrue($count == 1);
    }

    public function testDelete()
    {
        $t = new SoftTopic();
        $t->name = 'hello_delete';
        $t->save();
        $this->assertNotTrue($t->trashed());
        $this->assertTrue($t->delete());
        $this->assertTrue($t->exists());
        $this->assertTrue($t->trashed());
    }

    public function testDeleteOrFail()
    {
        $t = new SoftTopic();
        $t->name = 'hello_delete_or_fail';
        $t->save();
        // OK if no exception thrown
        $this->assertNotTrue($t->trashed());
        $this->assertTrue($t->deleteOrFail());
        $this->assertTrue($t->exists());
        $this->assertTrue($t->trashed());
    }

    public function testForcedDelete()
    {
        $t = new SoftTopic();
        $t->name = 'hello_forced_delete';
        $t->save();
        // OK if no exception thrown
        $this->assertNotTrue($t->trashed());
        $this->assertTrue($t->forceDelete());
        $this->assertNotTrue($t->exists());
        $this->assertTrue($t->trashed());
    }

    public function testRestore()
    {
        $t = new SoftTopic();
        $t->name = 'hello_restore';
        $t->save();
        $id = $t->id;
        $t->delete();

        SoftTopic::withTrashed();
        $t2 = SoftTopic::find($id);
        $this->assertTrue($t2->trashed());
        $this->assertTrue($t2->restore());
        $this->assertTrue($t2->exists());
        $this->assertNotTrue($t2->trashed());

        $t3 = new SoftTopic();
        $t3->name = 'hello_restore2';
        $t3->save();
        $t3->delete();
        $this->assertTrue($t3->trashed());
        $this->assertTrue($t3->restore());
        $this->assertTrue($t3->exists());
        $this->assertNotTrue($t3->trashed());
    }

    public function testDestroySingle()
    {
        $t = new SoftTopic();
        $t->name = 'hello_destroy';
        $t->save();
        $this->assertEquals(1, SoftTopic::destroy($t->id));
    }

    public function testDestroyWhere()
    {
        $t1 = new SoftTopic();
        $t1->name = 'hello_destroy_where_1';
        $t1->save();
        $start_id = $t1->id;
        $t2 = new SoftTopic();
        $t2->name = 'hello_destroy_where_2';
        $t2->save();
        $t3 = new SoftTopic();
        $t3->name = 'hello_destroy_where_3';
        $t3->save();
        $this->assertEquals(1, SoftTopic::destroyWhere(array('id' => ">= $start_id")));
        $this->assertEquals(2, SoftTopic::destroyWhere(array('id' => ">= $start_id"), 3));
    }

    public function testDestroyMulti()
    {
        $t1 = new SoftTopic();
        $t1->name = 'hello_destroy_multi1';
        $t1->save();
        $t2 = new SoftTopic();
        $t2->name = 'hello_destroy_multi2';
        $t2->save();
        $t3 = new SoftTopic();
        $t3->name = 'hello_destroy_multi3';
        $t3->save();
        $this->assertEquals(3, SoftTopic::destroy(array($t1->id, $t2->id, $t3->id)));
    }

    public function testDestroyMultiArg()
    {
        $t1 = new SoftTopic();
        $t1->name = 'hello_destroy_multi_arg1';
        $t1->save();
        $t2 = new SoftTopic();
        $t2->name = 'hello_destroy_multi_arg2';
        $t2->save();
        $t3 = new SoftTopic();
        $t3->name = 'hello_destroy_multi_arg3';
        $t3->save();
        $this->assertEquals(3, SoftTopic::destroy($t1->id, $t2->id, $t3->id));
    }

    public function testDestroyOrFailSingle()
    {
        $t = new SoftTopic();
        $t->name = 'hello_destroy_or_fail';
        $t->save();
        $this->assertEquals(1, SoftTopic::destroyOrFail($t->id));
    }

    public function testDestroyOrFailMulti()
    {
        $t1 = new SoftTopic();
        $t1->name = 'hello_destroy_or_fail_multi1';
        $t1->save();
        $t2 = new SoftTopic();
        $t2->name = 'hello_destroy_or_fail_multi2';
        $t2->save();
        $t3 = new SoftTopic();
        $t3->name = 'hello_destroy_or_fail_multi3';
        $t3->save();
        $this->assertEquals(3, SoftTopic::destroyOrFail(array($t1->id, $t2->id, $t3->id)));
    }

    public function testDestroyOrFailMultiArg()
    {
        $t1 = new SoftTopic();
        $t1->name = 'hello_destroy_or_fail_multi_arg1';
        $t1->save();
        $t2 = new SoftTopic();
        $t2->name = 'hello_destroy_or_fail_multi_arg2';
        $t2->save();
        $t3 = new SoftTopic();
        $t3->name = 'hello_destroy_or_fail_multi_arg3';
        $t3->save();
        $this->assertEquals(3, SoftTopic::destroyOrFail($t1->id, $t2->id, $t3->id));
    }

    public function testWhere()
    {
        SoftTopic::order('-id+name');
        SoftTopic::where(array(
            'id'     => '<=3'
        ));
        $result = SoftTopic::get()->result();
        $this->assertEquals(3, count($result));
        $this->assertEquals(1, $result[2]->id);
    }

    public function testWhereWithTrashed()
    {
        $t1 = new SoftTopic();
        $t1->name = 'test_where_with_trashed1';
        $t1->save();
        $t1->delete();
        $t2 = new SoftTopic();
        $t2->name = 'test_where_with_trashed2';
        $t2->save();
        $t2->forceDelete();
        $t3 = new SoftTopic();
        $t3->name = 'test_where_with_trashed3';
        $t3->save();
        SoftTopic::order('-id+name');
        SoftTopic::withTrashed();
        SoftTopic::where(array(
            'id'     => '>= ' . $t1->id
        ));
        $result = SoftTopic::get()->result();
        $this->assertEquals(2, count($result));
    }

    public function testWhereOnlyTrashed()
    {
        $t1 = new SoftTopic();
        $t1->name = 'test_where_only_trashed1';
        $t1->save();
        $t1->delete();
        $t2 = new SoftTopic();
        $t2->name = 'test_where_only_trashed2';
        $t2->save();
        $t2->forceDelete();
        $t3 = new SoftTopic();
        $t3->name = 'test_where_only_trashed3';
        $t3->save();
        SoftTopic::order('-id+name');
        SoftTopic::onlyTrashed();
        SoftTopic::where(array(
            'id'     => '>= ' . $t1->id
        ));
        $result = SoftTopic::get()->result();
        $this->assertEquals(1, count($result));
    }

    public function testGetWhere()
    {
        $data = SoftTopic::getWhere(array(
            'order' => '-id',
            'page'  => '3',
            'id'    => '<=3'
        ), array('per_page' => 1));
        $this->assertEquals(3, $data['count']);
        $this->assertEquals(1, $data['result'][0]->id);
    }
}
