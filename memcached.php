<?php
$mem = new Memcached;
$mem->addServer(config('cache1.host'), config('cache1.port'));

function memset($key, $value, $timeout = 0) {
  global $mem;
  return $mem->set($key, $value, $timeout);
}

function memget($key, $default = false) {
  global $mem;
  $value = $mem->get($key);
  return is_null($value) ? $default : $value;
}

function memdel($key) {
  global $mem;
  return $mem->delete($key);
}