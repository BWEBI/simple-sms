<?php
namespace SimpleSoftwareIO\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use SimpleSoftwareIO\SMS\Facades\SMS;

class SmsController extends Controller
{
    public function index()
    {
        $extraData = [
            'extra_data' => [
                'template_id' => 1,
                'from_id' => 100,
                'event_id' => 1
            ]
        ];

        SMS::send('Your SMS Message', $extraData, function($sms) {
            $sms->to(['0523768198','052-3768198']);
        });
    }
}