<?php

namespace App\Console\Commands;

use Common\Notification\ScheduleNotificationServiceInterface;
use Illuminate\Console\Command;

/**
 * Verifies the mail configuration end to end.
 *
 * Mail failures are otherwise discovered by a user who never received their
 * credentials — `MailNotificationService` deliberately returns false instead of
 * throwing, so a rejected App Password looks exactly like a successful account
 * creation from the outside. This command sends a real notification through the
 * real service, so what it proves is what production will do.
 */
class TestMailCommand extends Command {
    protected $signature = 'schedule:test-mail
                            {email : Where to send the test message}
                            {--template=otp : Which registered template to render (otp|user_registration)}
                            {--lang= : Language key to render in (en|am); defaults to the app locale}';

    protected $description = 'Send a test notification to verify the SMTP configuration';

    /**
     * @param \Common\Notification\ScheduleNotificationServiceInterface $notificationService
     *
     * @return int
     */
    public function handle(ScheduleNotificationServiceInterface $notificationService): int {
        $email = (string) $this->argument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        if (!$this->reportConfiguration()) {
            return self::FAILURE;
        }

        $templateKey = $this->resolveTemplateKey();
        if (!$templateKey) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Sending the [{$templateKey}] template to {$email} ...");

        $sent = $notificationService->sendTemplated(
            recipient: ['email' => $email],
            templateKey: $templateKey,
            data: $this->sampleData($email),
            channels: [NOTIFICATION_SEND_METHOD_EMAIL],
            language: $this->option('lang'),
        );

        if (!$sent) {
            $this->newLine();
            $this->error('The message was NOT sent. The reason was logged to storage/logs — look for "Notification failed".');
            $this->line('  Most common causes with Gmail:');
            $this->line('   • MAIL_PASSWORD holds the account password instead of a 16-character App Password.');
            $this->line('   • 2-Step Verification is off, so App Passwords cannot be generated.');
            $this->line('   • MAIL_USERNAME and MAIL_FROM_ADDRESS are different addresses.');
            $this->line('   • Outbound port 587 is blocked on this host.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sent.');

        if (config('mail.default') === 'log') {
            $this->comment('MAIL_MAILER=log — the message went to storage/logs/laravel.log, not to an inbox.');
        }

        return self::SUCCESS;
    }

    /**
     * Print the settings in force and refuse to send on an obviously broken one.
     *
     * @return bool
     */
    private function reportConfiguration(): bool {
        $mailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport");
        $password = (string) config("mail.mailers.{$mailer}.password");

        $this->table(['Setting', 'Value'], [
            ['MAIL_MAILER', $mailer],
            ['host', (string) config("mail.mailers.{$mailer}.host")],
            ['port', (string) config("mail.mailers.{$mailer}.port")],
            ['scheme', (string) config("mail.mailers.{$mailer}.scheme")],
            ['username', (string) config("mail.mailers.{$mailer}.username")],
            ['password', $password !== '' ? str_repeat('*', strlen($password)) : '(empty)'],
            ['from', (string) config('mail.from.address') . ' — ' . (string) config('mail.from.name')],
        ]);

        // Checked for every transport, not just SMTP: an empty sender fails
        // inside the mailer itself with `An email must have a "From" ...`,
        // which reads as a code bug rather than a missing .env line.
        $fromAddress = (string) config('mail.from.address');

        if ($fromAddress === '') {
            $this->error('MAIL_FROM_ADDRESS is empty. Set it in .env, then run: php artisan config:clear');

            return false;
        }

        if ($transport !== 'smtp') {
            return true;
        }

        // Checked before sending because Gmail answers all of these with the
        // same opaque "Username and Password not accepted" response.
        $username = (string) config("mail.mailers.{$mailer}.username");

        if ($username === '' || $password === '') {
            $this->error('MAIL_USERNAME and MAIL_PASSWORD are required for SMTP. Set them in .env, then run: php artisan config:clear');

            return false;
        }

        if (str_contains((string) config("mail.mailers.{$mailer}.host"), 'gmail')) {
            if (strcasecmp($username, $fromAddress) !== 0) {
                $this->warn("MAIL_USERNAME ({$username}) and MAIL_FROM_ADDRESS ({$fromAddress}) differ. Gmail will rewrite the sender to the authenticated account and replies will go there.");
            }

            if (strlen(str_replace(' ', '', $password)) !== GMAIL_APP_PASSWORD_LENGTH) {
                $this->warn('MAIL_PASSWORD is not ' . GMAIL_APP_PASSWORD_LENGTH . ' characters. Gmail App Passwords always are — this may be the account password, which Gmail rejects over SMTP.');
            }
        }

        return true;
    }

    /**
     * The registered template key named by `--template`.
     *
     * @return string|null
     */
    private function resolveTemplateKey(): ?string {
        $requested = strtolower(trim((string) $this->option('template')));

        $known = [
            NOTIFICATION_TEMPLATE_KEY_OTP => NOTIFICATION_TEMPLATE_KEY_OTP,
            NOTIFICATION_TEMPLATE_KEY_USER_REGISTRATION => NOTIFICATION_TEMPLATE_KEY_USER_REGISTRATION,
        ];

        if (isset($known[$requested])) {
            return $known[$requested];
        }

        $this->error("Unknown template [{$requested}]. Available: " . implode(', ', array_keys($known)) . '.');

        return null;
    }

    /**
     * Placeholder payload satisfying every registered template's required data.
     *
     * Deliberately obvious filler — the recipient must never mistake a
     * configuration test for a real credential mail.
     *
     * @param string $email
     *
     * @return array
     */
    private function sampleData(string $email): array {
        return [
            'name' => 'Mail configuration test',
            'full_name' => 'Mail configuration test',
            'email' => $email,
            'password' => 'not-a-real-password',
            'otp' => '123456',
            'message' => 'This is a test of the mail configuration.',
            'time' => (string) OTP_EXPIRATION_TIME,
        ];
    }
}
