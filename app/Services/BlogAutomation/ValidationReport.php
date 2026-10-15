<?php

namespace App\Services\BlogAutomation;

/** Result of ArticleValidator: errors block auto-publication, warnings inform the reviewer. */
class ValidationReport
{
    /** @var string[] */
    public array $errors = [];

    /** @var string[] */
    public array $warnings = [];

    public int $wordCount = 0;

    /** The article states legal/tax rules (topic flag or detected wording). */
    public bool $regulatory = false;

    /** An official source was both consulted (grounding) and cited in the text. */
    public bool $sourcesVerified = false;

    /** @var string[] official URLs cited in the article */
    public array $officialCitations = [];

    public function error(string $message): void
    {
        if (! in_array($message, $this->errors, true)) {
            $this->errors[] = $message;
        }
    }

    public function warning(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
