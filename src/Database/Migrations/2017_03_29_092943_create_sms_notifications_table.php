<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsNotificationsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sms_notifications', function (Blueprint $t) {
			$t->increments('id');
			$t->integer('template_id')->index('template_id');
			$t->enum('destination_type', ['client', 'company'])->default('client');
			$t->integer('from_id');
			$t->string('from_name', 100);
			$t->string('from_phone', 50);
			$t->string('to_phone', 50);
			$t->text('message');
			$t->integer('num_of_units');
			$t->integer('event_id')->default(0);
			$t->string('provider', 100);
			$t->boolean('api_connection')->default(0);
			$t->boolean('status')->default(0);
			$t->string('code_key', 50)->nullable();
			$t->timestamp('created_at');
		});   //
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sms_notifications');
	}
}
