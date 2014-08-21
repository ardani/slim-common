# Jump start your next Slim Framework based MVP with this handy set of solutions to common problems.  

This project is for anyone wanting to build an MVP using PHP, MySql, and memcache. 
I created it to help [me](http://twitter.com/collegeman) and [my own company](http://fatpandadev.com) go faster,
and now you can use it too.

## Want to help make it better?

Find a bug? [Open an issue on GitHub](https://github.com/collegeman/slim-common/issues).

Want a new feature? [Fork me](https://github.com/collegeman/slim-common/fork) and open
a pull request, but make sure to scan open issues first to make sure no one else is 
already working on the same problem.

## A little history.

I like dreaming about products I want to build. And most of the time when I'm dreaming about
products, I'm dreaming about SaaS. Afterall, I've been writing code for the Web a long time.

When I first picked up PHP it was because it was free and Microsoft wasn't. Later, when
PHP became the focus of my development, it was because PHP was far easier for building and
and testing iteratively than Java.

And that was before I discovered CodeIgniter, WordPress, and countless other amazing open
source projects and communities, all of them born of this wonderful and sometimes frustrating
language we call PHP.

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
soon I moved on to larger challenges&mdash;challenges that required me incorporate things like
the Facebook PHP SDK. More challenging though they may be, the problems I was solving were still
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

    git submodule add git@github.com:collegeman/slim-common common

Copy the templates in `./htdocs` into your own path.

    cp -R common/templates ./

Optionally, setup a symlink for the CSS and JS packages that ride along with common

    ln -s common/htdocs ./htdocs/common

Last step: you need to set your `AUTH_SALT` config setting in `./config.php`. Until you do this,
all requests to your app will result in an error.

Is it working? If you can request the following URL, then it's working:

    /working

# Working with MySQL

# Working with memcache

# Working with user auth and session management

# Hosting a REST API
