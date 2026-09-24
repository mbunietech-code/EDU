<?php

namespace Tests\Feature;

use App\Support\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LanguageDetectionTest extends TestCase
{
    #[DataProvider('samples')]
    public function test_detects_the_language_a_message_was_written_in(string $text, ?string $expected): void
    {
        $this->assertSame($expected, Language::detect($text));
    }

    public static function samples(): array
    {
        return [
            'swahili question' => ['ni orders ngapi leo', 'sw'],
            'swahili with loan words' => ['nionyeshe malipo yaliyokubaliwa', 'sw'],
            'swahili greeting' => ['Habari, naomba msaada', 'sw'],
            'english question' => ['how many users do we have today', 'en'],
            'english request' => ['show me the payments please', 'en'],
            'english waiting' => ['Hello, I am still waiting for a reply', 'en'],
            'shared word only' => ['orders', null],
            'button label' => ['Database health', null],
            'gibberish' => ['asdkjasd', null],
            'empty' => ['', null],
        ];
    }
}
