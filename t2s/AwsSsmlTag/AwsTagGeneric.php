<?php

namespace TextToSpeech\ExternalDataAssembler\AwsSsmlTag;

class AwsTagGeneric
{
    public const TAG_ORDINAL = 'ordinal';
    public const TAG_CHARACTERS = 'characters';
    public const TAG_SPELL_OUT = 'spell-out';
    public const TAG_DIGITS = 'digits';
    public const TAG_FRACTIONS = 'fraction';

    /**
     * @param string $tagType
     * @param string $value
     * @return string
     */
    public static function render(string $tagType, string $value): string
    {
        return sprintf('<say-as interpret-as="%s">%s</say-as>', $tagType, $value);
    }
}
