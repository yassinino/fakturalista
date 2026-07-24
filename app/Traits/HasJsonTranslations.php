<?php

namespace App\Traits;

trait HasJsonTranslations
{
    /**
     * Get a translated value for the given field and locale.
     * Falls back to 'fr' then 'en' if the requested locale is missing.
     */
    public function translate(string $field, ?string $locale = null): string
    {
        $locale        = $locale ?? app()->getLocale();
        $raw           = $this->getRawOriginal($field) ?? '{}';
        $translations  = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;

        return $translations[$locale]
            ?? $translations['fr']
            ?? $translations['en']
            ?? '';
    }

    /**
     * Get all translations for a field as an associative array.
     */
    public function getTranslations(string $field): array
    {
        $raw = $this->getRawOriginal($field) ?? '{}';
        return is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    }

    /**
     * Set a single locale translation and persist the full JSON object.
     */
    public function setTranslation(string $field, string $locale, string $value): static
    {
        $translations         = $this->getTranslations($field);
        $translations[$locale] = $value;
        $this->attributes[$field] = json_encode($translations, JSON_UNESCAPED_UNICODE);
        return $this;
    }
}
