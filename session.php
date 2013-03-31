<?php
session_cache_limiter(false);
session_start();
$current_user = false;

if (!config('auth.salt')) {
  throw new Exception("Please set auth.salt config var to a strong key");
}

class Role extends Model {}

class Group extends Model {}

class User extends Model {

  function _register() {
    
  }

  function roles() {
    return $this->has_many('Role');
  }

  function groups() {
    return $this->has_many('Group');
  }

  function can($role, $bool = null) {
    // getter:
    if (is_null($bool)) {
      return (bool) $this->roles()->where('role', $role)->find_one();  
    // setter:
    } else {
      if (true === (bool) $bool) {
        if (!$this->id()) {
          throw new Exception("Cannot add roles to an unsaved User");
        }
        $role = Model::factory('Role');
        $role->role = $role;
        $role->user_id = $this->id();
        $role->save(); // might dupe up here, but we don't care...
      } else {
        return $this->roles()->where('role', $role)->delete();
      }
    }    
  }

  function in($group, $bool = null) {
    // getter:
    if (is_null($bool)) {
      return (bool) $this->groups()->where('group', $group)->find_one();  
    // setter:
    } else {
      if (true === (bool) $bool) {
        if (!$this->id()) {
          throw new Exception("Cannot put and unsaved User into groups");
        }
        $group = Model::factory('Group');
        $group->group = $group;
        $group->user_id = $this->id();
        $group->save(); // might dupe up here, but we don't care...
      } else {
        return $this->groups()->where('group', $group)->delete();
      }
    }  
  }
}

/**
 * @param boolean Pass true to force regeneration; a new key will be generated if it does not exist
 * @return String CSRF nonce
 */
function csrf($regenerate = false) {
  @session_start();
  if ($regenerate || empty($_SESSION['csrf']) || !verify_csrf($_SESSION['csrf'])) {
    $_SESSION['csrf'] = nonce('csrf');
  }
  return $_SESSION['csrf'];
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

  @session_start();
  $seed .= session_id();
  /*
  $load = LoaderHelper::getInstance();
  if ($load->isLoggedIn()) {
    $seed .= $load->member->member_id;
  }
  */
  // hash the seed with our auth cookie salt, for extra special uniqueness
  return substr(hash_hmac('md5', $seed . _nonce_tick(), AUTH_SALT), -12, 10);
}

/**
 * Generate a nonce tick - a continuiously increasing integer that
 * advanced by 1 NONCE_SPLIT times for every NONCE_LIFESPAN period. This value is
 * used by nonce() and verify_nonce().
 */
function _nonce_tick() {
  return ceil(time() / ( NONCE_LIFESPAN / NONCE_SPLIT ));
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

  @session_start();
  $seed .= session_id();
  // get the current nonce tick
  $tick = _nonce_tick();
  
  for($i=0; $i<NONCE_SPLIT; $i++) {
    if ( substr(hash_hmac('md5', $seed . ( $tick - $i ), AUTH_SALT), -12, 10) == $nonce ) {
      return $i+1;  
    }
  }
  
  // Invalid nonce
  return false;
}

function has_session() {
  global $current_user;

  if ($current_user !== false) {
    return $current_user;
  }

  if (empty($_SESSION['user_id'])) {
    return false;
  }

  $current_user = ORM::for_table('users')->where('id', $_SESSION['user_id'])->find_one();

  if (!$current_user) {
    unset($_SESSION['user_id']);
    return false;
  }

  return $current_user;
}