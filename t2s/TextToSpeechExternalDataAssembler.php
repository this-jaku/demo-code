<?php

namespace TextToSpeech\ExternalDataAssembler;

use TextToSpeech\Exception\TextToSpeechExternalDataAssemblerException;
use TextToSpeech\Exception\TextToSpeechExternalDataValueNotFoundException;
use TextToSpeech\ExternalDataAssembler\AwsSsmlTag as Tag;

/**
 * @see https://docs.aws.amazon.com/polly/latest/dg/supportedtags.html
 */
class TextToSpeechExternalDataAssembler
{
    private const MARKER_KEY_FROM_TAG_FORMAT_SEPARATOR = '|';
    private const MARKER_PARTS_SEPARATOR = ':';

    /**
     * @param string $marker
     * @param array $externalData
     * @return string|null
     * @throws TextToSpeechExternalDataAssemblerException
     * @throws TextToSpeechExternalDataValueNotFoundException
     */
    public function findValue(string $marker, array $externalData): ?string
    {
        $marker = $this->stripMarkerFromBrackets($marker);
        $markerKey = explode(self::MARKER_KEY_FROM_TAG_FORMAT_SEPARATOR, $marker)[0];
        $markerKeyParts = explode(self::MARKER_PARTS_SEPARATOR, $markerKey);

        if (count($markerKeyParts) === 0) {
            throw new TextToSpeechExternalDataAssemblerException('Pusty klucz/nazwa markera. Należy poprawić znacznik w zapowiedzi.');
        }

        $child = $externalData;
        foreach ($markerKeyParts as $keyPart) {
            if (!array_key_exists($keyPart, $child)) {
                throw new TextToSpeechExternalDataValueNotFoundException(
                    'Zweryfikować poprawność znacznika w zapowiedzi, oraz kontrakt zwracany przez zewnętrzne API.'
                );
            }
            $child = $child[$keyPart];
        }

        if (!is_numeric($child) && !is_string($child) && !is_null($child)) {
            throw new TextToSpeechExternalDataAssemblerException(
                'Niepoprawna wartość dla danego klucza/nazwy. Dopuszczalne text, numer, null. 
            Zweryfikować poprawność znacznika w zapowiedzi, oraz kontrakt zwracany przez zewnętrzne API.'
            );
        }

        return $child ? (string)$child : null;
    }

    /**
     * @param string $marker
     * @param string $value
     * @return string
     * @throws TextToSpeechExternalDataAssemblerException
     */
    public function decorateValueWithDedicatedTag(string $marker, string $value): string
    {
        $marker = $this->stripMarkerFromBrackets($marker);
        $markerTags = explode(self::MARKER_KEY_FROM_TAG_FORMAT_SEPARATOR, $marker);
        array_shift($markerTags); // usunięcie pierwszego bloku ze ścieżką do wartości, pozostawienie tylko bloku formatowania taga

        if (count($markerTags) > 1) {
            throw  new TextToSpeechExternalDataAssemblerException(
                'Nie można formatować znacznika więcej niż jednym typem. Zweryfikować poprawność znacznika w zapowiedzi.'
            );
        }

        if (count($markerTags) === 1) {
            $tagParts = explode(self::MARKER_PARTS_SEPARATOR, array_pop($markerTags));
            $tagType = $tagParts[0];

            switch ($tagType) {
                case Tag\AwsTagDate::TAG_DATE:
                    $langCode = (!empty($tagParts[1])) ? (string)$tagParts[1] : '';
                    $dateFormat = (!empty($tagParts[2])) ? (string)$tagParts[2] : Tag\AwsTagDate::DEFAULT_DATE_FORMAT;
                    $value = Tag\AwsTagDate::render($value, $langCode, $dateFormat);
                    break;
                case Tag\AwsTagLang::TAG_LANG:
                    $langCode = (!empty($tagParts[1])) ? (string)$tagParts[1] : '';
                    $value = Tag\AwsTagLang::render($value, $langCode);
                    break;
                case Tag\AwsTagGeneric::TAG_CHARACTERS:
                case Tag\AwsTagGeneric::TAG_SPELL_OUT:
                case Tag\AwsTagGeneric::TAG_ORDINAL:
                case Tag\AwsTagGeneric::TAG_DIGITS:
                case Tag\AwsTagGeneric::TAG_FRACTIONS:
                    $value = Tag\AwsTagGeneric::render($tagType, $value);
                    break;
                default:
                    throw  new TextToSpeechExternalDataAssemblerException(
                        'Nieobsługiwany typ taga. Zweryfikować poprawność znacznika w zapowiedzi.'
                    );
            }
        }

        return $value;
    }

    private function stripMarkerFromBrackets(string $marker): string
    {
        $marker = ltrim($marker, '[');
        return rtrim($marker, ']');
    }
}
