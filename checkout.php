<?php
class Checkout {

  function button($args = '') {
    if (!is_array($args)) {
      parse_str($args, $args);
    }
    
    $args = array_merge(array(
      'id' => 'product',
      'product' => 'Product',
      'price' => 1.00
    ), $args);

    $args['hash'] = self::hash($args);

    foreach($args as $key => $val) {
      echo sprintf(' data-%s="%s" ', $key, htmlentities($val, ENT_QUOTES));
    }
  }

  function hash($args) {
    ksort($args);
    return md5(json_encode($args).config('checkout.secret'));    
  }

  function valid($args) {
    $args = (array) $args;    
    $hash = $args['hash'];
    unset($args['hash']);
    ksort($args);
    $isValid = md5(json_encode($args).config('checkout.secret')) === $hash;
    $args['hash'] = $hash;
    return $isValid;
  }

}