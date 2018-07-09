<?php

require(__DIR__ ."/../vendor/autoload.php");

$configFile = __DIR__ ."/../config/config.json";

$runner = new Runner($configFile);
$runner->prepare();
$runner->sendMetrics();


class Runner {

    private $_config;

    public function __construct($configFile)
    {
        $this->_config = new Config($configFile);
    }

    public function prepare()
    {
        // make sure we have the basic config available
        if (! $this->_config->read()) {
            $this->_getConfigValuesFromUser();
            $this->_config->write();
        }
    }

    public function sendMetrics()
    {
    }

    private function _getConfigValuesFromUser()
    {
        echo "No config exists. Please provide config values". PHP_EOL . PHP_EOL;
        $this->_config->hiveUsername = readLine("Hive username: ");
        $this->_config->hivePassword = readLine("Hive password: ");
        $this->_config->datadogApiKey = readLine("Datadog API key: ");
    }

    private function _refreshHiveSession()
    {
        $hiveFactory = new HiveApiFactory();
        $hive = $hiveFactory->getHiveApi();

        $response = $hive->post("auth/sessions",
            json_encode(["sessions" => [[
                "username" => $this->_config->hiveUsername,
                "password" => $this->_config->hivePassword,
                "caller" => "WEB"]
            ]])
        );

        if (200 == $response->info->http_code) {
            $a = json_decode($response->response);
            echo $a->sessions[0]->id . PHP_EOL;
        }
    }
}


class HiveApiFactory
{
    public function __constructor() {}

    public function getHiveApi()
    {
        return new RestClient([
            "base_url" => "https://api-prod.bgchprod.info:443/omnia",        
            "headers" => [
                "Content-Type" => "application/vnd.alertme.zoo-6.1+json",
                "Accept" => "application/vnd.alertme.zoo-6.1+json",
                "X-Omnia-Client" => "Hive Web Dashboard"
            ]
        ]);
    }
}



class Config
{
    private $_file;

    public function __construct($file)
    {
        $this->_file = $file;
    }

    public function read()
    {
        if (!file_exists($this->_file)) {
            return false;
        }

        $configPairs = json_decode(file_get_contents($this->_file,true));
        foreach ($configPairs as $k => $v) {
            $this->$k = $v;
        }
        return true;
    }

    public function write()
    {
        file_put_contents($this->_file, json_encode($this, JSON_PRETTY_PRINT));
    }
}
