<?php
namespace SimpleSoftwareIO\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use SimpleSoftwareIO\SMS\Facades\SMS;

class SmsController extends Controller
{
    public function index()
    {
        SMS::send('Your SMS Message', null, function($sms) {
            $sms->to('+15555555555');
        });
    }
}