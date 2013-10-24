<?php
session_cache_limiter(false);
session_start();
$current_user = false;

if (!config('auth.salt')) {
  throw new Exception("Please set auth.salt config var to a strong key");
}

class Role extends SlimModel {

  static function getOrCreate($name) {
    $factory = Model::factory(get_called_class());
    if (!$role = $factory->where('name', $name)->find_one()) {
      $role = $factory->create();
      $role->name = $name;
      $role->save();
    }
    return $roll;
  } 

}

class RoleUser extends SlimModel {}

class User extends SlimModel {

  protected $_roles = false;
  protected $_prefs = false;
  protected $_settings = false;

  static function _startLoginSession($userdata) {
    try {
      unset($userdata->password);
      $user = self::_register($userdata, true);
    } catch (EmailAddressAlreadyRegisteredException $e) {
      $user = Model::factory('User')->where('email_address', $userdata->email_address)->find_one();
      if (!$user) {
        // hmm... double exception: we found an user, and then
        // we couldn't find the user
        throw new Exception("Oops! Something strange happened. Please try again.");
      }
    }

    return $user->startLoginSession();
  }

  /**
   * Generate a key suitable for authentication with a timeout 
   * specified in minutes, then e-mail that key to this user.
   * @param int Number of minutes user has to login with key
   */
  function startLoginSession($window = 5) {
    if ($window < 1) {
      throw new Exception("Invalid window size: must be greater than 0 minutes.");
    }

    // start with random unique string
    $random = md5(uniqid());
    // capture the current time
    $time = time();

    $key = $this->getHash($random, $time, $window)->key;

    Mail::send(array(
      'to' => $this->email_address,
      'from' => 'yo@pickfourhq.com',
      'subject' => sprintf("Here's the link for accessing your sessions today, %s", date('F j')),
      'text' => 'slim:login.php'
    ), array(
      'link' => 'http://'.$_SERVER['SERVER_NAME'].'/auth/'.$key
    ));
  }

  function getHash($random, $time, $window) {
    $seed = "{$this->id},{$random},{$time},{$window}";
    $hash = md5(config('auth.salt').$seed);
    return (object) array(
      'seed' => $seed,
      'hash' => $hash,
      'key' => "{$hash},{$seed}"
    );
  }

  static function getBySessionKey($key) {
    list($hash, $id, $random, $time, $window) = explode(',', $key);
    if (!$id) {
      throw new AccessException("Invalid login: ID is missing");
    }
    if (!$user = self::getBy('id', $id)) {
      throw new AccessException("Invalid login: User does not exist by ID [{$id}]");
    }
    if (!$user->isSessionKeyValid($key)) {
      throw new AccessException("Invalid login: Bad session key");
    }
    return $user;
  }

  function isSessionKeyValid($key) {
    list($hash, $id, $random, $time, $window) = explode(',', $key);
    if ($this->id != $id) {
      return false;
    }
    if (!($hash && $random && $time && $window)) {
      return false;
    }
    if ( (time() - $time)/60 > $window ) {
      return false;
    }
    if ($hash != $this->getHash($random, $time, $window)->hash) {
      return false;
    }
    return true;
  }

  static function _register($userdata, $generate_password = false) {
    $userdata = (object) $userdata;
    // validation
    if (!isset($userdata->email_address)) {
      throw new Exception("E-mail address is required");
    }
    if (!isset($userdata->password)) {
      if ($generate_password) {
        $userdata->password = md5(uniqid().config('auth.salt'));
      } else {
        throw new Exception("Password is required");
      }
    }
    if (strlen($userdata->password) < 8) {
      throw new Exception("Password is too short: please use at least 8 characters");
    }
    // is email_address available?
    if (self::isEmailAddressRegistered($userdata->email_address)) {
      throw new EmailAddressAlreadyRegisteredException("That e-mail address is already registered");
    }

    $newUser = Model::factory(get_called_class())->create();
    $newUser->email_address = $userdata->email_address;
    $newUser->password = self::hashPassword($userdata->password);
    $newUser->utc_date_registered = date('c', time());
    $newUser->utc_date_confirmed = null;
    $newUser->save();

    set_session($newUser, isset($userdata->remember) ? $userdata->remember : false);

    // null out password 
    $newUser->password = null;

    return $newUser;
  }

  function encode() {
    $data = parent::encode();
    unset($data['password']);
    $data['roles'] = $this->getRoles(true);
    $data['prefs'] = $this->getPrefs(true);
    return $data;
  }

