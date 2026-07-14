<?php

namespace App\Services\Notification;

use Common\Lang\BackLang;
use InvalidArgumentException;

class NotificationTemplateRegistry {

    /**
     * Returns the entire notification template registry.
     *
     * @return array
     */
    public function all(): array {
        return config('notification_registry', []);
    }

    public function get(string $key): array {
        $templates = $this->all();
        $normalizedKey = strtolower($key);

        if (isset($templates[$normalizedKey])) {
            return $templates[$normalizedKey];
        }

        throw new InvalidArgumentException("Notification template [{$key}] is not registered.");
    }

    /**
     * Resolve the notification template to use for the given channels.
     * Resolves the notification template to use for the given channels, based on the language provided.
     * If no templates are found for the requested channels, an InvalidArgumentException will be thrown.
     *
     * @param string $key
     * @param array $channels
     * @param string|null $language
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function resolveChannelTemplates(string $key, array $channels, ?string $language = null): array {
        $definition = $this->get($key);
        $templates = $this->resolveLocaleTemplates($definition, $language);

        $resolved = [];
        foreach ($channels as $channel) {
            if (!empty($templates[$channel])) {
                $resolved[$channel] = $templates[$channel];
            }
        }

        if ($resolved === []) {
            throw new InvalidArgumentException("Notification template [{$key}] has no templates for the requested channels.");
        }

        return $resolved;
    }

    /**
     * Validates the given notification template data against the registered required data.
     * If any required data is missing, an InvalidArgumentException will be thrown.
     *
     * @param string $key
     * @param array $data
     *
     * @return void
     * @throws InvalidArgumentException
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
     * Resolves the notification template locale templates based on the given language key.
     * The function will return the first available template for the given language key,
     * otherwise it will return the English language templates.
     * If no templates are found, an InvalidArgumentException will be thrown.
     *
     * @param array $definition
     * @param string|null $language
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function resolveLocaleTemplates(array $definition, ?string $language = null): array {
        $locale = $this->resolveLanguageKey($language);

        foreach ([$locale, ENGLISH_LANG_KEY] as $languageKey) {
            $templates = $definition[$languageKey] ?? null;
            if (is_array($templates)) {
                return $templates;
            }
        }

        throw new InvalidArgumentException('Notification template is missing locale definitions.');
    }

    /**
     * Resolve the language key based on the given language key,
     * the current request, the application locale, or the English language key as a fallback.
     *
     * @param string|null $language
     * @return string
     */
    private function resolveLanguageKey(?string $language = null): string {
        $language = is_string($language) ? strtolower(trim($language)) : '';

        if ($language !== '' && in_array($language, BackLang::getAvailableLangKeys(), true)) {
            return $language;
        }

        try {
            if (app()->bound('request')) {
                return getCurrentLanguage(request());
            }
        } catch (\Throwable) {
        }

        $appLocale = strtolower((string) app()->getLocale());

        return in_array($appLocale, BackLang::getAvailableLangKeys(), true) ? $appLocale : ENGLISH_LANG_KEY;
    }
}
