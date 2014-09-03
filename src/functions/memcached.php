<?php
$mem = null;
if ( ($mem_host = config('cache1.host')) && ($mem_port = config('cache1.port')) ) {
  $mem = new Memcached;
  $mem->addServer(config('cache1.host'), config('cache1.port'));
}

/**
 * Try to write data to memcache.
 * @param String The cache key to read
 * @param mixed The value to store
 * @param int The time until cache expiration; defaults to 0 (no timeout)
 * @return The result of Memcached::set
 */
function memset($key, $value, $timeout = 0) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  return $mem->set($key, $value, $timeout);
}

/**
 * Try to read data to memcache.
 * @param String The cache key to read
 * @param mixed A default value to return when the value in the cache is null or missing
 * @return mixed The content of the cache or $default
 */
function memget($key, $default = false) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  $value = $mem->get($key);
  return is_null($value) ? $default : $value;
}

/**
 * Try to delete data from memcache.
 * @param String The cache key to delete
 * @return The result of Memcached::delete
 */
function memdel($key) {
  global $mem;
  if (is_null($mem)) {
    return false;
  }
  return $mem->delete($key);
}