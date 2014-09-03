<?php
use Collegeman\Slim\Common\User;
use Collegeman\Slim\Common\AccessException;

session_cache_limiter(false);
session_start();
$current_user = false;

/**
 * @param boolean Pass true to force regeneration; a new key will be generated if it does not exist
 * @return String CSRF nonce
 */
function csrf($regenerate = false) {
  if ($regenerate || empty($_SESSION['csrf']) || !verify_csrf($_SESSION['csrf'])) {
    $_SESSION['csrf'] = nonce('csrf');
  }
  $_SESSION['csrf'];
}

/**
 * @param String (optional) Test a specific token; default will be taken from $_REQUEST['csrf']
 * @return boolean
 */
function verify_csrf($token = null) {
  if (!$token && !empty($_SERVER['HTTP_X_CSRF'])) {
    $token = $_SERVER['HTTP_X_CSRF'];
  }
  if (!$token) {
    $token = $_REQUEST['csrf'];
  }
  return verify_nonce('csrf', $token);
}

function assert_verified_csrf($token = null) {
  if (!verify_csrf($token)) {
    throw new Exception("Invalid CSRF token");
  }
}

function assert_can_edit($data, $msg = null, $code = null) {
  assert_logged_in();

  if ($data instanceof Model) {
    $arr = $data->as_array();
  } else {
    $arr = (array) $data;
  }
  $not_current_user = empty($arr['user_id']) || $arr['user_id'] !== current_user()->id;
  if ( $not_current_user && !current_user_can('super') ) {
    throw new AccessException($msg, $code);
  }
}

/**
 * Produce a unique identifier verifiable for a limited time only. 
 * If the user is logged in, include the user's member ID in the 
 * generation of the key, thus making it more unique.
 * @param string $seed (Optional) Additional seed data makes the nonce 
 * unique to a specific request; must be reproducible for subsequent 
 * verification.
 * @param boolean $onetime (Optional) 
 */
function nonce($seed = null, $onetime = false) {
  if ($onetime) {
    $random = md5(uniqid());
    setcookie(nonce($seed), $random);
    $seed .= $random;
  }

  $seed .= session_id();
  // hash the seed with our auth cookie salt, for extra special uniqueness
  return substr(hash_hmac('md5', $seed . _nonce_tick(), config('auth.salt')), -12, 10);
}

/**
 * Generate a nonce tick - a continuiously increasing integer that
 * advanced by 1 NONCE_SPLIT times for every NONCE_LIFESPAN period. This value is
 * used by nonce() and verify_nonce().
 */
function _nonce_tick() {
  return ceil(time() / ( config('nonce.lifespan', 86400) / config('nonce.split', 24) ));
}

/**
 * Verify a nonce, given the seed that was used to produce it.
 * @param string $seed
 * @param string $nonce
 * @return int the number of nonce splits ago that the nonce was generated, up to NONCE_LIFESPAN / NONCE_SPLIT;
 * use verify_nonce($seed, $nonce) === 1 for the shortest period, use verify_nonce($seed, $nonce) == true
 * to validate the nonce within the entire NONCE_LIFESPAN (e.g., 24 hours)
 */
function verify_nonce($seed = null, $nonce, $onetime = false) {
  if ($onetime) {
    if (!isset($_COOKIE[nonce($seed)])) {
      return false;
    } else {
      $seed .= $_COOKIE[nonce($seed)];
    }
  }

  $seed .= session_id();
  // get the current nonce tick
  $tick = _nonce_tick();
  
  for($i=0; $i<config('nonce.split', 24); $i++) {
    if ( substr(hash_hmac('md5', $seed . ( $tick - $i ), config('auth.salt')), -12, 10) == $nonce ) {
      return $i+1;  
    }
  }
  
  // Invalid nonce
  return false;
}

function set_session($user, $remember = false) {
  global $current_user;

  $_SESSION['current_user'] = $current_user = $user;

  // TODO: put user and pass hashes into login cookie
    
}

function has_session() {
  global $current_user;

  if ($current_user !== false) {
    return $current_user;
  }

  if (empty($_SESSION['current_user'])) {
    return false;
  }

  if (is_object($_SESSION['current_user'])) {
    $current_user = $_SESSION['current_user'];
    return $current_user;
  }

  $current_user = ORM::for_table('users')->where('id', $_SESSION['current_user'])->find_one();

  if (!$current_user) {
    unset($_SESSION['current_user']);
    return false;
  }

  return $current_user;
}

/**
 * Alias for has_session()
 * @return User object or false if not an authenticated session
 */
function current_user() {
  return has_session();
}

function assert_logged_in($message = null, $code = null) {
  if (!has_session()) {
    throw new AccessException($message, $code);
  }
}

/**
 * Alias for User::can()
 * @return bool
 */
function current_user_can($role_name) {
  return ( ($user = has_session()) && ( $user->can('super') || $user->can($role_name) ));
}

function response_die($app, $msg, $status = 500) {
  // TODO: log errors

  $response = $app->response();
  $response->status($status);
  $response['Content-Type'] = 'text/plain';
  echo $msg;
}