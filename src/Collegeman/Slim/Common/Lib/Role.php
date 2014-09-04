<?php
namespace Collegeman\Slim\Common\Lib;

use Model;

class Role extends \Model {

  static function getOrCreate($name) {
    $factory = Model::factory(get_called_class());
    if (!$role = $factory->where('name', $name)->find_one()) {
      $role = $factory->create();
      $role->name = $name;
      $role->save();
    }
    return $roll;
  } 

}

