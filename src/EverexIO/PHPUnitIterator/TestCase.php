<?php

namespace EverexIO\PHPUnitIterator;
use DOMDocument;
use PHPUnit_Framework_TestCase;

// class TestCase extends \PHPUnit\Framework\TestCase
class TestCase extends PHPUnit_Framework_TestCase
{
    // @todo: move away from this class
    protected $url = '';
    protected $everex_url = '';
    /**
     * This function can run a single test with different parameters (_iterate)
     */
    protected function _iterateTest($test)
    {
        if(!empty($test['type']) && $test['type'] == 'everex')
        {
            $this->_iterateSingleEverexTest($test);
            return;
        }

        if(empty($test['asserts'])){
            $this->logToConsole("No assert rules found for test!");
            return;
        }

        if(!empty($test['_iterate'])){
            foreach($test['_iterate'] as $field => $values) {
                foreach($values as $value) {
                    // Update $test[$field] value to $value
                    $test = $this->updateArrayValue($test, $field, $value);
                    $this->_iterateSingleTest($test);
                }
            }
            return;
        }

        $this->_iterateSingleTest($test);
    }

    protected function _iterateSingleEverexTest($test)
    {
        $this->logToConsole('=== TESTING ' . $test['method'] . ' ===');

        if(!empty($test['description']))
        {
            $this->logToConsole('=== INFO ' . $test['description'] . ' ===');
        }

        foreach ($test['compareTo'] as $cur)
        {
            $params = array($test['compareFrom'], $cur);
            $result = $this->sendEverexRequest($test['method'], $params);
            $dataset = $params[0].$params[1];
            if (isset($test['compareReplace']))
                $dataset = $test['compareReplace'];
            $this->processComparing($result, $dataset, $test);
        }
    }

    protected function processComparing($result, $dataset, $test)
    {
        $type = $test['compareSource'];
        switch ($type)
        {
            case 'quandl':
                $compareData = $this->getDataFromQuandl($test['compareSourceParam'], $dataset);
                $this->compareHistoricData('quandl', $result['result'], $compareData['data']);
                break;
            case 'coinmarketcaphtml':
                $compareData = $this->getDataFromCoinMarketCap('html', $test['compareSourceParam']);
                $this->compareHistoricData('coinmarketcap', $result['result'], $compareData);
                break;
            case 'coinmarketcapapi':
                $compareData = $this->getDataFromCoinMarketCap('api', $test['compareSourceParam']);
                $this->compareCurrentData('coinmarketcap', $result['result'], $compareData, $test['compareTags']);
                break;
            case 'openexchangerates':
                $compareData = $this->getDataFromOpenExchangeRates();
                $tags = isset($test['compareTags']) ? $test['compareTags'] : array();
                $this->compareCurrentData('openexchangerates', $result['result'], $compareData['rates'], $tags);
                break;
            case 'bitstamp':
                $compareData = $this->getDataFromBitstamp();
                $this->compareCurrentData('bitstamp', $result['result'], $compareData);
                break;
        }
    }

    protected function compareCurrentData($type, $everexData, $compareData, $compareTags = array()){
        switch ($type)
        {
            case 'coinmarketcap':
                foreach ($compareTags as $tags)
                {
                    $percentChange = (1 - $everexData[$tags[0]] / $compareData[$tags[1]]) * 100;
                    $this->assertTrue($percentChange <= 10, "Percentage difference bigger than 10%");
                }
                break;

            case 'openexchangerates':
                $key = !empty($compareTags) ? $compareTags[0] : $everexData['code_to'];
                $percentChange = (1 - $everexData['rate'] / $compareData[$key]) * 100;
                $this->assertTrue($percentChange <= 10, "Percentage difference bigger than 10%");
                break;

            case 'bitstamp':
                foreach ($everexData as $key => $val)
                {
                    $percentChange = (1 - $val / $compareData[$key]) * 100;
                    $this->assertTrue($percentChange <= 10, "Percentage difference bigger than 10%");
                }
                break;
        }
    }

