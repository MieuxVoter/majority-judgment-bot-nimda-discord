# Majority Judgment Discord Bot

Made with [Nimda](https://github.com/JABirchall/NimdaDiscord), ❤, [Yasmin](https://github.com/CharlotteDunois/Yasmin).


## Getting Started

To install this bot, clone/download this repository.

Copy the `.env` to `.env.local`, and edit it with your preferences and Discord secret token
that you can get over there :
https://discordapp.com/developers/applications/

    cp .env .env.local

The `.env.local` file is ignored by git, so you won't commit your secrets by mistake.

Edit the configuration files in `/Nimda/Configuration` as needed. 

    composer install

Once all packages are installed run `php start.php`


## Prerequisites

* PHP version `^7.1`
* PHP `PDO` extensions
* PHP `mbstring` extension
* A discord bot token


## Features

- [x] Create a poll using `!poll 5 What shall we eat tonight?`
- [ ] Reveal the results of a poll
- [ ] Add a proposal to a poll


## Coding style

This is a **bot**.  Made with **Promises**.  No way this is going to be pretty.
Just enjoy yourselves.


## Deployment

This bot must be run in CLI: `php start.php`

> If this ends up working we'll just craft a Dockerfile I guess.


## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the 
[tags on this repository](https://github.com/JABirchall/NimdaDiscord/tags). 


## Authors

### Bot Authors

* **Hirondelle** - *Ninjaaa!*
* **Roipoussiere** - *Knight*
* **Vesporium** - *Magus*

### Framework Authors

* **JABirchall** - *Maintainer, Bot base, plugin system, timers*
* **Thurston** - *Intern, events*
* **[CharlotteDunois](https://github.com/CharlotteDunois)**


## License

This project is licensed under GNU AGPLv3 License - see the [LICENSE](LICENSE) file for details


## Acknowledgments

* [MieuxVoter](https://mieuxvoter.fr)
* [Parti Pirate](https://partipirate.org)