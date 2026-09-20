# Mail

The [Mailman module](https://github.com/laikait/laika-engine/tree/main/docs/mailman) of `laikait/laika-engine` sends mail (a fluent wrapper over PHPMailer — SMTP, sendmail, qmail, `mail()`) and reads it (IMAP and POP3 clients, no `ext-imap` needed). The Core module requires it, so it's installed with the framework.

```php
use Laika\Engine\Mailman\Mailer;

(new Mailer(config('mail')))
    ->to('ann@example.com', 'Ann')
    ->subject('Welcome to Laika')
    ->body('<h1>Welcome!</h1><p>Thanks for signing up.</p>', "Welcome!\nThanks for signing up.")
    ->send();
```

> If `Laika\Engine\Mailman\Mailer` isn't found, your install predates core 5.1 — run `composer update`.

## Configuration

Nothing in the framework builds a mailer for you: pass [`lf-config/mail.php`](../01_getting-started/03_configuration.md#lf-configmailphp) to the constructor. Keys the Mailer doesn't know are silently ignored, so check the spelling against the table below. (Older skeletons suggested `secure` and `from_email`; rename them to `encryption` and `from`.)

```php
// lf-config/mail.php
return [
    'driver'     => 'smtp',
    'host'       => 'smtp.example.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'user@example.com',
    'password'   => 'secret',
    'from'       => 'no-reply@example.com',
    'from_name'  => 'Laika App',
];
```

| Key | Default | |
|---|---|---|
| `driver` | `smtp` | `smtp`, `sendmail`, `qmail`, `mail` |
| `host` | `localhost` | |
| `port` | `587` | |
| `encryption` | `tls` | `tls` = STARTTLS (port 587), `ssl` = implicit TLS (port 465), `''` = none |
| `username`, `password` | `''` | |
| `from`, `from_name` | `''` | Default sender |
| `charset` | `UTF-8` | |
| `timeout` | `30` | Seconds |
| `validate_cert` | `true` | Only disable against a dev server you control |
| `debug` | `0` | PHPMailer SMTP debug level |
| `keepalive`, `auto_tls` | `false`, `true` | |
| `xmailer` | `Laika Mailman` | The `X-Mailer` header — see below |

A DSN works too: `Mailer::fromDsn('smtp://user:pass@smtp.example.com:587?encryption=tls')`. Percent-encode credentials containing `@`, `:` or `/`.

## Sending

Every build method returns the mailer, so calls chain:

```php
$mailer = new Mailer(config('mail'));

$mailer->to('ann@example.com', 'Ann')
       ->cc('billing@example.com')
       ->replyTo('support@example.com', 'Support')
       ->subject('Your invoice')
       ->body($html, $plainText)
       ->attach(APP_PATH . '/lf-storage/invoices/1042.pdf', 'invoice-1042.pdf')
       ->send();
```

| Method | |
|---|---|
| `from(string $address, string $name = '')`, `to()`, `cc()`, `bcc()`, `replyTo()` | Addresses; the second argument is the display name |
| `subject(string $subject)` | |
| `body(string $body, string $plainText = '')` / `html(...)` | HTML plus an optional plain-text alternative (generated when omitted) |
| `text(string $body)` | Plain text only |
| `attach(string $path, string $name = '')` | A file from disk |
| `attachData(string $contents, string $filename, string $mimeType = '')` | Bytes you already hold |
| `embed(string $path, string $cid, string $name = '')` | Inline image, referenced as `<img src="cid:...">` |
| `priority(int $priority)`, `header(string $name, string $value)` | |
| `xmailer(?string $value)` | Set or remove the `X-Mailer` header |
| `send(): bool` | Send; throws on failure |
| `reset(): static` | Clear recipients, attachments and body to send another message |
| `lastError(): string` | PHPMailer's error text |
| `phpMailer(): PHPMailer` | The underlying PHPMailer, as an escape hatch |

Supplying a real plain-text version rather than the generated one scores better with spam filters.

### Bulk Sending

`sendMany()` reuses one SMTP connection for a batch, and one failure doesn't stop the rest:

```php
$results = $mailer->sendMany([
    fn (Mailer $m) => $m->to('a@example.com')->subject('Hi')->text('One.'),
    fn (Mailer $m) => $m->to('b@example.com')->subject('Hi')->text('Two.'),
]);
// [0 => true, 1 => true]
```

### The X-Mailer Header

Mail carries `X-Mailer: Laika Mailman` by default, with no version number. Use the constants to change it:

```php
$mailer->xmailer('My App 2.0');
$mailer->xmailer(Mailer::XMAILER_NONE);       // no header
$mailer->xmailer(Mailer::XMAILER_PHPMAILER);  // PHPMailer's default, version included
```

### OAuth (XOAUTH2)

`$mailer->oauth($provider)` takes a `PHPMailer\PHPMailer\OAuthTokenProvider` — a single method, `getOauth64()`. See the [Mailman module docs](https://github.com/laikait/laika-engine/tree/main/docs/mailman).

## Send Mail From a Queue

Sending over SMTP takes seconds, so do it in a [job](../12_queue/01_basic.md) rather than the web request:

```php
namespace App\Job;

use Laika\Engine\Mailman\Mailer;
use Laika\Engine\Queue\Abstracts\Job;

class SendMail extends Job
{
    public int $maxTries = 3;

    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $html,
    ) {}

    public function handle(): void
    {
        (new Mailer(config('mail')))->to($this->to)->subject($this->subject)->body($this->html)->send();
    }
}
```

```php
use Laika\Engine\Worker\Queue;

Queue::driver()->push(new SendMail($user['email'], 'Welcome', $html), queue: 'mail');
```

To call the mailer as a relay (`Mail::to(...)->send()`), bind it in a provider — see [Services & Relays](../07_services-and-relay/01_basic.md#quick-start). Remember a singleton mailer keeps its recipients: call `reset()` between messages.

## Rendering an Email Body

Use a template:

```php
$html = (new \Laika\Engine\App\Template())->view('emails/welcome');
```

Inside a queue job there is no web request, so the request-based template variables (`input`, `errors`, `visitor`, `page`) carry nothing useful — `assign()` everything the email needs.

## Reading Mail

`ImapReader` and `Pop3Reader` are protocol clients written for this package — no `ext-imap`.

```php
use Laika\Engine\Mailman\Reader\ImapReader;

$reader = new ImapReader([
    'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl',
    'username' => 'support@example.com', 'password' => 'secret', 'folder' => 'INBOX',
]);

$reader->connect();

foreach ($reader->search(['unseen' => true, 'since' => '-7 days']) as $uid) {
    $message = $reader->fetch($uid);                       // doesn't mark it read
    echo $message->subject, ' — ', $message->fromAddress(), "\n";

    foreach ($message->files() as $attachment) {
        $attachment->saveTo(APP_PATH . '/lf-storage/mail');  // sanitises the filename
    }

    $reader->markSeen($uid);
}

$reader->disconnect();
```

`$message->body()` is **not sanitised** — inbound HTML is attacker-controlled. Clean it before putting it in a page.

For routing inbound mail to handlers by ticket or invoice number (support inboxes), the Mailman module has a `Pipeline` that scans messages for identifiers. It's covered in the [Mailman module docs](https://github.com/laikait/laika-engine/tree/main/docs/mailman).

## Errors

Everything throws `Laika\Engine\Mailman\Exceptions\MailmanException` or a subclass:

| Exception | Means |
|---|---|
| `TransportException` | Connection refused, TLS failed, timeout |
| `AuthenticationException` | Credentials rejected |
| `ProtocolException` | The server refused a command |
| `MailmanException` | Anything else |

Credentials are redacted from exception messages.

## See Also

- [Queue](../12_queue/01_basic.md)
- [Mailman module docs](https://github.com/laikait/laika-engine/tree/main/docs/mailman) — the full reading and Pipeline API