    protected function compareHistoricData($type, $everexData, $compareData)
    {
        $rand_keys = array_rand($everexData, 3);
        $flag = false;
        foreach ($rand_keys as $key){
            $item = $everexData[$key];
            $date = $item['date'];
            $comparingItem = null;
            foreach($compareData as $data) {
                if ($date == $data[0]) {
                    $flag = true;
                    $comparingItem = $data;
                    break;
                }
            }
            $compareFlag = false;

            if (is_null($comparingItem)) continue;

            foreach ($comparingItem as $value){
                if (is_string($value)) continue;
                switch ($type)
                {
                    case 'quandl':
                        if ($value == $item['rate'])
                            $compareFlag = true;
                        break;

                    case 'coinmarketcap':
                        foreach ($item as $val)
                        {
                            if ($value == $val)
                                $compareFlag = true;
                        }
                        break;
                }
            }
            $this->assertTrue($compareFlag, "Can't found equal data from $type");
        }
        $this->assertTrue($flag, "Can't find equal data");
    }

    protected function getDataFromBitstamp()
    {
        $url = 'http://www.bitstamp.net/api/ticker/';
        $json = file_get_contents($url);
        //using hardcoded data for test, because bitstamp.net is blocked by RKN
        //$json = '{"high": "11175.00000000", "last": "10874.70", "timestamp": "1519990390", "bid": "10862.20", "vwap": "10917.79", "volume": "10639.14399544", "low": "10612.07000000", "ask": "10874.99", "open": 10917.37}';
        $result = json_decode($json, TRUE);
        return $result;
    }

    protected function getDataFromOpenExchangeRates()
    {
        $apiKey = '56373b75d3204d008efa8b62e0589743';
        $url = 'https://openexchangerates.org/api/latest.json?app_id='.$apiKey;
        $json = file_get_contents($url);
        $result = json_decode($json, TRUE);
        return $result;
    }

    protected function getDataFromQuandl($database, $dataset){
        $apiKey = 'SS1Kj9CAzyj9bGssEQz9';
        $url = 'https://www.quandl.com/api/v1/datasets/'.$database.
            '/'.$dataset.'.json?auth_token='.$apiKey.'&trim_start=2015-04-01';
        $json = file_get_contents($url);
        $result = json_decode($json, TRUE);
        return $result;
    }

    protected function getDataFromCoinMarketCap($type, $currency){
        switch ($type)
        {
            case 'html':
                $url = 'https://coinmarketcap.com/currencies/'.$currency.'/historical-data/?start=20130428&end=20180122';
                $html = file_get_contents($url);
                $DOM = new DOMDocument;
                libxml_use_internal_errors(true);
                $DOM->loadHTML($html);
                libxml_clear_errors();
                $result = array();
                $data = $DOM->getElementsByTagName('tr');
                foreach($data as $key => $node)
                {   //first node is header node, so we skip it
                    if ($key == 0) continue;
                    $nodeValue = $node->nodeValue;
                    $values = explode("\n", $nodeValue);
                    $values = $this->remove_empty($values);
                    $array_elem = array();
                    foreach ($values as $innerkey => $val)
                    {
                        if ($innerkey == 1){
                            $val = date('Y-m-d', strtotime($val));
                        } else {
                            $val = str_replace( ',', '', $val );
                            $val = floatval($val);
                        }
                        array_push($array_elem, $val);
                    }
                    array_push($result, $array_elem);
                }
                return $result;
                break;
            case 'api':
                $url = 'https://api.coinmarketcap.com/v1/ticker/?convert=USD&limit=0';
                $data = json_decode(file_get_contents($url), true);
                foreach ($data as $item)
                {
                    if ($item['id'] == $currency)
                        return $item;
                }
                break;
        }
    }

    function remove_empty($array) {
        return array_filter($array, function($value) {
            return !empty($value) || $value === 0;
        });
    }

    /**
     * This function runs a single test
     */
    protected function _iterateSingleTest($test)
    {
        $this->logToConsole('=== TESTING ' . $test['method'] . ' ===');
        if(!empty($test['description']))
        {
            $this->logToConsole($test['description']);
        }
        // assert that result is array inside this method
        $test['URL_params'] = isset($test['URL_params']) ? $test['URL_params'] : [];
        if ($test['method'] == 'getBlockTransactions' && isset($test['GET_params']['block']) && $test['GET_params']['block'] == 'last')
        {
            $result = $this->sendRequest('getLastBlock', $test['URL_params'], $test['GET_params']);
            $last = $result['lastBlock'];
            $test['GET_params']['block'] = $last;
        }
        $result = $this->sendRequest($test['method'], $test['URL_params'], $test['GET_params']);
        $this->logToConsole('check returned answer...');
        $this->assertTrue(
            is_array($result),
            sprintf("Invalid response received:\n%s", var_export($result, TRUE))
        );
        foreach($test['asserts'] as $assert){
            $this->processTestRule($result, $assert, $test);
        }
    }

