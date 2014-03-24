<?php
class Embedly {

  private static $root = 'http://api.embed.ly/1';

  function oembed($url, $opts = array()) {
    $api = self::$root.'/oembed?'.http_build_query(array_merge(array(
      'key' => config('embedly.key'),
      'url' => $url
    ), $opts));

    $req = Requests::get($api);
    $res = json_decode($req->body);
    if ($req->status_code !== 200) {
      throw new Exception($res->error_message, $res->error_code);
    } else {
      return $res;
    }
  }

  function extract($url, $opts = array()) {
    $api = self::$root.'/extract?'.http_build_query(array_merge(array(
      'key' => config('embedly.key'),
      'url' => $url
    ), $opts));

    $req = Requests::get($api);
    $res = json_decode($req->body);
    if ($req->status_code !== 200) {
      throw new Exception($res->error_message, $res->error_code);
    } else {
      return $res;
    }
  }

}