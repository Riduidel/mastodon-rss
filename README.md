<h1 align="center">Welcome to mastodon-rss 👋</h1>
<p>
  <a href="#" target="_blank">
    <img alt="License: GPL" src="https://img.shields.io/badge/License-GPL-yellow.svg" />
  </a>
  <a href="https://twitter.com/Riduidel" target="_blank">
    <img alt="Twitter: Riduidel" src="https://img.shields.io/twitter/follow/Riduidel.svg?style=social" />
  </a>
</p>

> This small project reads the timeline of a Mastodon user into an RSS feed, allowing me to browse mastodon in the comfort of my RSS reader of choice

## WARNING

This version provides a small enhancement to the wonderful Mastodon REST library.
Unfortunatly, as the library author hasn't yet released a new version, it won't work on your machine.
Upvote [this pull request](https://github.com/phediverse/mastodon-rest/pull/11) if you want it integrated.

## Installation

* Declare your application in mastodon settings/development/applications
* Unzip the release in your own Apache web folder (`/var/www/html` on my Raspbian)
* Set the configuration variables (rename the `config.php.example` into `config.php` and edit that file to copy application id and secret)
* Open [${YOUR_SERVER}/mastodon-rss/timeline.php](${YOUR_SERVER}/mastodon-rss/timeline.php in your browser) in your browser

Enjoy!

## Author

👤 **Nicolas Delsaux**

* Website: http://riduidel.wordpress.com
* Twitter: [@Riduidel](https://twitter.com/Riduidel)
* Github: [@Riduidel](https://github.com/Riduidel)
* Mastodon: [@Riduidel@framapiaf.org](https://framapiaf.org/Riduidel)

## 🤝 Contributing

Contributions, issues and feature requests are welcome!<br />Feel free to check [issues page](https://github.com/Riduidel/mastodon-rss/issues). 

## Show your support

Give a ⭐️ if this project helped you!

***
_This README was generated with ❤️ by [readme-md-generator](https://github.com/kefranabg/readme-md-generator)_