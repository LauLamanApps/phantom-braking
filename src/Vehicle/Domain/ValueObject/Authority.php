<?php

declare(strict_types=1);

namespace App\Vehicle\Domain\ValueObject;

/**
 * The authority a reporter can also report a dangerous vehicle to, by the country where the car is registered
 * (see Country::authority()). Its texts live under the translation key "authority.<value>": names take a different
 * article per authority and language, so each authority has its own sentences.
 */
enum Authority: string
{
    /** Inspectie Leefomgeving en Transport (NL). */
    case Ilt = 'ilt';

    public function translationKey(): string
    {
        return 'authority.'.$this->value;
    }

    /** Where the reporter starts. */
    public function formUrl(): string
    {
        return match ($this) {
            // The e-loket form itself can't be linked: without a session started from ilent.nl it shows "Sessie verlopen".
            self::Ilt => 'https://www.ilent.nl/meldformulieren',
        };
    }

    /**
     * Languages the authority reads; the text to copy is offered in these.
     *
     * @return non-empty-list<string>
     */
    public function textLocales(): array
    {
        return match ($this) {
            self::Ilt => ['nl', 'en'],
        };
    }

    /**
     * The steps from formUrl() to pasting the text, as keys under "<translationKey>.form_steps".
     *
     * @return list<string>
     */
    public function formSteps(): array
    {
        return match ($this) {
            self::Ilt => ['open', 'topic', 'form', 'paste'],
        };
    }
}