  static function _login($userdata) {
    global $current_user;

    $userdata = (object) $userdata;
    // validation
    if (!isset($userdata->email_address)) {
      throw new Exception("E-mail address is required");
    }
    if (!isset($userdata->password)) {
      throw new Exception("Password is required");
    }
    
    $user = Model::factory(get_called_class())->where('email_address', $userdata->email_address)->find_one();
    if (!$user) {
      throw new Exception("Oops! No user is registered for that e-mail address.");
    }
    if (!self::verifyPassword($userdata->password, $user->password)) {
      throw new Exception("Oops! That password is incorrect.");
    }

    $user->apply(array('utc_date_last_login' => date('Y-m-d H:i:s')));
    
    set_session($user, isset($userdata->remember) ? $userdata->remember : false);

    return $user;
  }

  function getPrefs($flush = false) {
    if ($this->_prefs === false || $flush) {
      $this->_prefs = array();
      foreach($this->getSettings() as $setting) {
        if (strpos($setting->name, 'pref_') === 0) {
          $this->_prefs[substr($setting->name, 5)] = $setting->value;
        }
      }
    }
    return $this->_prefs;
  }

  function __wakeup() {
    $this->_prefs = false;
    $this->_settings = false;
    $this->_roles = false;
  }

  function getSettings($flush = false) {
    if ($this->_settings === false || $flush) {
      $this->_settings = array();
      foreach($this->settings()->find_many() as $setting) {
        $this->_settings[] = (object) array(
          'name' => $setting->name,
          'value' => maybe_unserialize($setting->value)
        );
      }
    }
    return $this->_settings;
  }

  function settings() {
    return $this->has_many('UserSetting');
  }

  static function _prefs($prefs, $app) {
    if (!has_session()) {
      throw new AccessException();
    }

    if ($app->request()->isGet()) {
      return current_user()->getPrefs(true);
    }

    if ($app->request()->isPost()) {
      // preferences are set by the client, so we allow pretty much
      // anything to be stored there - don't forget this!
      // it's really designed for storing flags, like whether or not
      // a particular screen or message has been seen before
      foreach($prefs as $pname => $value) {
        $name = 'pref_'.trim($pname);
        if (strlen($name) > UserSetting::MAX_NAME_LENGTH) {
          throw new Exception("Preference name [{$pname}] is too long");
        }
        if (!preg_match('/^[\w_-]+$/', $name)) {
          throw new Exception("Invalid preference name [{$pname}] is too long"); 
        }
        ORM::raw_execute("
          INSERT INTO user_setting (
            `user_id`,
            `name`,
            `value`
          ) VALUES (
            ?, ?, ?
          ) ON DUPLICATE KEY UPDATE
            `user_id` = VALUES(`user_id`),
            `name` = VALUES(`name`),
            `value` = VALUES(`value`)        
        ", array(
          current_user()->id,
          $name,
          maybe_serialize($value)
        ));
      }
    }

    if ($app->request()->isDelete()) {

      foreach($prefs as $pname => $value) {
        $name = 'pref_'.trim($pname);
        ORM::raw_execute("
          DELETE FROM user_setting 
          WHERE
            `user_id` = ?
            AND `name` = ?
          LIMIT 1
        ", array(
          current_user()->id,
          $name
        ));
      }
    }

    
  }

   /**
   * Given a cleartext password and a hash, determine if the
   * given password and hash match.
   * @param String cleartext password
   * @param String a previously-generated hash of that password
   * @return bool true if the password and the has are paired
   * @see ApplicationHelper::hashPassword
   */
  public static function verifyPassword($cleartext, $storedHash) {
    $bcrypt = new Bcrypt(12);
    return $bcrypt->verify($cleartext.config('auth.salt'), $storedHash);
  }

  /**
   * Given a cleartext password, generate a hash that can later
   * be used to verify the content of the password used to create it.
   * @param String cleartext password
   * @return String a hash of the password
   */
  public static function hashPassword($cleartext) {
    $bcrypt = new Bcrypt(12);
    return $bcrypt->hash($cleartext.config('auth.salt'));
  }

  function isEmailAddressRegistered($email_address) {
    return (bool) Model::factory(get_called_class())->where('email_address', $email_address)->find_one();
  }

  function roles() {
    return $this->has_many_through('Role');
  }

  function getRoles($flush = false) {
    if ($this->_roles === false || $flush) {
      $this->_roles = array_unique(array_map(function($role) {
        return $role->name;
      }, $this->roles()->find_many()));
    }
    return $this->_roles;
  }

  function can($role_name, $bool = null) {
    // getter:
    if (is_null($bool)) {
      return in_array($role_name, $this->getRoles());

    // setter:
    } else {
      $role = Role::getOrCreate($role_name);
      if (true === (bool) $bool) {
        if (!$this->id()) {
          throw new Exception("Cannot add roles to an unsaved User");
        }
        $rel = Model::factory('RoleUser');
        $rel->role_id = $role->id;
        $rel->user_id = $this->id();
        $rel->save();
        $this->getRoles(true);

      } else {
        return Model::factory('RoleUser')->where('user_id', $this->id())->where('role_id', $role->id)->delete_many();
      }
    }    
  }

  /*
  function in($group, $bool = null) {
    // getter:
    if (is_null($bool)) {
      return (bool) $this->groups()->where('group', $group)->find_one();  
    // setter:
    } else {
      if (true === (bool) $bool) {
        if (!$this->id()) {
          throw new Exception("Cannot put an unsaved User into groups");
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
  */
}

