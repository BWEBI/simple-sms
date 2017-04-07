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
	protected $apiEnding = ['format' => 'json'];

	/**
	 * Constructs a new instance.
	 *
	 * @param Client $client
	 */
	public function __construct(Client $client)
	{
		//$this->sms = $sms;
//		$this->sms_properties = Config::get('sms');
		$this->client = $client;
		$this->config = config('sms.cellact');
		//dd($this->config);
	}

	/**
	 * Sends a SMS message.
	 *
	 * @param \SimpleSoftwareIO\SMS\OutgoingMessage $message
	 */
	public function send(OutgoingMessage $message)
	{
		$composedMessage = $message->composeMessage();

		$data = [
			'PhoneNumbers' => $message->getTo(),
			'Message' => $composedMessage,
		];
		$this->buildCall('/sending/messages');
		$this->buildBody($data);

		$this->postRequest();
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

// here starts BWEBI functions


	public function startsms()
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

				// send back to send function
//				$foos = SMS::send();
//				SMS::send('Your SMS Message', null, function($sms) {
//					$sms->to('+15555555555');
//				});
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

	public function doInsert()
	{
		//num_of_units
		$sms_data = $this->smsParams;
		$smsUnits = ceil(strlen($sms_data['smsContent']) / $this->config['chars_per_unit']);
		$dataArr = [
			'template_id' => '301',
			'destination_type' => 'client',
			'from_id' => '401',
			'from_name' => $sms_data['smsFrom'],
			'from_phone' => $sms_data['smsSender'],
			'to_phone' => $sms_data['smsTo'],
			'message' => $sms_data['smsContent'],
			'num_of_units' => $smsUnits, //$sms_data['smsContent'],
			'event_id' => '501',
			'provider' => $this->provider,
		];
		//dd($dataArr);
		return SmsLog::insertSmsNotify($dataArr);
	}

	public function doUpdate($id, $res)
	{
		$JSONfromXML = $this->XMLtoJSON($res);
		$dataArr['status'] = ($JSONfromXML['RESULT'] === 'True') ? '1' : '0';
		$dataArr['session'] = ($dataArr['status'] === '1') ? $JSONfromXML['SESSION'] : "";        //Check if any valid keys should be pulled out from the Api response
		return SmsLog::updateSmsStatus($id, $dataArr);
	}


	public function orderArray($smsParams)
	{
			$checkOrder = [
				'HEAD' => [
					'FROM' => $smsParams['smsFrom'],
					'APP' => [
						'@attributes' => [
							'USER' => $smsParams['smsUser'],
							'PASSWORD' => $smsParams['smsPW'],
						],
						'@value' => $smsParams['smsApp']
					],
					'CMD' => $smsParams['smsCMD'],
					//'TTS' => $smsParams['smsTTS'],
					//'TTL' => $smsParams['smsTTL'],
				],
				'BODY' => [
					'SENDER' => $smsParams['smsSender'],
					'CONTENT' => $smsParams['smsContent'],
					'DEST_LIST' => [
						'TO' => [
							'+972528623326',
							'+972523768198',
							'+97252862332',
						]
					]
				],
				//'OPTIONAL' => [
				//	'MSG_ID' => $smsParams['smsMsgId'],
				//	'SERVICE_NAME' => $smsParams['smsService'],
				//]
			];
		return $checkOrder;
	}

	public function XMLtoJSON($xmlData)
	{
		$xml = simplexml_load_string($xmlData);
		$json = json_encode($xml);
		return json_decode($json, TRUE);
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

	public function doSms($actionType, array $params)
	{
		// next 2 lines for Post XML
		$xml = Array2XML::createXML('PALO', $params);
		return $this->makeCall($url, $xml->saveXML());
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
				curl_close($curl);
				return $server_output;
			}
		}
		curl_close($curl);
	}
}
