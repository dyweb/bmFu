<?php

/**
 * Created by PhpStorm.
 * User: at15
 * Date: 15-2-19
 * Time: 下午11:48
 */
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run()
    {
        DB::table('tags')->insert(array('name' => 'hangout'));
        DB::table('tags')->insert(array('name' => 'homework'));
    }
}