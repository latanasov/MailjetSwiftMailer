<?php
namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Mime_Message;
use \Swift_Attachment;
use \Swift_MimePart;
class messagePayloadV31 implements messageFormatStrategy {
 
  /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
 public function getMailjetMessage(Swift_Mime_Message $message)
    {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];

        $attachments = array();

        // Process Headers
        $headers = array();
        $mailjetSpecificHeaders = $this->prepareHeaders($message);

        // @TODO only Format To, Cc, Bcc
        $to = array();
        foreach ($toAddresses as $toEmail => $toName) {
            array_push($to, ['Email' => $toEmail, 'Name' => $toName]);
        }
        $cc = array();
        foreach ($ccAddresses as $ccEmail => $ccName) {
            array_push($cc, ['Email' => $ccEmail, 'Name' => $ccName]);
        }
        $bcc = array();
        foreach ($bccAddresses as $bccEmail => $bccName) {
            array_push($bcc, ['Email' => $bccEmail, 'Name' => $bccName]);
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
                    'ContentType'    => $child->getContentType(),
                    'Filename'    => $child->getFilename(),
                    'Base64Content' => base64_encode($child->getBody())
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
            'From'  => array(
                'Email' => $fromEmails[0],
                'Name'  => $fromAddresses[$fromEmails[0]]
            ),
            'To'    => $to,
            'Cc'    => $cc,
            'Bcc'   => $bcc,
            'HTMLPart'  => $bodyHtml,
            'TextPart'  => $bodyText,
            'Subject'   => $message->getSubject(),
        );

        if ($replyTo = $this->getReplyTo($message)) {
            $mailjetMessage['ReplyTo'] = $replyTo;
        }

        if (count($headers) > 0) {
            $mailjetMessage['Headers'] = $headers;
        }

        if (count($mailjetSpecificHeaders) > 0) {
            $mailjetMessage = array_merge($mailjetMessage, $mailjetSpecificHeaders);
        }

        if (count($attachments) > 0) {
            $mailjetMessage['Attachments'] = $attachments;
        }

        // @TODO bulk messages

        return ['body' => ['Messages' => $mailjetMessage]];
    }


       /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    private static function getMailjetHeaders()
    {
        return array(
            'X-MJ-TemplateID' => 'TemplateID',
            'X-MJ-TemplateLanguage' => 'TemplateLanguage',
            'X-MJ-TemplateErrorReporting' => 'TemplateErrorReporting',
            'X-MJ-TemplateErrorDeliver' => 'TemplateErrorDeliver',
            'X-Mailjet-Prio' => 'Priority',
            'X-Mailjet-Campaign' => 'CustomCampaign',
            'X-Mailjet-DeduplicateCampaign' => 'DeduplicateCampaign',
            'X-Mailjet-TrackOpen' => 'TrackOpens',
            'X-Mailjet-TrackClick' => 'TrackClicks',
            'X-MJ-CustomID' => 'CustomID',
            'X-MJ-EventPayLoad' => 'EventPayload',
            'X-MJ-MonitoringCategory' => 'MonitoringCategory',
            'X-MJ-Vars' => 'Variables'
            );
    }
    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return array|null
     */
    private function getReplyTo(Swift_Mime_Message $message)
    {
        if (is_array($message->getReplyTo())) {
            return array('Email' => key($message->getReplyTo()), 'Name' => current($message->getReplyTo()));
        } elseif (is_string($message->getReplyTo())) {
            return array('Email' => $message->getReplyTo());
        } else {
            return null;
        }
    }
    private function prepareHeaders(Swift_Mime_Message $message)
    {
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

        return $mailjetData;
    }
    
        /**
     * @return array
     */
    private function getSupportedContentTypes()
    {
        return array(
            'text/plain',
            'text/html'
        );
    }

    /**
     * @param string $contentType
     * @return bool
     */
    private function supportsContentType($contentType)
    {
        return in_array($contentType, $this->getSupportedContentTypes());
    }

    /**
     * @param Swift_Mime_Message $message
     * @return string
     */
    private function getMessagePrimaryContentType(Swift_Mime_Message $message)
    {
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
}