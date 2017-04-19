<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsNotificationsCounterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
	public function up()
	{
		Schema::create('sms_notifications_counter', function (Blueprint $t) {
			$t->increments('id');
			$t->integer('from_id')->index('from_id');
			$t->integer('counter');
		});   //
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sms_notifications_counter');
	}
}
