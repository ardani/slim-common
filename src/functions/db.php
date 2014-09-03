<?php


/**
 * Parse the given input into an array, and then backfill
 * with the contents of $defaults, if given.
 * @param mixed $in Accepts querystrings, arrays, and objects
 * @param mixed $defaults Accepts querystrings, arrays, and objects, or simply null
 * @return array Hashmap of input
 */
function parse_args($in, $defaults = null) {
  if (!$in && !$defaults) {
    return array();
  } else if (!$in) {
    return $defaults;
  }
  
  if (is_array($in)) {
    $in_arr = $in;
  } else if (is_object($in)) {
    $in_arr = get_object_vars($in);
  } else {
    parse_str($in, $in_arr);
  }

  if (!is_array($in_arr)) {
    throw new Exception("Failed to parse String input into an array: ".$in);
  }

  if (!is_null($defaults)) {
    $defaults = parse_args($defaults);
  }
  
  return $defaults && count($defaults) ? array_merge($defaults, $in_arr) : $in_arr;
}

/**
 * Unserialize value only if it was serialized.
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize($original) {
  if (is_serialized($original)) { // don't attempt to unserialize data that wasn't serialized going in
    return @unserialize($original);
  }
  return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized($data) {
  // if it isn't a string, it isn't serialized
  if (!is_string($data)) {
    return false;
  }
  $data = trim($data);
  if ('N;' == $data) {
    return true;
  }
  if (!preg_match('/^([adObis]):/', $data, $badions)) {
    return false;
  }
  switch ($badions[1]) {
    case 'a' :
    case 'O' :
    case 's' :
      if (preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
        return true;
      }
      break;
    case 'b' :
    case 'i' :
    case 'd' :
      if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
        return true;
      }
      break;
  }
  return false;
}

/**
 * Serialize data, if needed.
 *
 * @param mixed $data Data that might be serialized.
 * @return mixed A scalar data
 */
function maybe_serialize($data) {
  if (is_array($data) || is_object($data)) {
    return serialize( $data );
  }    
  if (is_serialized($data)) {
    return serialize($data);
  }    
  return $data;
}