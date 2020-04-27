<?php

namespace TextToSpeech\ExternalDataAssembler\AwsSsmlTag;

use TextToSpeech\Exception\TextToSpeechExternalDataAssemblerException;

class AwsTagLang
{
    public const TAG_LANG = 'lang';
    public const ALLOWED_LANGUAGES = [
        'arb',
        'cmn-CN',
        'cy-GB',
        'da-DK',
        'de-DE',
        'en-AU',
        'en-GB',
        'en-GB-WLS',
        'en-IN',
        'en-US',
        'es-ES',
        'es-MX',
        'es-US',
        'fr-CA',
        'fr-FR',
        'is-IS',
        'it-IT',
        'ja-JP',
        'hi-IN',
        'ko-KR',
        'nb-NO',
        'nl-NL',
        'pl-PL',
        'pt-BR',
        'pt-PT',
        'ro-RO',
        'ru-RU',
        'sv-SE',
        'tr-TR'
    ];

    /**
     * @param string $value
     * @param string $langCode
     * @return string
     * @throws TextToSpeechExternalDataAssemblerException
     */
    public static function render(string $value, string $langCode): string
    {
        if (!in_array($langCode, self::ALLOWED_LANGUAGES)) {
            throw new TextToSpeechExternalDataAssemblerException(
                'Nieobsługiwany język. Zweryfikować poprawność znacznika w zapowiedzi.'
            );
        }

        return sprintf('<lang xml:lang="%s">%s</lang>', $langCode, $value);
    }
}
