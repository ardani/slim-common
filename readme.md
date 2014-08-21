# Jump start your next MVP with Slim Framework and this handy set of solutions to common problems.  

This project is for anyone wanting to build a web app or web service MVP using 
[Slim Framework](http://slimframework.com), running on PHP, MySQL, and memcache. 
I created it to help [myself](http://twitter.com/collegeman) and [my company](http://fatpandadev.com) go faster,
and now you can use it too!

Ready to get started? [Go, go, go](#getting-started).

## Want to help make it better?

Find a bug? [Open an issue on GitHub](https://github.com/collegeman/slim-common/issues).

Want a new feature? [Fork me](https://github.com/collegeman/slim-common/fork) and open
a pull request, but please [grok the open issues](https://github.com/collegeman/slim-common/issues) first 
to make sure no one else is already working on the same problem.

## A little history.

I like dreaming about products I want to build. And most of the time when I'm dreaming about
products, I'm dreaming about SaaS. Afterall, I've been writing code for the Web a long time.

When I first picked up PHP it was because it was free and Microsoft wasn't. Later, when
PHP became the focus of my development, it was because PHP was far easier than Java
for building iteratively and testing.

That was before I discovered CodeIgniter, WordPress, and countless other amazing open
source projects and communities. All of them were born of this wonderful and sometimes 
frustrating language we call PHP. I was hooked.

Sometime last year I discovered [Slim Framework](http://slimframework.com), and I felt emboldened 
by its simplicity. Not long after that I discovered [Paris and Idiorm](http://j4mie.github.io/idiormandparis/), 
both of which have also became reliable tools in my belt.

Later that same year as I was working to build two different MVPs for two different companies,
I decided that there were enough similarities between the two projects to justify creating
a "common" repository of code to share between the two.

And with that, **slim-common** was born.

## What slim-common is

### slim-common is a package of solutions

I started by solving some common lower-level problems: things like how to manage configuration. But
soon I moved on to larger challenges&mdash;challenges that required me to incorporate third-party
libraries like the Facebook PHP SDK. More challenging though they may be, the problems I was solving were still
*common* problems, so into the library went Facebook's code.

### slim-common provides these essential toolkits/solutions:

* the [Slim Framework](https://github.com/codeguy/Slim) core
* configuration management
* credit card processing ([Stripe](https://github.com/stripe/stripe-php))
* [Facebook API integration](https://github.com/facebook/facebook-php-sdk)
* [Google API integration](https://github.com/google/google-api-php-client)
* HTTP requests ([Requests](https://github.com/rmccue/Requests))
* markdown rendering
* newsletter management ([MailChimp](http://apidocs.mailchimp.com/api/mcapi_php_changelog.php))
* remote content embedding ([Embed.ly](http://embed.ly))
* sending transactional e-mails ([Mandril](https://packagist.org/packages/mandrill/mandrill))
* sending and receiving SMS messages ([Twilio](http://twilio.com))
* UI patterns ([Bootstrap](http://getbootstrap.com))
* user authentication and security

## What slim-common is not

### slim-common does not use composer

This was *almost* a deliberate decision. 

I like composer, but at the time I was beginning these projects I wasn't familiar with it. 

So instead I have enjoyed relying on git submodules for managing dependencies, and I still do. 
Of course, this makes any project you build using **slim-common** dependent upon a feature that belongs 
to git, which technically violates the [Dependency](http://12factor.net/dependencies)
factor of the [The Twelve-Factor App](http://12factor.net/), but only to the extent that to
get started developing with **slim-common** you need to have git installed&mdash;hardly seems
like a big ask.

Maybe one day I'll add a composer description file to **slim-common** and strip out some of
the third-party riders (like the Facebook PHP SDK), but until I have time for that kind of
meta work, I'm going to stick with what gets the job done. 

### slim-common is not an application template

To use **slim-common** you need to *add* it to your own project, just as if it were a 
giant library of code, because in fact it is a giant library of code. There's a little bit
of setup, and then there's a four-line index.php file you need to create, and then you're
off to the races!

That said, this library does contain some templated code that you can use to quickstart your own app.

# Getting Started

Create a local folder in which to do your work. Switch to that folder, and initialize your 
local git repository. Then add **slim-common** as a submodule:

    your-app > git submodule add git@github.com:collegeman/slim-common common

Copy the templates from `common/htdocs` into the root of your project:

    your-app > cp -R common/templates/* ./

Optionally, change directory into your new `./htdocs` folder, and setup a symlink 
for the CSS and JS packages that ride along with common

    your-app > cd htdocs
    your-app/htdocs > ln -s common/htdocs common

Last step: you need to set your `AUTH_SALT` config setting in `./config.php`. The
value you put in that constant will be used for encrypting things like passwords.
You can easily generate a random key [here](http://randomkeygen.com/).
Until you do that, all requests to your app will result in an error message.

Is it working? If you can request the following URL, then it's working:

    /working

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

If you're getting an error message, please [open an issue](https://github.com/collegeman/slim-common/issues) and report the problem.

If you're not seeing any error messages, you probably need to check your web container. I like Apache.

# Working with MySQL

# Working with memcache

# Working with user auth and session management

# Hosting a REST API
