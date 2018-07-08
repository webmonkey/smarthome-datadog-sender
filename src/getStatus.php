<?php

require(__DIR__ ."/../vendor/autoload.php");

$sessionId = file_get_contents(__DIR__ ."/../config/session");
if (empty($sessionId)) {
    die("No Session ID found". PHP_EOL);
}

$api = new RestClient([
    "base_url" => "https://api-prod.bgchprod.info:443/omnia",
    "headers" => [
        "Content-Type" => "application/vnd.alertme.zoo-6.1+json",
        "Accept" => "application/vnd.alertme.zoo-6.1+json",
        "X-Omnia-Client" => "Hive Web Dashboard",
        "X-Omnia-Access-Token" => $sessionId 
    ]
]);

$response = $api->get("nodes");

$state = array();

if (200 == $response->info->http_code) {
    $a = json_decode($response->response);


    foreach ($a->nodes as $node) {

        if ($node->nodeType == "http://alertme.com/schema/json/node.class.light.json#") {
            if (!isset($state['lights'])) {
                $state['lights'] = array();
            }
            $state['lights'][$node->name]['state'] = $node->attributes->state->reportedValue;

            if ("ON" == $state['lights'][$node->name]['state']) {
                $state['lights'][$node->name]['brightness'] = $node->attributes->brightness->reportedValue;
            } else {
                $state['lights'][$node->name]['brightness'] = 0;
            }
        }

        if (isset($node->attributes->stateHotWaterRelay->reportedValue)) {
            $state['hotWater'] = $node->attributes->stateHotWaterRelay->reportedValue;
        }
        if (isset($node->attributes->stateHeatingRelay->reportedValue)) {
            $state['heating'] = $node->attributes->stateHeatingRelay->reportedValue;
        }
    }
}

print_r($state);
