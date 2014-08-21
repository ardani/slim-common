<?php
$mem = null;
if ( ($mem_host = config('cache1.host')) && ($mem_port = config('cache1.port')) ) {
  $mem = new Memcached;
  $mem->addServer(config('cache1.host'), config('cache1.port'));
}

function memset($key, $value, $timeout = 0) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  return $mem->set($key, $value, $timeout);
}

function memget($key, $default = false) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  $value = $mem->get($key);
  return is_null($value) ? $default : $value;
}

function memdel($key) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  return $mem->delete($key);
}