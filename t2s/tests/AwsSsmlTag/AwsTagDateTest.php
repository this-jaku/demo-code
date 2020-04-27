<?php

namespace TextToSpeech\AwsSsmlTag;

use TextToSpeech\Exception\TextToSpeechExternalDataAssemblerException;
use TextToSpeech\ExternalDataAssembler\AwsSsmlTag\AwsTagDate;
use PHPUnit\Framework\TestCase;

class AwsTagDateTest extends TestCase
{
    public function dataProvider(): array
    {
        $feed = [];
        $feed[] = ['2020-01-21', 'pl-PL', 'd', '<say-as interpret-as="ordinal">21</say-as>'];
        $feed[] = ['2020-01-21', 'pl-PL', 'y', '<say-as interpret-as="ordinal">2020</say-as>'];
        $feed[] = ['2020-01-21', 'pl-PL', 'm', '<say-as interpret-as="ordinal">1</say-as>'];
        $feed[] = ['2020-01-21', 'pl-PL', 'M', 'stycznia'];
        $feed[] = ['2020-01-21', 'pl-PL', 'D', 'wtorek'];

        $feed[] = [
            '2020-01-21',
            'pl-PL',
            'DdMy',
            'wtorek <say-as interpret-as="ordinal">21</say-as> stycznia <say-as interpret-as="ordinal">2020</say-as>'
        ];

        $feed[] = ['2020-01-21', 'pl-PL', 'nieznany format', '', TextToSpeechExternalDataAssemblerException::class];

        return $feed;
    }

    /**
     * @dataProvider dataProvider
     * @param string $value
     * @param string $langCode
     * @param string $format
     * @param string $expectedResult
     * @param string|null $expectedExceptionClass
     * @throws TextToSpeechExternalDataAssemblerException
     */
    public function testRender(
        string $value,
        string $langCode,
        ?string $format,
        string $expectedResult,
        string $expectedExceptionClass = null
    ) {
        if ($expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        $result = AwsTagDate::render($value, $langCode, $format);

        $this->assertEquals($expectedResult, $result);
    }
}
