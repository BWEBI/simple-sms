<?php
/**
 * Created by PhpStorm.
 * User: DevTeam
 * Date: 06/04/2017
 * Time: 12:06
 */

namespace SimpleSoftwareIO\SMS\Drivers;

use GuzzleHttp\Client;
use SimpleSoftwareIO\SMS\MakesRequests;
use SimpleSoftwareIO\SMS\OutgoingMessage;
use SimpleSoftwareIO\SMS\Models\SmsLog;
use SimpleSoftwareIO\SMS\Models\ValidModel;
use SimpleSoftwareIO\SMS\Helpers\Array2XML;
use SimpleSoftwareIO\SMS\SMS;

class CellActSMS extends AbstractSMS implements DriverInterface

{
    public $sms;

    public $provider = 'CellAct';

    public $rules = [
        'smsFrom' => 'required',
        'smsUser' => 'required',
        'smsPW' => 'required',
        'smsApp' => 'required',
        'smsCMD' => 'required',
        'smsTTS' => 'nullable',
        'smsTTL' => 'nullable',
        'smsSender' => 'nullable',
        'smsContent' => 'required',
        'smsTo' => 'required',
        'smsMsgId' => 'nullable',
        'smsService' => 'nullable',
    ];

    use MakesRequests;

    /**
     * The Guzzle HTTP Client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * The API's URL.
     *
     * @var string
     */
    protected $apiBase = 'https://la.cellactpro.com/unistart5.asp';

    /**
     * The ending of the URL that all requests must have.
     *
     * @var array
     */
    protected $apiEnding = [];

    /**
     * Constructs a new instance.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->config = config('sms.cellact');
        $this->setUser($this->config['user']);
        $this->setPassword($this->config['password']);
    }

    /**
     * Sends a SMS message.
     *
     * @param \SimpleSoftwareIO\SMS\OutgoingMessage $message
     */
    public function send(OutgoingMessage $message)
    {
        $composedMessage = $message->composeMessage();
        $this->extraData = $message->getExtraData();

        foreach ($message->getTo() as $to) {
            $data = [
                'template_id'       => $this->getExtraDataByKey($this->extraData, 'template_id'),
                'destination_type'  => $this->getExtraDataByKey($this->extraData, 'destination_type'),
                'from_id'           => $this->getExtraDataByKey($this->extraData, 'from_id'),
                'from_name'         => $this->config['from'],
                'from_phone'        => '',
                'to_phone'          => $to,
                'message'           => $composedMessage,
                'event_id'          => $this->getExtraDataByKey($this->extraData, 'event_id'),
                'provider'          => $this->provider,
                'status'            => $this->getExtraDataByKey($this->extraData, 'status'),
                'api_connection'    => $this->getExtraDataByKey($this->extraData, 'api_connection'),
            ];

            $this->buildBody($data);

            $id = $this->doInsert();

            $api_data = $this->generateMessageBody($id);

            $this->makeCall($this->buildUrl(), $api_data);
        }
    }

    /**
     * Checks the server for messages and returns their results.
     *
     * @param array $options
     *
     * @return array
     */
    public function checkMessages(array $options = [])
    {
        $this->buildCall('/incoming-messages');
        $this->buildBody($options);

        $rawMessages = $this->getRequest()->json();

        return $this->makeMessages($rawMessages['Response']['Entries']);
    }

    /**
     * Gets a single message by it's ID.
     *
     * @param string|int $messageId
     *
     * @return \SimpleSoftwareIO\SMS\IncomingMessage
     */
    public function getMessage($messageId)
    {
        $this->buildCall('/incoming-messages');
        $this->buildCall('/' . $messageId);

        $rawMessage = $this->getRequest()->json();

        return $this->makeMessage($rawMessage['Response']['Entry']);
    }

    /**
     * Returns an IncomingMessage object with it's properties filled out.
     *
     * @param $rawMessage
     *
     * @return mixed|\SimpleSoftwareIO\SMS\IncomingMessage
     */
    protected function processReceive($rawMessage)
    {
        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($rawMessage);
        $incomingMessage->setFrom($rawMessage['PhoneNumber']);
        $incomingMessage->setMessage($rawMessage['Message']);
        $incomingMessage->setId($rawMessage['ID']);
        $incomingMessage->setTo('313131');

        return $incomingMessage;
    }

    /**
     * Receives an incoming message via REST call.
     *
     * @param mixed $raw
     *
     * @return \SimpleSoftwareIO\SMS\IncomingMessage
     */
    public function receive($raw)
    {
        //Due to the way EZTexting handles Keyword Submits vs Replys
        //We must check both values.
        $from = $raw->get('PhoneNumber') ? $raw->get('PhoneNumber') : $raw->get('from');
        $message = $raw->get('Message') ? $raw->get('Message') : $raw->get('message');

        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($raw->get());
        $incomingMessage->setFrom($from);
        $incomingMessage->setMessage($message);
        $incomingMessage->setTo('313131');

        return $incomingMessage;
    }

    /**
     * Calculate SMS units based on the driver config parameter
     * (function could be moved to OutgoingMessage class)
     * @param $messageText
     * @return float|int
     */
    public function calculateMessageUnits()
    {
        $units = !empty($this->getBody()['message']) ? ceil(strlen($this->getBody()['message']) / $this->config['chars_per_unit']) : 0;

        return (int)$units;
    }

// here starts BWEBI functions


