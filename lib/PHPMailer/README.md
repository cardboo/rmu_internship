# PHPMailer — install instructions

`includes/email.php` uses PHPMailer for transactional email. Because
this project is vanilla PHP (no Composer), you have to drop the
PHPMailer source files into this folder once.

## Quickest install (one command)

From the project root:

```
tools\install_phpmailer.bat
```

That batch file:
1. Wipes any previous `lib/PHPMailer/PHPMailer-*` folder.
2. Uses `git clone` (or falls back to PowerShell `Invoke-WebRequest`)
   to fetch the stable PHPMailer v6.9.3 release.
3. Drops it into `lib/PHPMailer/PHPMailer-6.9.3/`.

`includes/email.php` auto-discovers the `src/` folder under
`lib/PHPMailer/` regardless of which sub-directory name the zip
extracts to, so no code change is needed after install.

## Use the stable 6.9.x line, NOT 7.0.x

If you grabbed PHPMailer manually, make sure you got the
**6.9.x stable** release — not 7.0.x. 7.x is a pre-release branch
that has shown TCP-connect quirks on Windows (the symptom is an
error 10061 / "Could not connect to SMTP host" even though port
587 is fully open). 6.9.3 fixes that.

## Manual install (if the batch file doesn't run)

1. Download the latest 6.9.x zip:
   <https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.3.zip>
2. Extract it. You'll get a folder named `PHPMailer-6.9.3`.
3. Move the whole `PHPMailer-6.9.3` folder into `lib/PHPMailer/`
   so you end up with:

   ```
   lib/PHPMailer/PHPMailer-6.9.3/src/PHPMailer.php
   lib/PHPMailer/PHPMailer-6.9.3/src/SMTP.php
   lib/PHPMailer/PHPMailer-6.9.3/src/Exception.php
   ```

4. Verify with the CLI smoke test from the project root:

   ```
   php tools\test_email.php your.real.address@gmail.com
   ```

   You should see "Sent OK"; otherwise the script prints the full
   SMTP transcript so the failure mode is obvious.

## If you ever switch to Composer

Run `composer require phpmailer/phpmailer:^6.9` and replace the
three `require_once` calls in `includes/email.php` with:

```php
require __DIR__ . '/../vendor/autoload.php';
```

The rest of the integration keeps working unchanged.
