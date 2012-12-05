<?php
session_cache_limiter(false);
session_start();
$current_user = false;

class Role extends Model {}

class Group extends Model {}

class User extends Model {

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


/*
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

function login_with_google() {
  global $client, $infoservice, $current_user;

  $google_user = $infoservice->userinfo->get();
  
  $user = ORM::for_table('users')->where('google_id', $google_user['id'])->find_one();

  if (!$user) {
    $user = ORM::for_table('users')->create();
    $user->google_id = $google_user['id'];
    $user->email = $google_user['email'];
  }

  $user->google_access_token = $client->getAccessToken();
  $user->save();

  $_SESSION['user_id'] = $user->id;
  $current_user = $user;
}
*/