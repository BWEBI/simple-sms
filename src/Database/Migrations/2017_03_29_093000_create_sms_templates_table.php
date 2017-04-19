<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
	public function up()
	{
		Schema::create('sms_templates', function (Blueprint $t) {
			//$t->increments('id')->references('template_id')->on('sms_notifications');
			$t->increments('id');
			$t->integer('creator_id');
			$t->string('name', 255);
			$t->text('body');
			$t->boolean('status');
			$t->timestamps();
			$t->dateTime('deleted_at');
		});   //
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sms_templates');
	}
}
