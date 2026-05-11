# PHPMailer — install instructions

`includes/email.php` uses PHPMailer for transactional email. Because
this project is vanilla PHP (no Composer), you have to drop the
PHPMailer source files into this folder once.

## One-time install

1. Download the latest PHPMailer release zip from GitHub:

       https://github.com/PHPMailer/PHPMailer/releases/latest

   (Look for the "Source code (zip)" link on the release page.)

2. Extract it. You'll get a folder named `PHPMailer-x.y.z`.

3. Copy the contents of that folder's `src/` directory into this
   directory's `src/` subfolder so you end up with:

       lib/PHPMailer/src/PHPMailer.php
       lib/PHPMailer/src/SMTP.php
       lib/PHPMailer/src/Exception.php
       lib/PHPMailer/src/POP3.php          (not used but harmless)
       lib/PHPMailer/src/OAuth.php         (not used but harmless)
       lib/PHPMailer/LICENSE               (optional)

4. Verify by running the CLI smoke test from the project root:

       php tools/test_email.php your.email@example.com

   You should see "Sent OK" on success, or a full SMTP transcript
   on failure (transcript shows the exact Gmail error so you can
   debug — wrong app password, port 587 blocked by network, etc.).

## Updating PHPMailer later

Drop a newer release on top of the existing `src/` files. The
integration in `includes/email.php` only uses the public classes
(`PHPMailer`, `SMTP`, `Exception`), which are stable across 6.x.

## If you ever switch to Composer

Run `composer require phpmailer/phpmailer` and replace the three
`require_once` calls in `includes/email.php` with:

```php
require __DIR__ . '/../vendor/autoload.php';
```

The rest of the integration keeps working unchanged.
