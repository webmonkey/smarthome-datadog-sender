<?php

require(__DIR__ ."/../vendor/autoload.php");

if (false == ini_get("date.timezone")) {
    ini_set("date.timezone", "UTC");
}

$configFile = __DIR__ ."/../config/config.json";

$runner = new Runner($configFile);
$runner->prepare();
while(true) {
    $runner->sendMetrics();
    sleep(60);
}
$runner->end();



class Runner {

    private $_config;
    private $_heartBeatFile = "/var/run/smarthome-datadog-sender";

    public function __construct($configFile)
    {
        $this->_config = new Config($configFile);
    }

    public function prepare()
    {
        // make sure we have the basic config available
        $this->_log("Reading Config");
        if (! $this->_config->read()) {
            $this->_log("No Config Exists");
            $this->_getConfigValuesFromUser();
        }

        if (empty($this->_config->hiveSessionId)) {
            $this->_refreshHiveSession();
        }
    }

    public function sendMetrics()
    {
        $hiveFactory = new HiveApiFactory();
        $hive = $hiveFactory->getHiveApi($this->_config->hiveSessionId);

        $this->_log("Fetching Hive data");
        $response = $hive->get("nodes");
        if (401 == $response->info->http_code) {
            $this->_log("Initial Hive data fetch failed");
            $this->_refreshHiveSession();
            $this->_log("Fetching Hive data again");
            $response = $hive->get("nodes");
        }


        if (200 != $response->info->http_code) {
            $this->_log("Failed to fetch Hive node data");
            $this->_log(print_r($response->response,true));
        } else {
            $a = json_decode($response->response);

            $metrics = array();
            foreach ($a->nodes as $node) {

                if ($node->attributes->nodeType->reportedValue == "http://alertme.com/schema/json/node.class.light.json#") {
                    $lightStatus = $node->attributes->state->reportedValue;
                    $lightName = strtolower($node->name);

                    if ("ON" == $lightStatus) {
                        $brightness = $node->attributes->brightness->reportedValue;
                    } else {
                        $brightness = 0;
                    }
                    $metrics["hive.light.$lightName.brightness"] = $brightness; 
                }

                if (isset($node->attributes->stateHotWaterRelay->reportedValue)) {
                    $metrics['hive.hotWaterRelay'] = $node->attributes->stateHotWaterRelay->reportedValue == "ON" ? 1 : 0;
                }
                if (isset($node->attributes->stateHeatingRelay->reportedValue)) {
                    $metrics['hive.heatingRelay'] = $node->attributes->stateHeatingRelay->reportedValue == "ON" ? 1 : 0;
                }

                if (isset($node->attributes->temperature->reportedValue)) {
                    $metrics['hive.temperature'] = $node->attributes->temperature->reportedValue;
                }
                if (isset($node->attributes->targetHeatTemperature->reportedValue)) {
                    $metrics['hive.targetTemperature'] = $node->attributes->targetHeatTemperature->reportedValue;
                }
            }
        }

        $weatherMap = new OpenWeatherMap($this->_config->openWeatherMapApiKey);
        $metrics["openWeatherMap.temperature"] = $weatherMap->getTemperature($this->_config->cityName);

        $hiveWeather = new HiveWeather();
        $metrics["hive.outsideTemperature"] = $hiveWeather->getTemperature($this->_config->countryCode, $this->_config->postCode);

        if (count($metrics) > 0) {
            $this->_log("Sending data for ". count($metrics) ." metrics to Datadog");
            $this->_log(json_encode($metrics));
            $dataDog = new DatadogHelper($this->_config->datadogApiKey);
            $dataDog->sendMetrics($metrics);

            $this->_log("Touching $this->_heartBeatFile");
            touch($this->_heartBeatFile);
        }
    }

    public function end()
    {
        $this->_config->write();
        $this->_log("Exiting...");
    }

    private function _getConfigValuesFromUser()
    {
        echo "No config exists. Please provide config values". PHP_EOL . PHP_EOL;
        $this->_config->hiveUsername = readLine("Hive username: ");
        $this->_config->hivePassword = readLine("Hive password: ");
        $this->_config->datadogApiKey = readLine("Datadog API key: ");
        $this->_config->openWeatherMapApiKey = readLine("OpenWeatherMap API key: ");
        $this->_config->cityName = readLine("OpenWeatherMap City Name: ");
        $this->_config->countryCode = readLine("Country Code: ");
        $this->_config->postCode = readLine("Post Code: ");
        $this->_config->write();
    }

    private function _refreshHiveSession()
    {
        $this->_log("Fetching new Hive Session ID");
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
            $this->_log("Successfully fetched new Hive Session ID");
            $a = json_decode($response->response);
            $this->_config->hiveSessionId = $a->sessions[0]->id;
            $this->_config->write();
        } else {
            $this->_log("Failed to fetch new Hive Session ID");
            $this->_log(print_r($response->response,true));
        }
    }

    private function _log($message)
    {
        $date = date("r");
        file_put_contents("php://stderr", "[$date] $message". PHP_EOL);
    }
}

class HiveWeather
{
    protected $apiURL = "https://weather.prod.bgchprod.info";

    public function __construct()
    {
    }

    public function getTemperature($country, $postCode)
    {
        $api = new RestClient([
            "base_url" => $this->apiURL
        ]);

        $response = $api->get("weather", [
            "country" => $country,
            "postcode" => $postCode 
        ]);

        if (200 == $response->info->http_code) {
            $a = json_decode($response->response);
            return $a->weather->temperature->value;
        }
    }
}

class OpenWeatherMap
{
    protected $apiURL = "api.openweathermap.org/data/2.5";

    private $_apiKey;

    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    public function getTemperature($location)
    {
        $api = new RestClient([
            "base_url" => $this->apiURL
        ]);

        $response = $api->get("weather", [
            "q" => $location,
            "units" => "metric",
            "APPID" => $this->_apiKey
        ]);
        if (200 == $response->info->http_code) {
            $a = json_decode($response->response);
            return $a->main->temp;
        }
    }
}



class HiveApiFactory
{
    public function __constructor() {}

    public function getHiveApi($sessionId="")
    {
        $headers = [
            "Content-Type" => "application/vnd.alertme.zoo-6.1+json",
            "Accept" => "application/vnd.alertme.zoo-6.1+json",
            "X-Omnia-Client" => "Hive Web Dashboard"
        ];

        if (!empty($sessionId)) {
            $headers["X-Omnia-Access-Token"] = $sessionId;
        }

        return new RestClient([
            "base_url" => "https://api-prod.bgchprod.info:443/omnia",        
            "headers" => $headers
        ]);
    }
}

class DatadogHelper
{
    private $_apiKey;
    private $_client;

    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
        $this->_client = new RestClient([
            "base_url" => "https://api.datadoghq.com/api/v1",
            "headers" => [
                "Content-type" => "application/json"
            ]
        ]);
    }

    // k:v array of metrics
    public function sendMetrics($metrics) {

        $series = array();
        foreach ($metrics as $k => $v) {
            $series[] = [
                "metric" => $k,
                "points" => [[time(), $v]],
                "host" => "home"
            ];
        }
        $response = $this->_client->post("series?api_key=". $this->_apiKey, json_encode(["series" => $series]));

        if (202 != $response->info->http_code) {
            print_r($response->response);
        }

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
