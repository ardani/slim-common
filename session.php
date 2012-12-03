<?php
session_cache_limiter(false);
session_start();
$current_user = false;

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

