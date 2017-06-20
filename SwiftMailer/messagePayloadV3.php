<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Mime_Message;
use \Swift_Attachment;
use \Swift_MimePart;

class messagePayloadV3 implements messageFormatStrategy {

    private $version = 'v3';

    /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_Message $message) {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];
        $attachments = array();
        // Process Headers
        $customHeaders = $this->prepareHeaders($message);
        $userDefinedHeaders = $this->findUserDefinedHeaders($message);
        if ($replyTo = $this->getReplyTo($message)) {
            $userDefinedHeaders = array_merge($userDefinedHeaders, array('Reply-To' => $replyTo));
        }
        // @TODO only Format To, Cc, Bcc
        $to = "";
        foreach ($toAddresses as $toEmail => $toName) {
            $to .= "$toName <$toEmail>";
        }
        $cc = "";
        foreach ($ccAddresses as $ccEmail => $ccName) {
            $cc .= "$toName <$toEmail>";
        }
        $bcc = "";
        foreach ($bccAddresses as $bccEmail => $bccName) {
            $bcc .= "$toName <$toEmail>";
        }
        // Handle content
        $bodyHtml = $bodyText = null;
        if ($contentType === 'text/plain') {
            $bodyText = $message->getBody();
        } elseif ($contentType === 'text/html') {
            $bodyHtml = $message->getBody();
        } else {
            $bodyHtml = $message->getBody();
        }
        // Handle attachments
        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment) {
                $attachments[] = array(
                    'Content-type' => $child->getContentType(),
                    'Filename' => $child->getFilename(),
                    'content' => base64_encode($child->getBody())
                );
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }
        $mailjetMessage = array(
            'FromEmail' => $fromEmails[0],
            'FromName' => $fromAddresses[$fromEmails[0]],
            'Html-part' => $bodyHtml,
            'Text-part' => $bodyText,
            'Subject' => $message->getSubject(),
            'Recipients' => $this->getRecipients($message)
        );
        if (count($userDefinedHeaders) > 0) {
            $mailjetMessage['Headers'] = $userDefinedHeaders;
        }
        if (count($customHeaders) > 0) {
            $mailjetMessage = array_merge($mailjetMessage, $customHeaders);
        }
        if (count($attachments) > 0) {
            $mailjetMessage['Attachments'] = $attachments;
        }
        // @TODO bulk messages
        return $mailjetMessage;
    }

    /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    public static function getMailjetHeaders() {
        return array(
            'X-MJ-TemplateID' => 'Mj-TemplateID',
            'X-MJ-TemplateLanguage' => 'Mj-TemplateLanguage',
            'X-MJ-TemplateErrorReporting' => 'MJ-TemplateErrorReporting',
            'X-MJ-TemplateErrorDeliver' => 'MJ-TemplateErrorDeliver',
            'X-Mailjet-Prio' => 'Mj-Prio',
            'X-Mailjet-Campaign' => 'Mj-campaign',
            'X-Mailjet-DeduplicateCampaign' => 'Mj-deduplicatecampaign',
            'X-Mailjet-TrackOpen' => 'Mj-trackopen',
            'X-Mailjet-TrackClick' => 'Mj-trackclick',
            'X-MJ-CustomID' => 'Mj-CustomID',
            'X-MJ-EventPayLoad' => 'Mj-EventPayLoad',
            'X-MJ-Vars' => 'Vars'
        );
    }

    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return string|null
     */
    protected function getReplyTo(Swift_Mime_Message $message) {
        if (is_array($message->getReplyTo())) {
            return current($message->getReplyTo()) . ' <' . key($message->getReplyTo()) . '>';
        }
    }

    /**
     * Extract Mailjet specific header
     * return an array of formatted data for Mailjet send API
     * @param  Swift_Mime_Message $message
     * @return array
     */
    protected function prepareHeaders(Swift_Mime_Message $message) {
        $mailjetHeaders = self::getMailjetHeaders();
        $messageHeaders = $message->getHeaders();
        $mailjetData = array();
        foreach (array_keys($mailjetHeaders) as $headerName) {
            /** @var \Swift_Mime_Headers_MailboxHeader $value */
            if (null !== $value = $messageHeaders->get($headerName)) {
                // Handle custom headers
                $mailjetData[$mailjetHeaders[$headerName]] = $value->getValue();
                // remove Mailjet specific headers
                $messageHeaders->removeAll($headerName);
            }
        }
        /* At this moment $messageHeaders is left with only custom user-defined headers,
         * we add those to $mailjetData
         */
        array_push($mailjetData, $messageHeaders);
        return $mailjetData;
    }

    /**
     * Extract user defined starting with X-*
     * @param  Swift_Mime_Message $message
     * @return array
     */
    private function findUserDefinedHeaders(Swift_Mime_Message $message) {
        $mailjetHeaders = self::getMailjetHeaders();
        $messageHeaders = $message->getHeaders();
        $userDefinedHeaders = array();

        foreach (array_keys($mailjetHeaders) as $headerName) {
            /** @var \Swift_Mime_Headers_MailboxHeader $value */
            if (null !== $value = $messageHeaders->get($headerName)) {
                // remove Mailjet specific headers
                $messageHeaders->removeAll($headerName);
            }
        }
        /* At this moment $messageHeaders is left with non-Mailjet specific headers
         * 
         */
        foreach ($messageHeaders->getAll() as $header) {
            if (0 === strpos($header->getFieldName(), 'X-')) {
                $userDefinedHeaders[$header->getFieldName()] = $header->getValue();
            }
        }
        return $userDefinedHeaders;
    }

    /**
     * @return array
     */
    protected function getSupportedContentTypes() {
        return array(
            'text/plain',
            'text/html'
        );
    }

    /**
     * @param string $contentType
     * @return bool
     */
    protected function supportsContentType($contentType) {
        return in_array($contentType, $this->getSupportedContentTypes());
    }

    /**
     * @param Swift_Mime_Message $message
     * @return string
     */
    protected function getMessagePrimaryContentType(Swift_Mime_Message $message) {
        $contentType = $message->getContentType();
        if ($this->supportsContentType($contentType)) {
            return $contentType;
        }
        // SwiftMailer hides the content type set in the constructor of Swift_Mime_SimpleMessage as soon
        // as you add another part to the message. We need to access the protected property
        // userContentType to get the original type.
        $messageRef = new \ReflectionClass($message);
        if ($messageRef->hasProperty('userContentType')) {
            $propRef = $messageRef->getProperty('userContentType');
            $propRef->setAccessible(true);
            $contentType = $propRef->getValue($message);
        }
        return $contentType;
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * @param Swift_Mime_Message $message
     *
     * @return array
     */
    protected function getRecipients(Swift_Mime_Message $message) {
        $to = [];
        if ($message->getTo()) {
            $to = array_merge($to, $message->getTo());
        }
        if ($message->getCc()) {
            $to = array_merge($to, $message->getCc());
        }
        if ($message->getBcc()) {
            $to = array_merge($to, $message->getBcc());
        }
        $recipients = [];
        foreach ($to as $address => $name) {
            $recipients[] = ['Email' => $address, 'Name' => $name];
        }
        return $recipients;
    }

    public function getVersion() {

        return $this->version;
    }

}
