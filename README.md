# MailjetSwiftMailer

[![Build Status](https://travis-ci.org/welpdev/MailjetSwiftMailer.svg?branch=master)](https://travis-ci.org/welpdev/MailjetSwiftMailer)
[![Packagist](https://img.shields.io/packagist/v/welp/mailjet-swiftmailer.svg)](https://packagist.org/packages/welp/mailjet-swiftmailer)
[![Packagist](https://img.shields.io/packagist/dt/welp/mailjet-swiftmailer.svg)](https://packagist.org/packages/welp/mailjet-swiftmailer)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/welpdev/MailjetSwiftMailer/blob/master/LICENSE.md)

A SwiftMailer transport implementation for Mailjet

*Compatible Mailjet send API V3*

🚧 **WORK IN PROGRESS...** 🚧

## Installation

Require the package with composer

    composer require welp/mailjet-swiftmailer

## Usage Example

    $transport = new MailjetTransport($dispatchEvent, $apiKey, $apiSecret);
    $transport->setClientOptions(['url' => "www.mailjet.com", 'version' => 'v3', 'call' => false]); // optional

    $transport->send($message);

## Mailjet client custom configuration

You can pass an array in transport's constructor or use `setClientOptions` function:

    $clientOptions = ['url' => "www.mailjet.com", 'version' => 'v3', 'call' => false];
    $transport = new MailjetTransport($dispatchEvent, $apiKey, $apiSecret, $clientOptions);

    or

    $transport->setClientOptions(['url' => "www.mailjet.com", 'version' => 'v3', 'call' => false]);


Properties of $options:

* url (Default: api.mailjet.com) : domain name of the API
* version (Default: v3) : API version (only working for Mailjet API V3 +)
* call (Default: true) : turns on(true) / off the call to the API
* secured (Default: true) : turns on(true) / off the use of 'https'

## Mailjet custom headers

    'X-MJ-TemplateID'
    'X-MJ-TemplateLanguage'
    'X-MJ-TemplateErrorReporting'
    'X-MJ-TemplateErrorDeliver'
    'X-Mailjet-Prio'
    'X-Mailjet-Campaign'
    'X-Mailjet-DeduplicateCampaign'
    'X-Mailjet-TrackOpen'
    'X-Mailjet-TrackClick'
    'X-MJ-CustomID'
    'X-MJ-EventPayLoad'
    'X-MJ-Vars'

For example:

    $message->getHeaders()->addTextHeader('X-MJ-TemplateLanguage', true);

[Mailjet documentation](https://dev.mailjet.com/guides/#send-api-json-properties)

## Mailjet bulk sending

@TODO

## Integration in Symfony

If you want to use MailjetTransport in your Symfony project follow these small steps:

1. `composer require welp/mailjet-swiftmailer`
2. Into your `services.yml`, register MailjetTransport:

```yaml
swiftmailer.mailer.transport.welp_mailjet:
    class: Welp\MailjetSwiftMailer\SwiftMailer\MailjetTransport
    arguments:
        - "@swiftmailer.transport.eventdispatcher.welp_mailjet"
        - "%welp_mailjet.api_key%"
        - "%welp_mailjet.secret_key%"
```

Note: We set `welp_mailjet.api_key` and `welp_mailjet.secret_key` into parameters.yml

3. Finally, configure SwiftMailer in your `config.yml`:

```yaml
# Swiftmailer Configuration
swiftmailer:
    transport: welp_mailjet
```

## Execute Tests

    vendor/bin/phpunit -c .

## Contributing

If you want to contribute to this project, look at [over here](CONTRIBUTING.md)
