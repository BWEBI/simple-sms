<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
	    Schema::create('sms_config', function (Blueprint $t) {
		    $t->increments('id');
		    $t->string('provider', 100)->unique();
		    $t->enum('api_format', ['JSON', 'XML']);
		    $t->integer('chars_per_unit');
	    });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
	    Schema::drop('sms_config');
    }
}
