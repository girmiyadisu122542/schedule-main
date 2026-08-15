<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One mailable for every registered notification template.
 *
 * The registry (`config/notification_registry.php`) already names the view, the
 * subject key and the required data, so a class per notification would be a
 * second place to keep the same three facts in step. New notifications are added
 * by registering a template and writing a Blade view — no PHP class needed.
 *
 * Deliberately NOT `ShouldQueue`. The OTP mail is on the sign-in path: queued,
 * it would sit unsent on any deployment without a running worker, and the
 * symptom is "nobody can log in" rather than "mail is slow". Sending inline
 * costs the request a second or two against Gmail and fails loudly instead.
 */
class TemplatedNotificationMail extends Mailable {
    use Queueable;
    use SerializesModels;

    /**
     * @param string $viewName the Blade view to render
     * @param string $subjectLine already localized by the caller
     * @param array $data template payload, validated against the registry
     * @param string $language the recipient's language key
     * @param string $footerNote localized footer line
     */
    public function __construct(
        // Named `$viewName`: `Mailable` already declares a public `$view`,
        // and a promoted readonly property cannot redeclare it.
        private readonly string $viewName,
        private readonly string $subjectLine,
        private readonly array $data,
        private readonly string $language,
        private readonly string $footerNote,
    ) {
    }

    /**
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: $this->subjectLine);
    }

    /**
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content {
        return new Content(
            view: $this->viewName,
            with: [
                'data' => $this->data,
                'language' => $this->language,
                'subject' => $this->subjectLine,
                'footerNote' => $this->footerNote,
                // Bilingual copy without a view per language. The English string
                // is the source of truth and the Amharic sits beside it, so a
                // translator editing one can see the other.
                'line' => fn (string $english, string $amharic, array $bindings = []): string => $this->translate(
                    $this->language === AMHARIC_LANG_KEY ? $amharic : $english,
                    $bindings,
                ),
            ],
        );
    }

    /**
     * Substitute `:placeholder` bindings into a line.
     *
     * Deliberately NOT `BackLang::get()`'s `{{placeholder}}` spelling: these
     * lines live inside Blade echoes, and Blade's `{{ ... }}` match is
     * non-greedy, so a nested `}}` closes the echo early and the view stops
     * compiling. The two mechanisms are separate — a string moved from a view
     * into a Message bucket has to be respelled.
     *
     * @param string $line
     * @param array $bindings
     *
     * @return string
     */
    private function translate(string $line, array $bindings): string {
        foreach ($bindings as $key => $value) {
            $line = str_replace(':' . $key, (string) $value, $line);
        }

        return $line;
    }
}
