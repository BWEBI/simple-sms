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
        'from_id' => 'required|integer',
        'from_name' => 'required',
        'to_phone' => 'required',
        'message' => 'required'
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
     * @return array|bool
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

            // Check for request validation errors
            if ($this->hasErrors()) {
                return $this->hasErrors();
            }

            // Insert to DB before Api call
            $id = $this->doInsert();
            if (!$id){
                return self::handleResponse(0, 'Failed to insert Data');
            }

            // Generate the message and make the Api call
            $api_data = $this->generateMessageBody($id);
            $api_result = $this->makeCall($this->buildUrl(), $api_data);

            // Format the Api response and update DB
            return $this->handleApiResult($id, $api_result);
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
        // Populate when needed
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
        // Populate when needed
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
        // Populate when needed
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
        // Populate when needed
    }

    public function doInsert()
    {

        $dataArr = $this->getBody();
        $dataArr['num_of_units'] = $this->calculateMessageUnits();

        return SmsLog::insertSmsNotify($dataArr);
    }

    /**
     * Calculate SMS units based on the driver config parameter
     * (function could be moved to OutgoingMessage class)
     * @return int
     */
    public function calculateMessageUnits()
    {
        $units = !empty($this->getBody()['message']) ? ceil(strlen($this->getBody()['message']) / $this->config['chars_per_unit']) : 0;

        return (int)$units;
    }

    public function handleApiResult($id, $res)
    {
        // Format api response
        $responseArr = Array2XML::XMLtoJSON($res);
        $dataArr['status'] = ($responseArr['RESULT'] === 'True') ? '1' : '0';
        $dataArr['code_key'] = ($dataArr['status'] === '1') ? $responseArr['SESSION'] : '';
        $dataArr['api_connection'] = '1';

        // Update DB with Api response
        SmsLog::updateSmsStatus($id, $dataArr);

        if ($dataArr['status']) {
            return self::handleResponse(1, null, $id);
        } else {
            return self::handleResponse(0, 'Failed to send SMS');
        }
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
            if ((int)$info['http_code'] == 200) {
                //
            }
        }

        curl_close($curl);

        return $server_output;
    }

    /**
     * Validate request
     * @return array|bool
     */
    protected function hasErrors()
    {
        $valid = new ValidModel($this->getBody(), $this->rules);

        if ($valid->hasErrors()) {
            return self::handleResponse(0, $valid->getErrors());
        }

        return false;

    }

    /**
     * @param int $success
     * @param null $msg
     * @param null $data
     * @return array
     */
    static function handleResponse($success=1, $msg=null, $data=null)
    {
        $responseArr = Array('success' => $success, 'msg' => $msg, 'data' => $data);

        return $responseArr;

    }

}
