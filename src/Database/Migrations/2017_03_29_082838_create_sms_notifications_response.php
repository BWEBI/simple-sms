<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsNotificationsResponse extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
	    Schema::create('sms_notifications_response', function (Blueprint $t) {
		    $t->increments('id');
		    $t->integer('notification_id')->index('notification_id');
		    //$t->foreign('template_id')->references('notification_id')->on('sms_notifications_response');

		    //$t->foreign('notification_id')->references('template_id')->on('sms_notifications');
		    $t->boolean('reject');
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
	    Schema::drop('sms_notifications_response');
    }
}
