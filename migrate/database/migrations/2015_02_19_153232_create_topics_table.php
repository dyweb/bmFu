<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTopicsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('topics', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            $table->dateTime('create_time')->default('0000-00-00 00:00:00');
            $table->dateTime('update_time')->default('0000-00-00 00:00:00');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('topics');
	}

}
