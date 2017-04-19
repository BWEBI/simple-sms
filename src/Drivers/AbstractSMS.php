<?php

namespace SimpleSoftwareIO\SMS\Drivers;

use SimpleSoftwareIO\SMS\IncomingMessage;
use SimpleSoftwareIO\SMS\SMSNotSentException;
use SimpleSoftwareIO\SMS\Models\SmsLog;

abstract class AbstractSMS
{

    /**
     * Default variables for insertion
     * @var array
     */
    public $default_extra_data = [
        'template_id'       => 0,           // SMS template | @var int
        'destination_type'  => 'client',    // SMS destination type >> client/company | @var string
        'event_id'          => 0,           // Notification event triggering the SMS | @var int
        'status'            => 0,           // Status before api connection | @var int
        'api_connection'    => 0,           // If connection was made to Api | @var int
    ];

    protected $debug;

    /**
     * Throw a not sent exception.
     *
     * @param string   $message
     * @param null|int $code
     *
     * @throws SMSNotSentException
     */
    public function throwNotSentException($message, $code = null)
    {
        throw new SMSNotSentException($message, $code);
    }

    /**
     * Creates a new IncomingMessage instance.
     *
     * @return IncomingMessage
     */
    protected function createIncomingMessage()
    {
        return new IncomingMessage();
    }

    /**
     * Creates many IncomingMessage objects.
     *
     * @param string $rawMessages
     *
     * @return array
     */
    protected function makeMessages($rawMessages)
    {
        $incomingMessages = [];
        foreach ($rawMessages as $rawMessage) {
            $incomingMessages[] = $this->processReceive($rawMessage);
        }

        return $incomingMessages;
    }

    /**
     * Creates a single IncomingMessage object.
     *
     * @param string $rawMessage
     *
     * @return mixed
     */
    protected function makeMessage($rawMessage)
    {
        return $this->processReceive($rawMessage);
    }

    /**
     * Creates many IncomingMessage objects and sets all of the properties.
     *
     * @param string $rawMessage
     *
     * @return mixed
     */
    abstract protected function processReceive($rawMessage);

    /**
     * Defines if debug is enabled or disabled (SMS77).
     *
     * @param $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }


    public function getExtraDataByKey($dataObj, $key)
    {
        if (!isset($dataObj[$key])) {
            $dataObj[$key] = isset($this->default_extra_data[$key]) ? $this->default_extra_data[$key] : null;
        }

        return $dataObj[$key];
    }

}
