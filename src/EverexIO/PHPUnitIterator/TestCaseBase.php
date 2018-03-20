<?php

namespace EverexIO\PHPUnitIterator;

use PHPUnit_Framework_TestCase;

if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION > 5) 
{   // PHP 7
    class TestCaseBase extends \PHPUnit\Framework\TestCase {}
} 
else 
{   // PHP 5
    class TestCaseBase extends PHPUnit_Framework_TestCase {}
}