class UserSetting extends SlimModel implements PrivateModel {

  const MAX_NAME_LENGTH = 64;

}

/**
 * Bcrypt hashing package.
 * Usage: 
 *   $bcrypt = new Bcrypt($rounds = 12);
 *   $hash = $bcrypt->hash('password');
 *   $bool = $bcrypt->verify('password', $hash) // == true
 * The value of $rounds will affect both the $hash result, as
 * well as the total time it takes to generate $hash.
 * @see http://stackoverflow.com/questions/4795385/how-do-you-use-bcrypt-for-hashing-passwords-in-php
 */
class Bcrypt {

  private $rounds;

  public function __construct($rounds = 12) {
    if (CRYPT_BLOWFISH != 1) {
      throw new Exception("bcrypt not supported in this installation. See http://php.net/crypt");
    }

    $this->rounds = $rounds;
  }

  public function hash($input) {
    $hash = crypt($input, $this->getSalt());

    if(strlen($hash) > 13)
      return $hash;

    return false;
  }

  public function verify($input, $existingHash) {
    $hash = crypt($input, $existingHash);

    return $hash === $existingHash;
  }

  private function getSalt() {
    $salt = sprintf('$2a$%02d$', $this->rounds);

    $bytes = $this->getRandomBytes(16);

    $salt .= $this->encodeBytes($bytes);

    return $salt;
  }

  private $randomState;

  private function getRandomBytes($count) {
    $bytes = '';

    if(function_exists('openssl_random_pseudo_bytes') &&
        (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) { // OpenSSL slow on Win
      $bytes = openssl_random_pseudo_bytes($count);
    }

    if($bytes === '' && is_readable('/dev/urandom') &&
       ($hRand = @fopen('/dev/urandom', 'rb')) !== FALSE) {
      $bytes = fread($hRand, $count);
      fclose($hRand);
    }

    if(strlen($bytes) < $count) {
      $bytes = '';

      if($this->randomState === null) {
        $this->randomState = microtime();
        if(function_exists('getmypid')) {
          $this->randomState .= getmypid();
        }
      }

      for($i = 0; $i < $count; $i += 16) {
        $this->randomState = md5(microtime() . $this->randomState);

        if (PHP_VERSION >= '5') {
          $bytes .= md5($this->randomState, true);
        } else {
          $bytes .= pack('H*', md5($this->randomState));
        }
      }

      $bytes = substr($bytes, 0, $count);
    }

    return $bytes;
  }

  private function encodeBytes($input) {
    // The following is code from the PHP Password Hashing Framework
    $itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    $output = '';
    $i = 0;
    do {
      $c1 = ord($input[$i++]);
      $output .= $itoa64[$c1 >> 2];
      $c1 = ($c1 & 0x03) << 4;
      if ($i >= 16) {
        $output .= $itoa64[$c1];
        break;
      }

      $c2 = ord($input[$i++]);
      $c1 |= $c2 >> 4;
      $output .= $itoa64[$c1];
      $c1 = ($c2 & 0x0f) << 2;

      $c2 = ord($input[$i++]);
      $c1 |= $c2 >> 6;
      $output .= $itoa64[$c1];
      $output .= $itoa64[$c2 & 0x3f];
    } while (1);

    return $output;
  }
}


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
  $arr = (array) $data;
  if (( empty($arr['user_id']) || $arr['user_id'] !== current_user()->id ) && !current_user_can('super')) {
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

/**
 * Alias for User::can()
 * @return bool
 */
function current_user_can($role_name) {
  return ( ($user = has_session()) && $user->can($role_name) );
}

function response_die($app, $msg, $status = 500) {
  // TODO: log errors

  $response = $app->response();
  $response->status($status);
  $response['Content-Type'] = 'text/plain';
  echo $msg;
}

class AccessException extends Exception {

  function __construct($message = null, $code = null) {
    if (is_null($message)) {
      $message = 'You are not allowed to access this resource.';
    }
    if (is_null($code)) {
      $code = 403;
    }
    parent::__construct($message, $code);
  }

}

class EmailAddressAlreadyRegisteredException extends Exception {}