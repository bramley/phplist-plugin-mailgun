<?php

$webhook = new phpList\plugin\Mailgun\WebhookHandler();
$result = $webhook->run();
http_response_code($result ? 200 : 406);

exit();
