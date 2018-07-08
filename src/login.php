<?php

require(__DIR__ ."/../vendor/autoload.php");

$username = getenv('HIVE_USERNAME');
$password = getenv('HIVE_PASSWORD');

if (empty($username) || empty($password)) {
    die("Please specify HIVE_USERNAME and HIVE_PASSWORD". PHP_EOL);
}


$api = new RestClient([
    "base_url" => "https://api-prod.bgchprod.info:443/omnia",
    "headers" => [
        "Content-Type" => "application/vnd.alertme.zoo-6.1+json",
        "Accept" => "application/vnd.alertme.zoo-6.1+json",
        "X-Omnia-Client" => "Hive Web Dashboard"
    ]
]);

$response = $api->post("auth/sessions",
    json_encode(["sessions" => [[
        "username" => $username,
        "password" => $password,
        "caller" => "WEB"]
    ]])
);

if (200 == $response->info->http_code) {
    $a = json_decode($response->response);
    echo $a->sessions[0]->id . PHP_EOL;
}
