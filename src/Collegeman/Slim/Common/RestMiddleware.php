<?php
namespace Collegeman\Slim\Common;

class RestMiddleware extends \Slim\Middleware {
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
   * Make sure that the method is callable.
   * The method "search" is always callable.
   * In all other cases, look for the presence of @public in the doc comment of the method given.
   * @param String The class to test
   * @param String The method to test
   * @return bool true if the method is "search" (and it exists) or has @public, otherwise false
   * @throws Exception if method does not exist
   */
  static function isCallable($class, $method) {
    if ($method === 'search') {
      return is_callable(array($class, $method));
    }

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
      $log->debug($e->getMessage()."\n".$e->getTraceAsString());
      if ($e instanceof AccessException) {
        $res->status($e->getCode());
      } else {
        $res->status(500);
      }
    }

    // encode
    $res->write(defined('JSON_PRETTY_PRINT') ? json_encode($result, JSON_PRETTY_PRINT & JSON_NUMERIC_CHECK) : json_encode($result, JSON_NUMERIC_CHECK));
  }

  private function prepForEncoding($r) {
    if ($r instanceof Model || $r instanceof ORM) {
      return self::prepForEncoding($r->as_array());
    } else if (is_array($r)) {
      $array = array();
      foreach($r as $i => $value) {
        $array[$i] = self::prepForEncoding($value);
      }
      return $array;
    } else if (is_object($r)) {
      $array = array();
      foreach(get_object_vars($r) as $i => $value) {
        $array[$i] = self::prepForEncoding($value);
      }
      return $array;
    } else {
      return $r;
    }
  }

  /**
   * Create an instance of RestMiddleware and install it into the given Slim Application.
   * @param \Slim\Slim The application instance
   * @param String (optional) When provided, a root path in which
   * to search for Model classes. Sets up an autoloader per PHP
   * Autoloading Standard PSR-0.
   * @see http://www.php-fig.org/psr/psr-0/
   */
  static function addToApp($app, $pathToLib = null) {
    if (!is_null($pathToLib)) {

      spl_autoload_register(function($className) use ($pathToLib) {
        $className = ltrim($className, '\\');
        $fileName  = $pathToLib;
        $namespace = '';
        if ($lastNsPos = strripos($className, '\\')) {
          $namespace = substr($className, 0, $lastNsPos);
          $className = substr($className, $lastNsPos + 1);
          $fileName  .= str_replace('\\', DIRECTORY_SEPARATOR, strtolower($namespace)) . DIRECTORY_SEPARATOR;
        }
        $fileName .= DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, strtolower($className)) . '.php';
        
        if (file_exists($fileName)) {
          return require($fileName);
        }
      });

    }


    $app->add(new RestMiddleware());

    // Setup generic model access for all relevant request types
    $app->map('/api/:model(/:id(/:function)?)?', function($model, $id = false, $function = false) use ($app) {
      RestMiddleware::enable();
      $req = $app->request();
      
      // if the class doesn't exist, we're done
      if (!class_exists($model)) {
        throw new AccessException('Not found', 403);
      }

      // if $id is non-numeric, then we treat it as a function name
      if ($id !== false && !is_numeric($id)) {
        $function = $id;
        $id = false;
      }

      // requests made to /api/:model are mapped to a function called "search"
      if ($req->isGet() && !$id && !$function) {
        $function = 'search';
      }
      
      // look for the fields arg
      $fields = $req->isGet() && ($fieldList = $req->get('fields')) ? array_map('trim', explode(',', $fieldList)) : array();

      // if an ID is provided
      if ($id !== false) {
        $load = array(ucwords($model), 'load');

        if (!is_callable($load)) {
          throw new AccessException('Invalid', 404);
        }

        // try to load the object
        if (!$instance = call_user_func_array($load, array($id, $fields))) {
          throw new AccessException('Not found', 404);
        }
      
        // don't allow access to private models
        if ($instance instanceof PrivateModel) {
          throw new AccessException();
        }
      } else {
        if ($model instanceof PrivateModel) {
          throw new AccessException();
        }
      }  

      // if no other function is listed, return the instance
      if ($req->isGet() && !$function) {
        return RestMiddleware::result( $instance );  
      }

      // allow for JSON input or $_POST
      $input = $req->headers('CONTENT-TYPE') === 'application/json' ? array_merge($req->params(), (array) json_decode(file_get_contents('php://input'))) : $req->params();

      // create request?
      if ($req->isPost() && !$function) {
        return RestMiddleware::result( call_user_func_array(array($model, 'create'), array($input)) );
      }

      // update request?
      if ($req->isPut() && !$function) {
        return RestMiddleware::result( call_user_func_array(array($instance, 'update'), array($input)) );
      }

      // delete request?
      if ($req->isDelete() && $id !== false && !$function) {
        return RestMiddleware::result( call_user_func_array(array($model, 'trash'), array($id, $input)) );
      }

      if ($function) {
        if (RestMiddleware::isCallable($model, $function)) {
          if ($id !== false) {
            return RestMiddleware::result( call_user_func_array(array($instance, $function), array($input)) );
          } else {
            return RestMiddleware::result( call_user_func_array(array($model, $function), array($input)) );
          }
        } else {
          throw new AccessException();
        }
      } else {
        throw new AccessException(null, 404);
      }

    })->via('GET', 'POST', 'PUT', 'DELETE');
  }

}