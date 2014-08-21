Put your Idiorm and Paris classes in this folder, one per file, named for the class inside each.

For example, if you have a database model named `Foo` like this

    <?php
    class Foo extends Model {

       static function findByWhatever() {
          // return whatever
       }

    }

Put this code into `lib/foo.php`. 

Doing so will allow the autoloader to find it, so that you can call `Foo::findByWhatever()`
from anywhere in your application stack.

If you're using the REST API features of **slim-common** you'll be able to access
the standard CRUD methods as well as any function denoted `@public` with the URL
convention

    /api/foo[/..]

You can read more about the API features in the main readme in **slim-common**.