<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Mime_Message;
use \Swift_Attachment;
use \Swift_MimePart;

class messagePayloadV31 implements messageFormatStrategy {

    private $version = 'v3.1';

    /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_Message $message) {
        $contentType = Utils::getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];

        $attachments = array();
        $inline_attachments = array();

        // Process Headers
        $customHeaders = Utils::prepareHeaders($message, $this->getMailjetHeaders());
        $userDefinedHeaders = Utils::findUserDefinedHeaders($message);


        // @TODO only Format To, Cc, Bcc
        //@TODO array_push is not recommended
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
        } else {
            $bodyHtml = $message->getBody();
        }


        // Handle attachments
        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment) {
                //Handle regular attachments
                if ($child->getDisposition() === "attachment") {
                    $attachments[] = array(
                        'ContentType' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'Base64Content' => base64_encode($child->getBody())
                    );
                }
                //Handle inline attachments
                elseif ($child->getDisposition() === "inline") {
                    $inline_attachments[] = array(
                        'ContentType' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'ContentID' => $child->getId(),
                        'Base64Content' => base64_encode($child->getBody())
                    );
                }
            } elseif ($child instanceof Swift_MimePart && Utils::supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }

        $mailjetMessage = array(
            'From' => array(
                'Email' => $fromEmails[0],
                'Name' => $fromAddresses[$fromEmails[0]]
            ),
            'To' => $to,
            'Cc' => $cc,
            'Bcc' => $bcc,
            'HTMLPart' => $bodyHtml,
            'TextPart' => $bodyText,
            'Subject' => $message->getSubject(),
        );

        if ($replyTo = $this->getReplyTo($message)) {
            $mailjetMessage['ReplyTo'] = $replyTo;
        }

        if (count($userDefinedHeaders) > 0) {
            $mailjetMessage['Headers'] = $userDefinedHeaders;
        }

        if (count($customHeaders) > 0) {
            $mailjetMessage = array_merge($mailjetMessage, $customHeaders);
        }

        if (count($attachments) > 0) {
            $mailjetMessage['Attachments'] = $attachments;
        }
        if (count($inline_attachments) > 0) {
            $mailjetMessage['InlinedAttachments'] = $inline_attachments;
        }


        return ['Messages' => $mailjetMessage];
    }

    /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    private static function getMailjetHeaders() {
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
    private function getReplyTo(Swift_Mime_Message $message) {
        if (is_array($message->getReplyTo())) {
            return array('Email' => key($message->getReplyTo()), 'Name' => current($message->getReplyTo()));
        } elseif (is_string($message->getReplyTo())) {
            return array('Email' => $message->getReplyTo());
        } else {
            return null;
        }
    }

    public function getVersion() {

        return $this->version;
    }

}
