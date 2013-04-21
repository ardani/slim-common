<?php
// Load configuration file, affects local only:
@include(ROOT.'/config.php');
// Load Slim
require(ROOT.'/common/Slim/Slim.php');
\Slim\Slim::registerAutoloader();
// Initialize Slim:
$app = new \Slim\Slim(array(
// Template path: default is /templates in the Root
'templates.path' => ROOT.'/templates',
// Logging: default is enabled; but log level controlled by LOG_LEVEL constant
'log.enabled' => true
));  
// Load config management library:
require(ROOT.'/common/config.php');
// Set logging level:
$log = $app->getLog();
$log->setLevel(config('log.level', 0));
// Load default libraries:
require(ROOT.'/common/memcached.php');
require(ROOT.'/common/db.php');
require(ROOT.'/common/session.php');