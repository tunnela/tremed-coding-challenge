## Tremed Coding Challenge

A simple app demoing user auth and management

### Requirements

- PHP 8+ (`php-sqlite3` package for fast testing)
- Composer

### Setup

Run the following commands to install required `composer` packages:

```
composer install
```

After this duplicate `.env.example` file and name it to `.env`. Update `MAIL_FROM` and `SMTP_*` variables as needed.

### Running

Then start the app server with the following command:

```
composer serve
```

...and then visit `http://localhost:8000` on a web browser.

### Code

The most important code files can be found at:

- App: [public/index.php](https://github.com/tunnela/tremed-coding-challenge/blob/master/public/index.php)
- JS: [public/js/*](https://github.com/tunnela/tremed-coding-challenge/blob/master/public/js)
- CSS: [public/css/app.css](https://github.com/tunnela/tremed-coding-challenge/blob/master/public/css/app.css)
- HTML: [public/templates/*](https://github.com/tunnela/tremed-coding-challenge/blob/master/public/templates)
- HTML: [resources/views/*](https://github.com/tunnela/tremed-coding-challenge/blob/master/resources/views)
- PHP: [src/*](https://github.com/tunnela/tremed-coding-challenge/blob/master/src)
