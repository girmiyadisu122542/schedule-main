<?php

namespace App\Services\Notification;

use InvalidArgumentException;
use Translation\Message;

/**
 * Reads `config/notification_registry.php`.
 *
 * Trimmed when notifications moved from the external service to Laravel mail:
 * the per-locale, per-channel template-NAME lookup it used to do belonged to a
 * service that stored its own templates. A Blade view renders both languages
 * itself, so what survives is the part that was always this application's job —
 * validating that a template has the data it needs before anything is sent.
 */
class NotificationTemplateRegistry {

    /**
     * The entire registry.
     *
     * @return array
     */
    public function all(): array {
        return config('notification_registry', []);
    }

    /**
     * One template definition.
     *
     * @param string $key
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function get(string $key): array {
        $templates = $this->all();
        $normalizedKey = strtolower($key);

        if (isset($templates[$normalizedKey])) {
            return $templates[$normalizedKey];
        }

        throw new InvalidArgumentException("Notification template [{$key}] is not registered.");
    }

    /**
     * Refuse to send a template that is missing data it declares it needs.
     *
     * A mail rendered with a hole in it is discovered by the recipient, which is
     * the worst possible place to find out — so this runs before the send, not
     * inside the view.
     *
     * @param string $key
     * @param array $data
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function validateRequiredData(string $key, array $data): void {
        $definition = $this->get($key);
        $missing = [];

        foreach ($definition[NOTIFICATION_TEMPLATE_REQUIRED_DATA_KEY] ?? [] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            $fields = implode(', ', $missing);
            throw new InvalidArgumentException("Notification template [{$key}] is missing required data: {$fields}.");
        }
    }

    /**
     * The language a notification should be written in.
     *
     * Falls back through: the language the caller named → the language of the
     * request in flight → the application locale → English. A notification is
     * often sent from a queue or a console command where there is no request, so
     * the last two steps are load-bearing rather than defensive.
     *
     * The available keys come from `Translation\Message`, NOT from `BackLang`:
     * `$langs` is declared on the subclass, so asking the base class yields an
     * empty list and every language argument silently falls through to the
     * default — which is what made `--lang=am` render English.
     *
     * @param string|null $language
     * @return string
     */
    public function languageKeyFor(?string $language = null): string {
        $language = is_string($language) ? strtolower(trim($language)) : '';

        if ($language !== '' && in_array($language, Message::getAvailableLangKeys(), true)) {
            return $language;
        }

        try {
            if (app()->bound('request')) {
                return getCurrentLanguage(request());
            }
        } catch (\Throwable) {
        }

        $appLocale = strtolower((string) app()->getLocale());

        return in_array($appLocale, Message::getAvailableLangKeys(), true) ? $appLocale : ENGLISH_LANG_KEY;
    }

    /**
     * A Message translation rendered in a NAMED language.
     *
     * `Message::get()` resolves the language from the `lang` request header and
     * returns the default in console — so a recipient whose language was passed
     * in would get an Amharic body under an English subject, and a queued or
     * command-line send would always be English. This reads the language's
     * bucket directly instead.
     *
     * @param string $key
     * @param string $languageKey
     * @param array $bindings
     *
     * @return string|null
     */
    public function translateIn(string $key, string $languageKey, array $bindings = []): ?string {
        $translations = Message::getAllTranslations($languageKey);
        $translation = $translations[$key] ?? null;

        if (!is_string($translation) || $translation === '') {
            return null;
        }

        return $bindings === []
            ? $translation
            : Message::parseBindings($translation, $bindings, '{{$key}}');
    }
}
