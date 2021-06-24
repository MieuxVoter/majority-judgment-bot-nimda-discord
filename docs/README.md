# Contributing

Merge requests are most welcome.

This bot was made during a sprint, and is minimalistic.

It probably needs a stack redesign.  Do anything you want.


## Coding style

This is a **bot**.  Made with **Promises**.  No way this is going to be pretty.
Just enjoy yourselves.


## Deployment

This bot must be run in CLI: `php start.php`

> If this ends up working we'll just craft a Dockerfile I guess.


## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the 
[tags on this repository](https://github.com/JABirchall/NimdaDiscord/tags). 


## Database

### Laravel Stuff

Old, deprecated.  Still here for now, but let's remove it.
Instead, use Doctrine.

### Doctrine

Update the database structure from the PHP Entities:

    vendor/bin/doctrine orm:schema-tool:update --force --dump-sql

> Only handles additions and deletions, not proper migrations.
> `vendor/bin/doctrine-migrations` neeeeds additional configuration.  Help us! :)
