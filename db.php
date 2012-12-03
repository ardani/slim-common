<?php
require(ROOT.'/common/idiorm.php');
ORM::configure('mysql:host='.config('db1.host').';dbname='.config('db1.name').';port='.config('db1.port'));
ORM::configure('username', config('db1.user'));
ORM::configure('password', config('db1.pass'));