    /**
     * Processes a rule and calls an assertion
     */
    protected function processTestRule($result, $assert, $test)
    {
        if(empty($assert['fields'])) return;
        $fields = $assert['fields'];
        $equal = isset($assert['equals']) ? $assert['equals'] : null;
        $type = isset($assert['type']) ? $assert['type'] : false;
        $isNot = (0 === strpos($type, '!'));
        if($isNot) {
            $type = substr($type, 1);
        }
        if (is_array($fields))
            foreach($fields as $field){
                $this->processAssertType($type, $isNot, $field, $result, $assert, $test, $equal);
            }
        else
        {
            $this->processAssertType($type, $isNot, $fields, $result, $assert, $test, $equal);
        }
    }

    /**
     * Runs assertion
     */
    protected function processAssertType($type, $isNot, $field, $result, $assert, $test, $equal = null) {
        $value = $this->getArrayValueFromString($result, $field);
        switch($type) {
            case 'isset':
                if (isset($assert['array']))
                {
                    $count = isset($assert['count']) ? $assert['count'] : count($result);
                    $this->logToConsole('check field "' . $field . '"');
                    for ($i = 0; $i < $count; $i++)
                    {
                        $val = $result[$i][$field];
                        $res = $isNot ? !isset($val) : isset($val);
                        $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
                    }
                }
                else
                {
                    $val = isset($assert['array']) ? $result[0][$field] : $value;
                    // Important: field can be in form of "f1:f2:f3" for multilevel arrays
                    $this->logToConsole('check field "' . $field . '"');
                    $res = $isNot ? !isset($val) : isset($val);
                    $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
                }
                break;
            case 'empty':
                $this->logToConsole('check field "' . $field . '" is not empty');
                $res = $isNot ? !empty($value) : empty($value);
                $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
                break;
            case 'contain':
                $this->logToConsole('check if array contains '. $field . ' with value '. $equal );
                $contain = false;
                if (isset($assert['array']))
                {
                    $count = isset($assert['count']) ? $assert['count'] : count($result) - 1;
                    for ($i = 0; $i < $count; $i++)
                    {
                        $val = $result[$i][$field];
                        if ($val == $equal) $contain = true;
                    }
                    $this->checkContains($isNot, $contain, $field, $equal);
                }
                else
                {
                    $val = $result[$field];
                    if ($val == $equal) $contain = true;
                    $this->checkContains($isNot, $contain, $field, $equal);
                }
                break;
            case 'count':
                $gt =  isset($assert['gt']) ? $assert['gt'] : null;
                $lt =  isset($assert['lt']) ? $assert['lt'] : null;
                $range =  isset($assert['range']) ? $assert['range'] : null;
                $cnt = isset($assert['array']) ? count($result) : count($result[$field]);
                if (!is_null($gt))
                {
                    $this->logToConsole('check count greater than ' . $gt);
                    $this->assertTrue($cnt > $gt, "count less than ".$gt);
                }
                else if (!is_null($lt))
                {
                    $this->logToConsole('check count less than ' . $lt);
                    $this->assertTrue($cnt < $lt, "count greater than ".$lt);
                }
                else if (!is_null($range))
                {
                    $this->logToConsole('check count greater than ' . $range[0].' and less than ' . $range[1]);
                    $this->assertTrue($cnt >= $range[0] && $cnt <= $range[1], "count less than ".$lt);
                }
                else
                {
                    $this->logToConsole('check count equals ' . $equal);
                    $this->assertEquals($equal, $cnt, "fields are not equal");
                }
                break;
            case 'compareArrays':
                $count = isset($assert['count']) ? $assert['count'] : count($result);
                for ($i = 0; $i < $count; $i++)
                {
                    $callback = isset($assert['callback']) ? $assert['callback'] : false;
                    if(is_callable($callback)){
                        $expected = $callback($result[$i]['hash']);
                    }
                    $fields = !empty($assert['fields']) ? $assert['fields'] : array_keys($expected);
                    foreach($fields as $field){
                        $this->assertEquals($result[$i][$field], $expected[$field], "fields are not equal");
                    }
                }
                break;
            case 'timeCheck':
                $oldvalue = $result['lastBlock'];
                $this->logToConsole('waiting ' . $field . ' seconds');
                sleep($field);
                $result = $this->sendRequest($test['method'], $test['URL_params'], $test['GET_params']);
                $newValue = $result["lastBlock"];
                $this->logToConsole('check if block has changed');
                $this->assertNotEquals($oldvalue, $newValue, "Last block doesn't changed");
                break;
            case 'checkLastBlock':
                $this->logToConsole('check last block timestamp difference');
                $lastOperation = end($result['operations']);
                $lastBlock = $this->sendRequest('getLastBlock', $test['URL_params'], $test['GET_params']);
                $lastBlockValue = $lastBlock["lastBlock"];
                $test['GET_params']['block'] = $lastBlockValue;
                $blockTransactions = $this->sendRequest('getBlockTransactions', $test['URL_params'], $test['GET_params']);
                foreach ($blockTransactions as $block)
                {
                    $interval = abs($block['timestamp'] - $lastOperation['timestamp']);
                    $this->assertLessThan($assert['time'], $interval, sprintf("timestamp difference bigger than %s", $assert['time']));
                }
                break;
            default:
                $val = isset($assert['array']) ? $result[0][$field] : $value;
                $this->logToConsole('check "' . $field . '" equals "' . $equal . '"');
                $this->assertEquals(strtolower($val), strtolower($equal), "fields are not equal");
                break;
        }
    }

