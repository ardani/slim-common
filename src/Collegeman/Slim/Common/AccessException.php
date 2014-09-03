<?php
namespace Collegeman\Slim\Common;

class AccessException extends \Exception {

  function __construct($message = null, $code = null) {
    if (is_null($message)) {
      $message = 'Forbidden';
    }
    if (is_null($code)) {
      $code = 403;
    }
    parent::__construct($message, $code);
  }

}
