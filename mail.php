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
        $view = new \Slim\View();
        $view->appendData($data);
        $view->setTemplatesDirectory($app->config('templates.path'));
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