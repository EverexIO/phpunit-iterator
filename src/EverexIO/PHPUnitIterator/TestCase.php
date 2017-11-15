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
    }

    /**
     * Processes a rule and calls an assertion
     */
    protected function processTestRule($result, $assert)
    {
        if(empty($assert['fields'])) return;
        $type = isset($assert['type']) ? $assert['type'] : false;
        $isNot = (0 === strpos($type, '!'));
        if($isNot) {
            $type = substr($type, 1);
        }
        foreach($fields as $field){
            $this->processAssertType($type, $isNot, $field, $result);
        }
    }

    /**
     * Runs assertion
     */
    protected function processAssertType($type, $isNot, $field, $result) {
        switch($type) {
            case 'isset':
                 // Important: field can be in form of "f1:f2:f3" for multilevel arrays
                 $res = $isNot ? !isset($result[$field]) : isset($result[$field]);
                 $this->assertTrue($res, sprintf("isset assert failed for field %s", $field));
                 break;
            case 'empty':
                 break;
            default:
                 // assertEqual
        }
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
