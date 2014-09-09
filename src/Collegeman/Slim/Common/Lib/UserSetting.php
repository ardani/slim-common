<?php
namespace Collegeman\Slim\Common\Lib;

use Model;

class UserSetting extends \Model implements PrivateInterface {

  public static $_table = 'user_setting';

  const MAX_NAME_LENGTH = 64;

}
