<?php
Route::group(
    [
	    'namespace' => 'SimpleSoftwareIO\SMS\Http\Controllers',
    ],
    function () {
        Route::get('cellact', 'SmsController@index');
    }
);