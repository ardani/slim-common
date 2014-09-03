<?php
class Mail {

  function send($msg = '', $data = array()) {
    global $app;
    $mandrill = new Mandrill(config('mandrill.apikey'));

    if (!is_array($msg)) {
      parse_str($msg, $msg);
    }

    foreach(array('text', 'html') as $t) {
      if (!empty($msg[$t]) && strpos(trim($msg[$t]), 'slim:') === 0) {
        $view = new \Slim\Views\Twig();
        $view->setTemplatesDirectory($app->config('templates.path'));
        $view->parserOptions = array(
          'charset' => 'utf-8',
          'cache' => realpath('../templates/cache'),
          'auto_reload' => true,
          'strict_variables' => false,
          'autoescape' => true
        );
        $view->appendData($data);
        $msg[$t] = $view->fetch('email/'.substr(trim($msg[$t]), 5));
      }
    }

    if (isset($msg['from'])) {
      $msg['from_email'] = $msg['from'];
      unset($msg['from']);
    }

    if (!is_array($msg['to'])) {
      $msg['to'] = array(
        array('email' => $msg['to'])
      );
    }

    return $mandrill->call('/messages/send', array('message' => $msg));
  }

}