    protected function checkContains($isNot, $contain, $field, $equal)
    {
        $res = !$isNot;
        if (!$res)
        {
            $this->assertFalse($contain,sprintf("array contains %s equals %s", $field, $equal));
        }
        else
        {
            $this->assertTrue($contain, sprintf("can't find field '%s' equals %s in array", $field, $equal));
        }
    }

    /**
     * Returns value from object. Example - tokens:int(0):tokenInfo:address
     */
    protected function getArrayValueFromString($array, $str){
        $fields = explode(':', $str);
        $obj = $array;
        foreach ($fields as $field){
            $field = $this->checkIndex($field);
            if (isset($obj[$field]))
                $obj = $obj[$field];
            else return null;
        }
        return $obj;
    }

    protected function checkIndex($key)
    {
        if (strpos($key, 'int') !== false)
        {
            return intval(preg_replace('/[^0-9]+/', '', $key), 10);
        }
        else return $key;
    }

    /**
     * Function can update array value in multilevel array using "key1:key2:...:keyN" field name
     */
    protected function updateArrayValue($array, $field, $value) {
        $fields = explode(':', $field);
        $tmp = &$array;
        foreach($fields as $idx => $field) { 
            if($idx == (count($fields) - 1)) {
                $tmp[$field] = $value;
            } else if(is_array($tmp[$field])) {
                $tmp = &$tmp[$field];
            } else break;
        }
        return $array;
    }

    // @todo: move away from this class
    protected function sendRequest($method, $object = '', array $parameters = array())
    {
        $url = $this->url . $method;
        if($object){
            $url = $url . '/' . $object;
        }
        if(!empty($parameters)){
            $url = $url . '?' . http_build_query($parameters);
        }
        $json = file_get_contents($url);
        $aResult = json_decode($json, TRUE);
        return $aResult;
    }

    // @todo: move away from this class
    protected function sendEverexRequest($method, array $parameters = array())
    {
        $url = 'http://rates.everex.io';

        /*{ "jsonrpc": "2.0", "method": "getCurrencyHistory", "params": ["USD", "THB"], "id": 0 }*/

        $data = array(
            'jsonrpc'     => '2.0',
            'id'       => 1,
            'method'    => $method,
            'params' => $parameters
        );

        $json = json_encode($data);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $json
            )
        );
        $context  = stream_context_create($options);
        $json = file_get_contents($url, false, $context);
        $aResult = json_decode($json, TRUE);
        return $aResult;
    }

    // @todo: move away from this class
    protected function logToConsole($text)
    {
       printf($text . "\r\n");
    }
}
