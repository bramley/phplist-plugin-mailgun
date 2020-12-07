<?php

namespace phpList\plugin\Mailgun;

class WebhookHandler
{
    public function run()
    {
        ob_end_clean();
        $logger = \phpList\plugin\Common\Logger::instance();
        $postData = file_get_contents('php://input');

        if ($postData === false) {
            logEvent('Mailgun webhook error getting input');

            return false;
        }
        $data = json_decode($postData);

        if ($data === null) {
            logEvent('Mailgun webhook error json decoding');
            $logger->debug(print_r($postData, true));

            return false;
        }
        $logger->debug(print_r($data, true));

        if (!property_exists($data, 'signature') || !property_exists($data, 'event-data')) {
            logEvent('Mailgun webhook error in the data');

            return false;
        }

        if (abs(time() - $data->signature->timestamp) > 15) {
            logEvent('Mailgun webhook timestamp is too old');

            return false;
        }
        $computedSignature = hash_hmac(
            'sha256',
            $data->signature->timestamp . $data->signature->token,
            getConfig('mailgun_webhook_key')
        );

        if (!hash_equals($computedSignature, $data->signature->signature)) {
            logEvent('Mailgun webhook incorrect signature');

            return false;
        }
        $eventData = $data->{'event-data'};
        logEvent(sprintf('Mailgun event : "%s", %s', $eventData->event, $eventData->recipient));

        switch ($eventData->event) {
            case 'failed':
                $reason = $eventData->{'delivery-status'}->description;
                addUserToBlackList($eventData->recipient, "Mailgun bounce : $reason");
                break;
            case 'unsubscribed':
                addUserToBlackList($eventData->recipient, 'Mailgun unsubscription');
                break;
            case 'complained':
                addUserToBlackList($eventData->recipient, 'Mailgun spam complaint');
                break;
        }

        return true;
    }
}
