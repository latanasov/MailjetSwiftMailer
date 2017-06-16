<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Events_EventDispatcher;
use \Swift_Events_EventListener;
use \Swift_Events_SendEvent;
use \Swift_Mime_Message;
use \Swift_Transport;
use \Swift_Attachment;
use \Swift_MimePart;
use Mailjet\Resources;

interface messageFormatStrategy {

    public function getMailjetMessage(Swift_Mime_Message $message);
}

/**
 * A SwiftMailer transport implementation for Mailjet
 */
class MailjetTransport implements Swift_Transport {

    /**
     * @var Swift_Events_EventDispatcher
     */
    protected $eventDispatcher;
    
    /**
     * @var messageFormatStrategy
     */
    public $messageFormat;

    /**
     * Mailjet API Key
     * @var string|null
     */
    protected $apiKey;

    /**
     * Mailjet API Secret
     * @var string|null
     */
    protected $apiSecret;

    /**
     * performs the call or not
     * @var bool
     */
    protected $call;

    /**
     * url (Default: api.mailjet.com) : domain name of the API
     * version (Default: v3) : API version (only working for Mailjet API V3 +)
     * call (Default: true) : turns on(true) / off the call to the API
     * secured (Default: true) : turns on(true) / off the use of 'https'
     * @var array|null
     */
    protected $clientOptions;

    /**
     * @var array|null
     */
    protected $resultApi;
    
      /**
     * @var \Mailjet\Client
     */
    protected  $mailjetClient;
    /**
     * @param Swift_Events_EventDispatcher $eventDispatcher
     * @param string $apiKey
     * @param string $apiSecret
     * @param array $clientOptions
     */
    public function __construct(Swift_Events_EventDispatcher $eventDispatcher, $apiKey = null, $apiSecret = null, $call = true, array $clientOptions = []) {
        $this->eventDispatcher = $eventDispatcher;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->call = $call;
        $this->clientOptions = $clientOptions;
        $this->mailjetClient = $this->createMailjetClient();
    }

    /**
     * Not used
     */
    public function isStarted() {
        return false;
    }

    /**
     * Not used
     */
    public function start() {
        
    }

    /**
     * Not used
     */
    public function stop() {
        
    }

    /**
     * Not used
     */
    public function ping() {
        
    }

    /**
     * @param Swift_Mime_Message $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null) {
        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;
        if ($event = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($event, 'beforeSendPerformed');
            if ($event->bubbleCancelled()) {
                return 0;
            }
        }
        $sendCount = 0;
        
        // extract Mailjet Message from SwiftMailer Message
        $mailjetMessage = $this->messageFormat->getMailjetMessage($message);
        
       
        try {
            // send API call
            $this->resultApi = $this->mailjetClient->post(Resources::$Email, $mailjetMessage);
            if (isset($this->resultApi->getBody()['Sent'])) {
                $sendCount += count($this->resultApi->getBody()['Sent']);
            }
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            $failedRecipients = $message->getTo();
            $sendCount = 0;
            $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
        }
        // Send SwiftMailer Event
        if ($event) {
            $event->setResult($resultStatus);
            $event->setFailedRecipients($failedRecipients);
            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
        }
        return $sendCount;
    }

    /**
     * @param array $message (of Swift_Mime_Message)
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function bulkSend(array $messages, &$failedRecipients = null) {
        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;

        $sendCount = 0;
        $bodyRequest = ['Messages' => []];

        foreach ($messages as $message) {
            // extract Mailjet Message from SwiftMailer Message
            $mailjetMessage = $this->getMailjetMessage($message);
            array_push($bodyRequest['Messages'], $mailjetMessage);
        }
        // Create mailjetClient
       

        try {
            // send API call
            $this->resultApi = $this->mailjetClient->post(Resources::$Email, $mailjetMessage);

            if (isset($this->resultApi->getBody()['Sent'])) {
                $sendCount += count($this->resultApi->getBody()['Sent']);
            }
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            //$failedRecipients = $mailjetMessage['Recipients'];
            $sendCount = 0;
            $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
        }

        return $sendCount;
    }

    /**
     * @return \Mailjet\Client
     * @throws \Swift_TransportException
     */
    protected function createMailjetClient() {
        
        if ($this->apiKey === null || $this->apiSecret === null) {
            throw new \Swift_TransportException('Cannot create instance of \Mailjet\Client while API key is NULL');
        }
        if (isset($this->clientOptions)) {
            if ($this->clientOptions['version'] == 'v3.1') {
            $this->messageFormat = new messagePayloadV31();
        }
        if ($this->clientOptions['version'] == 'v3') {
            $this->messageFormat = new messagePayloadV3();
        } else {
            //todo throw some error or pick one as default
        }
            return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call, $this->clientOptions);
        }
        return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call);
    }

    /**
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin) {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * @param string $apiSecret
     * @return $this
     */
    public function setApiSecret($apiSecret) {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getApiSecret() {
        return $this->apiSecret;
    }

    /**
     * @param bool $call
     * @return $this
     */
    public function setCall($call) {
        $this->call = $call;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCall() {
        return $this->call;
    }

    /**
     * @param array $clientOptions
     * @return $this
     */
    public function setClientOptions(array $clientOptions = []) {
        $this->clientOptions = $clientOptions;
        return $this;
    }

    /**
     * @return null|array
     */
    public function getClientOptions() {
        return $this->clientOptions;
    }

    /**
     * @return null|array
     */
    public function getResultApi() {
        return $this->resultApi;
    }

}
