<?php
// Load configuration file, affects local only:
@include(ROOT.'/config.php');
// Load Slim
require(ROOT.'/common/Slim/Slim.php');
\Slim\Slim::registerAutoloader();
/**
 * helper autoloader
 */
spl_autoload_register(function($className) {
  $baseDir = __DIR__.DIRECTORY_SEPARATOR;
  $className = ltrim($className, '\\');
  $fileName  = $baseDir;
  $namespace = '';
  if ($lastNsPos = strripos($className, '\\')) {
    $namespace = substr($className, 0, $lastNsPos);
    $className = substr($className, $lastNsPos + 1);
    $fileName  .= str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
  }
  $fileName .= str_replace('_', DIRECTORY_SEPARATOR, strtolower($className)) . '.php';
  // error_log($fileName);
  if (file_exists($fileName)) {
    return require($fileName);
  }
});
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
// Setup the slim.after hook for printing DB log
$app->hook('slim.after', function() use ($app) {
  foreach(ORM::get_query_log() as $entry) {
    $app->getLog()->debug($entry);
  }
});
// Add API functionality
require(ROOT.'/common/api.php');