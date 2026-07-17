<?php

namespace Tests\Unit;

use App\Services\Ai\AiWeekPlanParser;
use InvalidArgumentException;
use Tests\TestCase;

class AiWeekPlanParserTest extends TestCase
{
    public function test_parses_plain_json(): void
    {
        $dates = ['2026-07-20', '2026-07-21'];
        $raw = json_encode([
            'days' => [
                [
                    'date' => '2026-07-20',
                    'slots' => [
                        'breakfast' => [['label' => 'Avoine', 'quantity_g' => 80]],
                        'lunch' => [],
                        'dinner' => [['label' => 'Poulet', 'quantity_g' => 150]],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
                [
                    'date' => '2026-07-21',
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Salade', 'quantity_g' => 200]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
            ],
        ]);

        $parsed = app(AiWeekPlanParser::class)->parse($raw, $dates);

        $this->assertCount(2, $parsed['days']);
        $this->assertSame('Avoine', $parsed['days'][0]['slots']['breakfast'][0]['label']);
        $this->assertSame(80.0, $parsed['days'][0]['slots']['breakfast'][0]['quantity_g']);
    }

    public function test_parses_markdown_fenced_json(): void
    {
        $raw = <<<'MD'
Voici le plan :
```json
{"days":[{"date":"2026-07-20","slots":{"breakfast":[{"label":"Yaourt","quantity_g":120}],"lunch":[],"dinner":[],"morning_snack":[],"afternoon_snack":[],"night_snack":[]}}]}
```
MD;

        $parsed = app(AiWeekPlanParser::class)->parse($raw, ['2026-07-20']);

        $this->assertSame('Yaourt', $parsed['days'][0]['slots']['breakfast'][0]['label']);
    }

    public function test_rejects_missing_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AiWeekPlanParser::class)->parse(
            '{"days":[{"date":"2026-07-20","slots":{"breakfast":[],"lunch":[],"dinner":[],"morning_snack":[],"afternoon_snack":[],"night_snack":[]}}]}',
            ['2026-07-20', '2026-07-21'],
        );
    }

    public function test_rejects_empty_response(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AiWeekPlanParser::class)->parse('   ');
    }
}