    public function index()
    {
        // need to create variables from information for the array just for testing to make sure it all works
        // validate works with this array NOT the array created for the XML
        $smsParams = $this->getSmsParams();

        $this->sms_data = $this->orderArray($smsParams);
        $this->smsParams = $smsParams;
        $this->smsSendTo = explode(',', $smsParams['smsTo']);
        $this->allResponses = [];

        //validate entire model
        $valid = new ValidModel($this->smsParams, $this->rules);
        if ($valid->hasErrors()) {
            $responseArr = Array('success' => 0, 'msg' => $valid->getErrors());
            return $responseArr;
        }
        foreach ($this->smsSendTo as $key => $value) {

            $this->smsParams['smsTo'] = $value;

            // Prepare and insert initial sms data
            $id = $this->doInsert();
            if ($id) {
                $this->sms_data['BODY']['DEST_LIST']['TO'] = $this->smsParams['smsTo'];
                // Send sms request to Api
                $apiFullResult = Array2XML::createXML('PALO', $this->sms_data)->saveXML();


                //return $this->makeCall($url, $xml->saveXML());
                $apiTFresult = $this->XMLtoJSON($apiFullResult);
                if ($apiTFresult['RESULT'] === "True") {
                    // Update inserted sms with Api results
                    $this->doUpdate($id, $apiFullResult);
                    $responseArr = Array('success' => 1, 'id' => $id);
                } else {
                    $responseArr = Array('success' => 0, 'msg' => 'Error Reported From Company On Attempt To Send');
                }
                //build array of possible multiple responses
                $this->allResponses[] = $responseArr;
            }
        }
        return json_encode($this->allResponses);
        //return json_encode($responseArr);
    }

    public function doUpdate($id, $res)
    {
        $JSONfromXML = $this->XMLtoJSON($res);
        $dataArr['status'] = ($JSONfromXML['RESULT'] === 'True') ? '1' : '0';
        $dataArr['session'] = ($dataArr['status'] === '1') ? $JSONfromXML['SESSION'] : "";        //Check if any valid keys should be pulled out from the Api response
        return SmsLog::updateSmsStatus($id, $dataArr);
    }

    public function doInsert()
    {

        $dataArr = $this->getBody();
        $dataArr['num_of_units'] = $this->calculateMessageUnits();

        return SmsLog::insertSmsNotify($dataArr);
    }

    public function getSmsParams()
    {
        return [
            'smsFrom' => $this->config['from'],
            'smsUser' => $this->config['user'],
            'smsPW' => $this->config['password'],
            'smsApp' => $this->config['app'],
            'smsCMD' => $this->config['cmd'],
            'smsTTS' => '90',
            'smsTTL' => '180',
            'smsSender' => '',
            'smsContent' => 'This is an example text message sent through Cellact for Phoneplus',
            'smsTo' => '052-8623326,052862332,0523768198',
            //'smsMsgId' => '8772365',
            //'smsService' => 'phoneplus',
        ];

    }


    public function generateMessageBody($msgID = 0, $sender = null)
    {
        $data = [
            'HEAD' => [
                'FROM' => $this->config['from'],
                'APP' => [
                    '@attributes' => [
                        'USER' => $this->getAuth()[0],
                        'PASSWORD' => $this->getAuth()[1],
                    ],
                    '@value' => $this->config['app']
                ],
                'CMD' => $this->config['cmd'],
                //'TTS' => $smsParams['smsTTS'],
                //'TTL' => $smsParams['smsTTL'],
            ],
            'BODY' => [
                'SENDER' => $sender,
                'CONTENT' => $this->getBody()['message'],
                'DEST_LIST' => [
                    'TO' => $this->getBody()['to_phone']
                ]
            ],
            'OPTIONAL' => [
                'MSG_ID' => $msgID
//				'SERVICE_NAME' => $smsParams['smsService'],
            ]
        ];

        $msg_body = Array2XML::createXML('PALO', $data)->saveXML();

        return $msg_body;
    }

    public function XMLtoJSON($xmlData)
    {
        $xml = simplexml_load_string($xmlData);
        $json = json_encode($xml);
        return json_decode($json, TRUE);
    }

    public function makeCall($url, $XMLString)
    {
        //For Post XML
        $headers[] = "Content-type: application/x-www-form-urlencoded";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $XMLString = str_replace('%', '%25', $XMLString);
        $XMLString = str_replace(' ', '%20', $XMLString);
        $XMLString = str_replace('#', '%23', $XMLString);
        $XMLString = str_replace('&', '%26', $XMLString);
        $XMLString = str_replace('?', '%3F', $XMLString);
        $XMLString = str_replace('+', '%2B', $XMLString);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "XMLString=$XMLString");
        $server_output = curl_exec($curl);

        // Check if any error occurred
        if (!curl_errno($curl)) {
            $info = curl_getinfo($curl);
            dd($server_output);
            if ((int)$info['http_code'] == 200) {
                curl_close($curl);
                return $server_output;
            }
        }
        curl_close($curl);
    }
}
