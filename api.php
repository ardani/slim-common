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

  private static $reflections = array();

  /**
   * Look for the presence of @public in the doc comment of the method given.
   * @param String The class to test
   * @param String The method to test
   * @return true if the method has @public, otherwise false
   * @throws Exception if method does not exist
   */
  static function isCallable($class, $method) {
    if (empty(self::$reflections[$class])) {
      self::$reflections[$class] = array(
        'class' => new ReflectionClass($class)
      );
    }
    if (empty(self::$reflections[$class]['methods'][$method])) {
      try {
        self::$reflections[$class]['methods'][$method] = self::$reflections[$class]['class']->getMethod($method);
      } catch (Exception $e) {
        self::$reflections[$class]['methods'][$method] = false;
      }
    }
    if (self::$reflections[$class]['methods'][$method] === false) {
      throw new Exception("Method does not exist: {$class}::{$method}");
    }
    if (empty(self::$reflections[$class]['doc'][$method])) {
      self::$reflections[$class]['doc'][$method] = self::$reflections[$class]['methods'][$method]->getDocComment();
    }
    return strpos(self::$reflections[$class]['doc'][$method], '@public') !== false;
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
    $function = 'search';
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

  if ($instance instanceof PrivateModel) {
    throw new Exception("This model is not public");
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
    if (ApiMiddleware::isCallable($class, $function)) {
      return ApiMiddleware::result( call_user_func_array(array($class, $function), array($input, $app)) );
    } else {
      throw new Exception("Method is private {$class}::{$function}");  
    }
  }

})->via('GET', 'POST', 'PUT', 'DELETE');

// Setup documentation URL
$app->get('/api', function() use ($app) {
  ApiMiddleware::disable();
  $app->render('api.php', array('pageTitle' => 'API Documentation'));
});