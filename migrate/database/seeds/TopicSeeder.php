<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:48
 */
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    public function run()
    {
        DB::table('topics')->insert(array('name' => 'hangout'));
        DB::table('topics')->insert(array('name' => 'homework'));
    }
}