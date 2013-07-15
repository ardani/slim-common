<?php
/**
 * Middleware that handles encoding API results to JSON
 * and setting all necessary headers.
 */
class ApiMiddleware extends \Slim\Middleware {
  private static $result;
  private static $disabled = true;
  
  static function result($result) {
    self::$result = $result;
  }

  static function disable() {
    self::$disabled = true;
  }

  static function enable() {
    self::$disabled = false;
  }

  function call() {
    $app = $this->app;
    $res = $app->response();

    try {
      $this->next->call();
      if (self::$disabled) {
        return;
      }
      if ($res->status() !== 200) {
        return;
      }
      $res['Content-Type'] = 'application/json';
      $result = self::prepForEncoding(self::$result);
      $res->status(200);
    } catch (Exception $e) {
      if ($e instanceof PDOException) {
        $log = $app->getLog();
        foreach(ORM::get_query_log() as $entry) {
          $log->debug($entry);
        }
      }
      $res['Content-Type'] = 'application/json';
      $result = array('error' => $e->getMessage());
      $log = $app->getLog();
      $log->debug($e->getTraceAsString());
      if ($e instanceof AccessException) {
        $res->status($e->getCode());
      } else {
        $res->status(500);
      }
    }

    // encode
    $res->write(defined('JSON_PRETTY_PRINT') ? json_encode($result, JSON_PRETTY_PRINT) : json_encode($result));
  }

  private function prepForEncoding($r) {
    if ($r instanceof SlimModel) {
      return $r->encode();
    } else if ($r instanceof Model) {
      return $r->as_array();
    } else if ($r instanceof ORM) {
      return $r->as_array();
    } else if (is_array($r)) {
      $array = array();
      foreach($r as $i => $value) {
        $array[$i] = self::prepForEncoding($value);
      }
      return $array;
    } else {
      return $r;
    }
  }
}

$app->add(new ApiMiddleware());

// Setup generic model access: GET, POST, PUT and DELETE
$app->map('/api/:model(/:id(/:function)?)?', function($model, $id = false, $function = false) use ($app) {
  ApiMiddleware::enable();

  $req = $app->request();
  
  if ($id !== false && !is_numeric($id)) {
    $function = $id;
    $id = false;
  }

  if ($req->isGet() && !$id && !$function) {
    $function = 'list';
  }
  
  if (!class_exists($model)) {
    throw new Exception(sprintf('%s does not exist', ucwords($model)), 404);
  }

  if ($id !== false) {
    if (!has_session()) {
      throw new AccessException();
    }
    $instance = call_user_func_array(array(ucwords($model), 'getBy'), array('id', $id));
    if (!$instance) {
      throw new Exception(sprintf("%s does not exist for [%d]", ucwords($model), $id), 404);
    }
    if (!empty($instance->user_id) && $instance->user_id !== current_user()->id && !current_user_can('super')) {
      throw new AccessException();
    }
  } else {
    $instance = Model::factory(ucwords($model))->create();
  }
  
  if ($req->isGet()) {
    if (!$function) {
      return ApiMiddleware::result( $instance );  
    } 
  }

  // allow for JSON input or $_POST
  $input = $app->request()->headers('CONTENT-TYPE') === 'application/json' ? json_decode(file_get_contents('php://input')) : $app->request()->params();

  if (has_session()) {
  
    if ($req->isPut() || $req->isPost()) {
      if (!$function) {
        return ApiMiddleware::result( call_user_func_array(array($instance, 'apply'), array($input)) );
      }
    }

    if ($req->isDelete()) {
      if ($id !== false) {
        return ApiMiddleware::result( array('success' => $instance->delete() ) );
      }
    }

  }

  if ($function) {
    $class = get_class($instance);
    $callable = array($instance, '_'.$function);
    if (!is_callable($callable)) {
      throw new Exception("Invalid method {$class}::_{$function}");
    }
    return ApiMiddleware::result( call_user_func_array($callable, array($input, $app)) );
  }

})->via('GET', 'POST', 'PUT', 'DELETE');

// Setup documentation URL
$app->get('/api', function() use ($app) {
  ApiMiddleware::disable();
  $app->render('api.php', array('pageTitle' => 'API Documentation'));
});