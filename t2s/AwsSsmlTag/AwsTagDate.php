<?php

namespace TextToSpeech\ExternalDataAssembler\AwsSsmlTag;

use TextToSpeech\Exception\TextToSpeechExternalDataAssemblerException;

class AwsTagDate
{
    public const TAG_DATE = 'date';
    public const DEFAULT_DATE_FORMAT = 'dFY';
    public const ALLOWED_DATE_FORMATS_TO_PHP_MAPPING = [
        'd' => 'j',
        'D' => 'EEEE',
        'm' => 'n',
        'M' => 'MMMM',
        'y' => 'Y',
    ];

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
     * @param string $langCode LCID pl-PL
     * @param string $dateFormat
     * @return string
     * @throws TextToSpeechExternalDataAssemblerException
     */
    public static function render(string $value, string $langCode, string $dateFormat): string
    {
        $dateTime = new \DateTime($value);

        if (!in_array($langCode, self::ALLOWED_LANGUAGES)) {
            throw new TextToSpeechExternalDataAssemblerException(
                'Nieobsługiwany kod języka. Zweryfikować poprawność znacznika w zapowiedzi.'
            );
        }

        $dateFormatArray = str_split($dateFormat);

        $resultDateParts = [];
        foreach ($dateFormatArray as $formatElement) {
            if (!array_key_exists($formatElement, self::ALLOWED_DATE_FORMATS_TO_PHP_MAPPING)) {
                throw new TextToSpeechExternalDataAssemblerException(
                    'Nieobsługiwany format daty. Zweryfikować poprawność znacznika w zapowiedzi.'
                );
            }

            switch ($formatElement) {
                case 'd':
                case 'm':
                case 'y':
                    $phpDateFormat = self::ALLOWED_DATE_FORMATS_TO_PHP_MAPPING[$formatElement];
                    $resultDateParts[] = sprintf(
                        '<say-as interpret-as="ordinal">%s</say-as>',
                        $dateTime->format($phpDateFormat)
                    );
                    break;
                case 'D':
                case 'M':
                    $intlDateFormat = self::ALLOWED_DATE_FORMATS_TO_PHP_MAPPING[$formatElement];
                    $formatter = new \IntlDateFormatter($langCode, null, null);
                    $formatter->setPattern($intlDateFormat);
                    $resultDateParts[] = $formatter->format($dateTime);
                    break;
            }
        }

        return implode(' ', $resultDateParts);
    }
}
