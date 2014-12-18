<?php
namespace Collegeman\Slim\Common;

use \Requests;

/**
 * Simplest possible helper for working with the Embed.ly API.
 */
class Embedly {

  /**
   * @type String The root URL for the Embed.ly API
   */
  private static $root = 'http://api.embed.ly/1';

  /**
   * Query Embed.ly's oEmbed service for an embeddable version
   * of the content available at the given URL.
   * @param String $url The URL to query; all normalization of the URL
   * is assumed to have been done externally.
   * @param array $opts Options for submitting along with the URL;
   * if omitted, the option "key" will be inserted and will carry
   * the value stored in config option "embedly.key"
   * @see config()
   * @throws Exception If the API response code is anything other than 200
   */
  static function oembed($url, $opts = array()) {
    $api = self::$root.'/oembed?'.http_build_query(array_merge(array(
      'key' => config('embedly.key'),
      'url' => $url
    ), $opts));

    $req = \Requests::get($api);
    $res = json_decode($req->body);
    if ($req->status_code !== 200) {
      throw new Exception($res->error_message, $res->error_code);
    } else {
      return $res;
    }
  }

  /**
   * Query Embed.ly's oEmbed service for an embeddable version
   * of the content available at the given URL.
   * @param String $url The URL to query; all normalization of the URL
   * is assumed to have been done externally.
   * @param array $opts Options for submitting along with the URL;
   * if omitted, the option "key" will be inserted and will carry
   * the value stored in config option "embedly.key"
   * @see config()
   * @throws Exception If the API response code is anything other than 200
   */
  function extract($url, $opts = array()) {
    $api = self::$root.'/extract?'.http_build_query(array_merge(array(
      'key' => config('embedly.key'),
      'url' => $url
    ), $opts));

    $req = \Requests::get($api);
    $res = json_decode($req->body);
    if ($req->status_code !== 200) {
      throw new Exception($res->error_message, $res->error_code);
    } else {
      return $res;
    }
  }

}
