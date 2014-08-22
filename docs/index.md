# Getting Started

If you're unfamiliar with Slim, you should start by [reading their documentation](http://docs.slimframework.com/).

You'll need a runtime container—Apache or something else. Also, to make the most of the framework, 
you'll need MySQL and memcache. On my laptop I'm running OSX 10.8.5 (I need to upgrade). 
I use the copy of Apache that comes with the Mac, and I use [HomeBrew](http://brew.sh/)
to install PHP, MySQL, and memcache.

How you setup your runtime container is entirely up to you, but the following instructions
do assume that you'll make the webroot of your app a folder inside your app called `./htdocs`&mdash;
don't worry about creating that folder yourself, as there's a step below in which we do
it by copying templates from common into your app.

Next, create a local folder in which to do your work. Switch to that folder, and initialize your 
local git repository. Then add **slim-common** as a submodule:

    your-app > git submodule add git@github.com:collegeman/slim-common common

Copy the templates from `common/htdocs` into the root of your project:

    your-app > cp -R common/templates/* ./

If you already have a `.gitignore` file, add `config.php` to the list. If you
don't have a `.gitignore` file, you can copy the one from common into your
own project:

    your-app > cp common/templates/.gitignore ./

Optionally, change directory into your new `./htdocs` folder, and setup a symlink 
for the CSS and JS packages that ride along with common

    your-app > cd htdocs
    your-app/htdocs > ln -s ../common/htdocs common

Last step: you need to set your `AUTH_SALT` config setting in `./config.php`. 

The value you put in that constant will be used for encrypting things like passwords.
You can easily generate a random key [here](http://randomkeygen.com/).
Until you do that, all requests to your app will result in an error message.

Is it working? If you request the following URL

    /working

and you see the following output

    Yep. It's working.

Then you have succeeded. Happy coding!

## It's not working?

### I'm seeing a 404

Make sure that the file `./htdocs/.htaccess` exists in your project path. The contents of the file should be as follows:

    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule . index.php [L]

### I'm seeing something else

If you're not seeing any error messages, the first thing you should do is check your PHP error log.

If you're seeing this error in the log:

    PHP Fatal error:  Uncaught exception 'Exception' with message 'Please set auth.salt config var to a strong key'

Then you skipped that step about setting your `AUTH_SALT` in `./config.php`. Do that now.

If you're seeing some other error message, please [open an issue](https://github.com/collegeman/slim-common/issues) and report the problem.

If you're not seeing any error messages, you probably need to check the health of your runtime container.

Can't get it working? [Open an issue](https://github.com/collegeman/slim-common/issues)&mdash;depending upon the nature of your problem, I am available for consulting.

## Setting up routes

You'll find a couple of pre-configured routes in `./dispatcher.php`. The default routes include:

* **/info** &mdash; Executes `phpinfo()`
* **/server** &mdash; Pretty-prints the contents of `$_SERVER`
* **/working** &mdash; "Yep. It's working."

The **/info** and **/server** routes won't work until you setup MySQL and user ACL features.

## Setting up MySQL

## Setting up Memcache

[Read about how to use routing](http://docs.slimframework.com/#Routing-Overview) in the documentation for Slim Framework.

# Configuration management

First and foremost, remember that the [Config](http://12factor.net/config) factor of 
[The Twelve-Factor App](http://12factor.net/) is to store config in the environment. I like
to remember this easily as, "Don't put API keys in your code."

Storing configuration in the environment also allows you to have multiple
environments with multiple configurations using a single deployment process&mdash;
you need this for going fast at the beginning and scaling at the end. 

If you didn't do it when you were getting started, make sure that the local `./config.php` 
file is ignored by git&mdash;you don't want to version control your local 
configuration file, or any configuraiton file for that matter.

The config system built-into **slim-common** is simple and flexible. 

A global function
`config($name, $default)` can be called in any context for loading a configuration
value named `$name`, for example:

    $app_id = config('facebook.app.id', $default);

You should express `$name` in lowercase, using a dot notation
to separate groups of configuration values, e.g., `"db1.host"`. Use the optional
second argument `$default` to specify a default value to return in the event that
configuration key `$name` does not exist.

The configuration system looks in three places for configuration values:

* In constants, e.g., `define('DB1_HOST')`
* In the `$_SERVER` global, e.g., `$_SERVER['DB1_HOST']`
* And in the Slim Framework app, e.g., `Slim::config('db1.host')`

The config system will first look at constants for config values&mdash;this makes
it possible to use the PHP file `./config.php` to store configuration in code,
*but you should only do this locally, for the convenience, and don't put your
local configuration into version control*. 
A configuration key named `"db1.host"` would be found in a constant named `DB1_HOST`&mdash;
all uppercase, and the dots are replaced by underscores.

Next the config system will search the `$_SERVER` scope. Here the name is converted
just as it is for the constant-based configuration pattern: `"db1.host"` would be
found in `$_SERVER['DB1_HOST']`. This is my preferred method of configuration as it
allows me to store config with the runtime container&mdash;in the case of Apache,
in `<VirtualHost>` blocks using the `SetEnv` directive. You can read more about
`SetEnv` [here](http://stackoverflow.com/questions/10902433/setting-environment-variables-for-accessing-in-php)
and [here](http://httpd.apache.org/docs/2.2/mod/mod_env.html).

The third and final place the config system will look is in the Slim Framework
application configuration via `Slim::config()` which you can read more about
[here](http://docs.slimframework.com/#Configuration-Overview).      

# MySQL, Idiorm, and Paris

# User Auth and ACL

# Memcache

# Building REST APIs
