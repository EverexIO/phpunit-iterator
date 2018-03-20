<?php

namespace EverexIO\PHPUnitIterator;

if (class_exists('\PHPUnit\Framework\TestCase')) {
    class TestCaseBase extends \PHPUnit\Framework\TestCase {}
} 
else if (class_exists('\PHPUnit_Framework_TestCase')) {
    class TestCaseBase extends \PHPUnit_Framework_TestCase {}
}
else {
    die('No suitable TestCase class found, please check PHPUnit version!');
}