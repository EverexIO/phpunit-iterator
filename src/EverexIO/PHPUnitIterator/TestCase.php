<?php

namespace EverexIO\PHPUnitIterator;

/**
 *
 */
class TestCase extends \PHPUnit\Framework\TestCase
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
        $result = $this->sendRequest($test['method'], $test['URL_params'], $test['GET_params']);
        $this->logToConsole('check returned answer...');
        $this->assertTrue(
            is_array($result),
            sprintf("Invalid response received:\n%s", var_export($result, TRUE))
        );
        foreach($test['asserts'] as $assert){
            $this->processTestRule($result, $assert);
        }
    }

    /**
     * Processes a rule and calls an assertion
     */
    protected function processTestRule($result, $assert)
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
                $this->processAssertType($type, $isNot, $field, $result, $assert, $equal);
            }
        else
        {
            $this->processAssertType($type, $isNot, $fields, $result, $assert, $equal);
        }
    }

    /**
     * Runs assertion
     */
    protected function processAssertType($type, $isNot, $field, $result, $assert, $equal = null) {
        $value = $this->getArrayValueFromString($result, $field);
        switch($type) {
            case 'isset':
                $val = isset($assert['array']) ? $result[0][$field] : $value;
                // Important: field can be in form of "f1:f2:f3" for multilevel arrays
                $this->logToConsole('check field "' . $field . '"');
                $res = $isNot ? !isset($val) : isset($val);
                $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
                break;
            case 'empty':
                $this->logToConsole('check field "' . $field . '" is not empty');
                $res = $isNot ? !empty($value) : empty($value);
                $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
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
            default:
                $val = isset($assert['array']) ? $result[0][$field] : $value;
                $this->logToConsole('check "' . $field . '" equals "' . $equal . '"');
                $this->assertEquals(strtolower($val), strtolower($equal), "fields are not equal");
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
