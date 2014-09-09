<?php
/*
Bootstrap your basic Slim web app.
*/

@include('../config.php');

if (!config('auth.salt')) {
  throw new Exception("Please set auth.salt config var to a strong key");
}

date_default_timezone_set(config('timezone', ini_get('date.timezone') ? ini_get('date.timezone') : 'UTC'));

// connect to the database
ORM::configure('mysql:host='.config('db1.host').';dbname='.config('db1.name').';port='.config('db1.port'));
ORM::configure('username', config('db1.user'));
ORM::configure('password', config('db1.pass'));
ORM::configure('logging', config('log.level', 0) >= 4);

// startup Slim
$app = new \Slim\Slim(array(
  'templates.path' => '../templates'
));

// Create monolog logger and store logger in container as singleton 
// (Singleton resources retrieve the same log resource definition each time)
$app->container->singleton('log', function () {
  $log = new \Monolog\Logger('slim-common');
  $log->pushHandler(new \Monolog\Handler\StreamHandler('../logs/app.log', 
    // TODO: set this by config
    \Monolog\Logger::DEBUG));
  return $log;
});

// Prepare view
$app->view(new \Slim\Views\Twig());
$app->view->parserOptions = array(
  'charset' => 'utf-8',
  'cache' => realpath('../templates/cache'),
  'auto_reload' => true,
  'strict_variables' => false,
  'autoescape' => true
);

$app->view->parserExtensions = array(
  new \Slim\Views\TwigExtension(),
  new \Collegeman\Slim\Common\TwigExtension()
);

return $app;