<?php

namespace EverexIO\PHPUnitIterator;

use PHPUnit_Framework_TestCase;

// class TestCase extends \PHPUnit\Framework\TestCase
class TestCase extends PHPUnit_Framework_TestCase
{
    // @todo: move away from this class
    protected $url = '';

    /**
     * This function can run a single test with different parameters (_iterate)
     */
    protected function _iterateTest($test)
    {
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
    protected function logToConsole($text)
    {
       printf($text . "\r\n");
    }
}
