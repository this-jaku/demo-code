<?php

namespace TextToSpeech;

use TextToSpeech\Exception\TextToSpeechExternalDataAssemblerException;
use TextToSpeech\Exception\TextToSpeechExternalDataValueNotFoundException;
use TextToSpeech\ExternalDataAssembler\TextToSpeechExternalDataAssembler;
use PHPUnit\Framework\TestCase;

class TextToSpeechExternalDataAssemblerTest extends TestCase
{
    /** @var TextToSpeechExternalDataAssembler */
    private $textToSpeechExternalDataAssembler;

    public function setUp()
    {
        parent::setUp();
        $this->textToSpeechExternalDataAssembler = new TextToSpeechExternalDataAssembler();
    }

    public function findValueDataProvider(): array
    {
        $feed = [];

        // znajduje wartość w płytkim zagłębieniu
        $feed[] = [
            '[API:tutaj_wartosc]',
            [
                'API' => [
                    'tutaj_wartosc' => 'jam jest wartością'
                ]
            ],
            'jam jest wartością'
        ];

        // znajduje wartość w zagłębieniu
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'tutaj_wartosc' => 'jam jest wartością'
                        ]
                    ]
                ]
            ],
            'jam jest wartością'
        ];

        // znajduje wartość w zagłębieniu, dodanie definicji taga nie wpływa na wynik
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc|lang:en_US]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'tutaj_wartosc' => 'jam jest wartością'
                        ]
                    ]
                ]
            ],
            'jam jest wartością'
        ];

        // znajduje wartość integer
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'tutaj_wartosc' => 123456
                        ]
                    ]
                ]
            ],
            123456
        ];

        // znajduje wartość float
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'tutaj_wartosc' => 123.456
                        ]
                    ]
                ]
            ],
            123.456
        ];

        // znajduje wartość float
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'tutaj_wartosc' => null
                        ]
                    ]
                ]
            ],
            null
        ];

        // znajduje wartość pod indeksem 1
        $feed[] = [
            '[API:klucz:1]',
            [
                'API' => [
                    'klucz' => [
                        'wiersz 0 ten nas nie interesuje',
                        'jam jest wartością'
                    ]
                ]
            ],
            'jam jest wartością'
        ];

        // znajduje wartość w strukturze pod indeksem 1
        $feed[] = [
            '[API:klucz:1:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        ['wiersz 0' => 'ten nas nie interesuje'],
                        ['tutaj_wartosc' => 'jam jest wartością']
                    ]
                ]
            ],
            'jam jest wartością'
        ];

        // nie znajduje wartości w zagłębieniu
        // API nie dostaczyło szukanego zagłębienia,
        // lub marker wsazuje na pole które nie jest zwracane przez API
        $feed[] = [
            '[API:klucz:dalej_klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'dalej_klucz' => [
                            'inny klucz' => 'jam jest wartością'
                        ]
                    ]
                ]
            ],
            'jam jest wartością',
            TextToSpeechExternalDataValueNotFoundException::class
        ];

        // nie znajduje wartości pod indeksem 1
        $feed[] = [
            '[API:klucz:1]',
            [
                'API' => [
                    'klucz' => [
                        'jam jest wartością'
                    ]
                ]
            ],
            'jam jest wartością',
            TextToSpeechExternalDataValueNotFoundException::class
        ];

        // nie znajduje wartościw strukturze pod indeksem 1
        $feed[] = [
            '[API:klucz:1:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        ['tutaj_wartosc' => 'jam jest wartością']
                    ]
                ]
            ],
            'jam jest wartością',
            TextToSpeechExternalDataValueNotFoundException::class
        ];

        // pusta ścieżka markera
        $feed[] = [
            '[API]',
            [
                'tutaj_wartosc' => 'jam jest wartością'
            ],
            'jam jest wartością',
            TextToSpeechExternalDataAssemblerException::class
        ];

        // pusta ścieżka markera
        $feed[] = [
            '',
            [
                'tutaj_wartosc' => 'jam jest wartością'
            ],
            'jam jest wartością',
            TextToSpeechExternalDataAssemblerException::class
        ];

        // znajduje wartość array, która nie jest dopuszczalna
        $feed[] = [
            '[API:klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'tutaj_wartosc' => ['jak AWS ma obsłużyć tablicę ziom?']
                    ]
                ]
            ],
            ['jak AWS ma obsłużyć tablicę ziom?'],
            TextToSpeechExternalDataAssemblerException::class
        ];

        // znajduje wartość boolean, który nie jest dopuszczalny
        $feed[] = [
            '[API:klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'tutaj_wartosc' => false
                    ]
                ]
            ],
            false,
            TextToSpeechExternalDataAssemblerException::class
        ];

        // znajduje wartość boolean, który nie jest dopuszczalny
        $feed[] = [
            '[API:klucz:tutaj_wartosc]',
            [
                'API' => [
                    'klucz' => [
                        'tutaj_wartosc' => true
                    ]
                ]
            ],
            true,
            TextToSpeechExternalDataAssemblerException::class
        ];

        return $feed;
    }

    /**
     * @dataProvider findValueDataProvider
     * @param string $marker
     * @param array $externalData
     * @param mixed $expectedResult
     * @param string $expectedExceptionClass
     * @throws Exception\TextToSpeechExternalDataAssemblerException
     * @throws Exception\TextToSpeechExternalDataValueNotFoundException
     */
    public function testFindValues(
        string $marker,
        array $externalData,
        $expectedResult,
        string $expectedExceptionClass = null
    ) {
        if ($expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }
        $result = $this->textToSpeechExternalDataAssembler->findValue($marker, $externalData);

        $this->assertEquals($expectedResult, $result);
    }

    public function decorateValueWithDedicatedTagDataProvider(): array
    {
        //bez formatu
        $feed[] = [
            '[API:klucz:tutaj_wartosc|spell-out]',
            'hakuna matata',
            '<say-as interpret-as="spell-out">hakuna matata</say-as>'
        ];

        //z formatem
        $feed[] = [
            '[API:klucz:tutaj_wartosc|lang:en-US]',
            'hakuna matata',
            '<lang xml:lang="en-US">hakuna matata</lang>'
        ];

        //nieobsługiwany tag
        $feed[] = [
            '[API:klucz:tutaj_wartosc|hakunaMatata]',
            '',
            '',
            TextToSpeechExternalDataAssemblerException::class
        ];

        return $feed;
    }

    /**
     * @dataProvider decorateValueWithDedicatedTagDataProvider
     * @param string $marker
     * @param string $value
     * @param $expectedResult
     * @param string|null $expectedExceptionClass
     * @throws TextToSpeechExternalDataAssemblerException
     */
    public function testDecorateValueWithDedicatedTag(
        string $marker,
        string $value,
        $expectedResult,
        string $expectedExceptionClass = null
    ) {
        if ($expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }
        $result = $this->textToSpeechExternalDataAssembler->decorateValueWithDedicatedTag($marker, $value);
        $this->assertEquals($expectedResult, $result);
    }
}
