<?php

namespace App\Services\Notification;

use App\Mail\TemplatedNotificationMail;
use Common\Notification\ScheduleNotificationServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the system's transactional notifications with Laravel's own mailer.
 *
 * This replaced an HTTP client that posted to a separate notification service
 * owned by another system. That service is not part of this deployment, so
 * every notification it was responsible for — new-account credentials, and the
 * OTP codes behind sign-in, 2FA and password reset — silently failed: the client
 * logged a warning and returned false, and the caller reported "could not send"
 * with nothing to point at.
 *
 * The interface is unchanged on purpose. `OtpHelper` and `SendCredentialsService`
 * call `sendTemplated()` exactly as before; only the binding in
 * `NotificationServiceProvider` moved.
 *
 * **Email only.** There is no SMS provider in this deployment. A caller asking
 * for SMS gets `false` and a log line naming the template, rather than a
 * silently dropped message — see {@see self::deliverableRecipient()}.
 */
class MailNotificationService implements ScheduleNotificationServiceInterface {

    public function __construct(
        private readonly NotificationTemplateRegistry $registry,
    ) {
    }

    /**
     * Send a single templated notification to one recipient.
     *
     * @param array $recipient contact data — `email` is the only deliverable key
     * @param string $templateKey a key in `config/notification_registry.php`
     * @param array $data template payload
     * @param array $channels requested channels; only `email` can be served
     * @param string|null $language recipient language key
     *
     * @return bool
     */
    public function sendTemplated(
        array $recipient,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null,
    ): bool {
        try {
            $address = $this->deliverableRecipient($recipient, $templateKey, $channels);
            if (!$address) {
                return false;
            }

            $this->registry->validateRequiredData($templateKey, $data);

            Mail::to($address)->send($this->build($templateKey, $data, $language));

            return true;
        } catch (\InvalidArgumentException $exception) {
            // A template that is not registered, or is missing required data:
            // a programming error, not a delivery failure.
            Log::warning('Notification skipped: ' . $exception->getMessage(), compact('templateKey', 'channels'));

            return false;
        } catch (\Throwable $exception) {
            // SMTP refused, credentials rejected, host unreachable. The caller
            // turns `false` into a user-facing message; the detail lives here.
            Log::error('Notification failed: ' . $exception->getMessage(), compact('templateKey'));

            return false;
        }
    }

    /**
     * Send a templated notification to several recipients.
     *
     * Sent one message per recipient rather than one message with many
     * recipients: these are credentials and one-time codes, so every address
     * would otherwise see every other address. One failure does not abort the
     * rest, and the return value reports whether ALL of them were delivered.
     *
     * @param array $recipients
     * @param string $templateKey
     * @param array $data
     * @param array $channels
     * @param string|null $language
     *
     * @return bool
     */
    public function sendBulkTemplated(
        array $recipients,
        string $templateKey,
        array $data = [],
        array $channels = [],
        ?string $language = null,
    ): bool {
        $sent = 0;

        foreach ($recipients as $recipient) {
            if ($this->sendTemplated((array) $recipient, $templateKey, $data, $channels, $language)) {
                $sent++;
            }
        }

        return $sent > 0 && $sent === count($recipients);
    }

    /**
     * The address this notification can actually reach, or null.
     *
     * @param array $recipient
     * @param string $templateKey
     * @param array $channels
     *
     * @return string|null
     */
    private function deliverableRecipient(array $recipient, string $templateKey, array $channels): ?string {
        $email = $recipient['email'] ?? null;

        if ($email) {
            return $email;
        }

        // Reached when the caller asked for SMS, or passed a phone-only
        // recipient. Logged rather than thrown: the caller already treats false
        // as "not sent" and reports it, and an exception here would abort a
        // transaction over a channel this deployment never had.
        Log::warning('Notification not sent: no email address, and SMS is not configured in this deployment.', [
            'templateKey' => $templateKey,
            'channels' => $channels,
            'recipientKeys' => array_keys($recipient),
        ]);

        return null;
    }

    /**
     * Build the mailable for a registered template.
     *
     * @param string $templateKey
     * @param array $data
     * @param string|null $language
     *
     * @return \App\Mail\TemplatedNotificationMail
     */
    private function build(string $templateKey, array $data, ?string $language): TemplatedNotificationMail {
        $definition = $this->registry->get($templateKey);
        $languageKey = $this->registry->languageKeyFor($language);

        $view = $definition[NOTIFICATION_TEMPLATE_VIEW_KEY] ?? null;
        if (!$view) {
            throw new \InvalidArgumentException("Notification template [{$templateKey}] names no view.");
        }

        return new TemplatedNotificationMail(
            viewName: $view,
            subjectLine: $this->localized(
                $definition[NOTIFICATION_TEMPLATE_SUBJECT_KEY] ?? '',
                $languageKey,
                (string) config('app.name'),
            ),
            data: $data,
            language: $languageKey,
            footerNote: $this->localized('email_footer_note', $languageKey, (string) config('app.name')),
        );
    }

    /**
     * A translated line in the recipient's language, falling back to something
     * sendable.
     *
     * An unregistered key resolves to null, and a null subject reaches the
     * recipient as a blank subject line — so the fallback is the app name rather
     * than an empty string.
     *
     * @param string $key
     * @param string $languageKey
     * @param string $fallback
     *
     * @return string
     */
    private function localized(string $key, string $languageKey, string $fallback): string {
        $translated = $key
            ? $this->registry->translateIn($key, $languageKey, ['app' => (string) config('app.name')])
            : null;

        return is_string($translated) && $translated !== '' ? $translated : $fallback;
    }
}
