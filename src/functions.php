<?php
define('FUNCTIONS_PATH', dirname(__FILE__).'/functions');
/*
Global functions in slim-common.
*/
require(FUNCTIONS_PATH.'/config.php');
require(FUNCTIONS_PATH.'/memcached.php');
require(FUNCTIONS_PATH.'/db.php');