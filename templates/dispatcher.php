<?php
$app->get('/info', function() {
  if (current_user_can('super')) {
    phpinfo();
  }
});

$app->get('/server', function() {
  if (current_user_can('super')) {
    echo '<pre>';
    print_r($_SERVER);
  }
});

$app->get('/working', function() {
  echo "Yep. It's working.";
});

$app->run();