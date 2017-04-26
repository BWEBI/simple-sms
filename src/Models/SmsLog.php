<?php
namespace SimpleSoftwareIO\SMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SmsLog extends Model
{
	protected $table = 'quotes';
	protected $guarded = ['id'];

	static function insertSmsNotify($dataArr)
	{
		return DB::table('sms_notifications')->insertGetId($dataArr);
	}

	static function updateSmsStatus($id, $dataArrUpdate)
	{
		DB::table('sms_notifications')
			->where('id', $id)
			->update($dataArrUpdate);
		return $id;
	}

}