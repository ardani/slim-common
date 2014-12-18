<?php
namespace Collegeman\Slim\Common\Lib;

use Model;
use Collegeman\Slim\Common\Bcrypt;
use Collegeman\Slim\Common\EmailAddressAlreadyRegisteredException;

class User extends \Model {

  public static $_table = 'user';

  protected $_roles = false;
  protected $_prefs = false;
  protected $_settings = false;

  static function load($id, $fields) {
    $mem = "user:{$id}";
    if ($user = memget($mem)) {
      return $user;
    }
    $user = Model::factory(get_called_class())
      ->where('id', $id)
      ->find_one();
    if ($user) {
      memset($mem, $user);
    }
    return $user;
  }

  /**
   * @public
   */
  static function startLoginSession($userdata) {
    try {
      unset($userdata->password);
      $user = self::_register($userdata, true);
    } catch (EmailAddressAlreadyRegisteredException $e) {
      $user = Model::factory('User')->where('email_address', $userdata->email_address)->find_one();
      if (!$user) {
        // hmm... double exception: we found an user, and then
        // we couldn't find the user
        throw new \Exception("Oops! Something strange happened. Please try again.");
      }
    }

    return $user->startUserLoginSession();
  }

  /**
   * Generate a key suitable for authentication with a timeout
   * specified in minutes, then e-mail that key to this user.
   * @param int Number of minutes user has to login with key
   */
  function startUserLoginSession($window = 5) {
    if ($window < 1) {
      throw new \Exception("Invalid window size: must be greater than 0 minutes.");
    }

    // start with random unique string
    $random = md5(uniqid());
    // capture the current time
    $time = time();

    $key = $this->getHash($random, $time, $window)->key;

    Mail::send(array(
      'to' => $this->email_address,
      'from' => 'yo@pickfourhq.com',
      'subject' => sprintf("Here's the link for accessing your account today, %s", date('F j')),
      'text' => 'slim:login.php'
    ), array(
      'link' => 'https://'.$_SERVER['SERVER_NAME'].'/auth/'.$key
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

  /**
   * @public
   */
  static function register($userdata, $generate_password = false) {
    $userdata = (object) $userdata;
    // validation
    if (!isset($userdata->email_address)) {
      throw new \Exception("E-mail address is required");
    }
    if (!isset($userdata->password)) {
      if ($generate_password) {
        $userdata->password = md5(uniqid().config('auth.salt'));
      } else {
        throw new \Exception("Password is required");
      }
    }
    if (strlen($userdata->password) < 8) {
      throw new \Exception("Password is too short: please use at least 8 characters");
    }
    // is email_address available?
    if (self::isEmailAddressRegistered($userdata->email_address)) {
      throw new EmailAddressAlreadyRegisteredException("That e-mail address is already registered");
    }

    $newUser = Model::factory(get_called_class())->create();
    $newUser->name = trim($userdata->name);
    $newUser->email_address = trim($userdata->email_address);
    $newUser->password = self::hashPassword($userdata->password);
    $newUser->utc_date_registered = date('c', time());
    $newUser->utc_date_confirmed = null;
    $newUser->save();

    set_session($newUser, isset($userdata->remember) ? $userdata->remember : false);

    // null out password
    $newUser->password = null;

    return $newUser;
  }

  function as_array() {
    $data = parent::as_array();
    unset($data['password']);
    unset($data['email_address']);
    $data['roles'] = $this->getRoles(true);
    $data['prefs'] = $this->getPrefs(true);
    return $data;
  }

  /**
   * @public
   */
  static function login($userdata) {
    global $current_user;

    $userdata = (object) $userdata;
    // validation
    if (!isset($userdata->email_address)) {
      throw new \Exception("E-mail address is required");
    }
    if (!isset($userdata->password)) {
      throw new \Exception("Password is required");
    }

    $user = Model::factory(get_called_class())->where('email_address', $userdata->email_address)->find_one();
    if (!$user) {
      throw new \Exception("Oops! No user is registered for that e-mail address.");
    }
    if (!self::verifyPassword($userdata->password, $user->password)) {
      throw new \Exception("Oops! That password is incorrect.");
    }

    $user->utc_date_last_login = date('c', true);
    $user->save();

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

  /**
   * @public
   */
  static function prefs($prefs) {
    global $app;
    $req = $app->request();

    if (!has_session()) {
      throw new AccessException();
    }

    if ($req->isGet()) {
      return current_user()->getPrefs(true);
    }

    if ($req->isPost()) {
      // preferences are set by the client, so we allow pretty much
      // anything to be stored there - don't forget this!
      // it's really designed for storing flags, like whether or not
      // a particular screen or message has been seen before
      foreach($prefs as $pname => $value) {
        $name = 'pref_'.trim($pname);
        if (strlen($name) > UserSetting::MAX_NAME_LENGTH) {
          throw new \Exception("Preference name [{$pname}] is too long");
        }
        if (!preg_match('/^[\w_-]+$/', $name)) {
          throw new \Exception("Invalid preference name [{$pname}] is too long");
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

    if ($req->isDelete()) {

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

  static function isEmailAddressRegistered($email_address) {
    return (bool) Model::factory(get_called_class())->where('email_address', $email_address)->find_one();
  }

  function roles() {
    return $this->has_many_through('Collegeman\Slim\Common\Lib\Role');
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
          throw new \Exception("Cannot add roles to an unsaved User");
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
          throw new \Exception("Cannot put an unsaved User into groups");
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
