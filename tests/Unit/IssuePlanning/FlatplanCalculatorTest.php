<?php

namespace Tests\Unit\IssuePlanning;

use App\Support\IssuePlanning\FlatplanCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlatplanCalculatorTest extends TestCase
{
    #[DataProvider('pageCountProvider')]
    public function test_it_builds_reading_order_for_any_page_count(int $pageCount, array $expected): void
    {
        self::assertSame($expected, FlatplanCalculator::spreads($pageCount));
    }

    public static function pageCountProvider(): array
    {
        return [
            'one page' => [1, [['left' => null, 'right' => 1]]],
            'four pages' => [4, [
                ['left' => null, 'right' => 1],
                ['left' => 2, 'right' => 3],
                ['left' => 4, 'right' => null],
            ]],
            'five pages' => [5, [
                ['left' => null, 'right' => 1],
                ['left' => 2, 'right' => 3],
                ['left' => 4, 'right' => 5],
            ]],
            'eight pages' => [8, [
                ['left' => null, 'right' => 1],
                ['left' => 2, 'right' => 3],
                ['left' => 4, 'right' => 5],
                ['left' => 6, 'right' => 7],
                ['left' => 8, 'right' => null],
            ]],
            'twenty-two pages' => [22, [
                ['left' => null, 'right' => 1],
                ['left' => 2, 'right' => 3],
                ['left' => 4, 'right' => 5],
                ['left' => 6, 'right' => 7],
                ['left' => 8, 'right' => 9],
                ['left' => 10, 'right' => 11],
                ['left' => 12, 'right' => 13],
                ['left' => 14, 'right' => 15],
                ['left' => 16, 'right' => 17],
                ['left' => 18, 'right' => 19],
                ['left' => 20, 'right' => 21],
                ['left' => 22, 'right' => null],
            ]],
            'thirty-two pages' => [32, [
                ['left' => null, 'right' => 1],
                ['left' => 2, 'right' => 3],
                ['left' => 4, 'right' => 5],
                ['left' => 6, 'right' => 7],
                ['left' => 8, 'right' => 9],
                ['left' => 10, 'right' => 11],
                ['left' => 12, 'right' => 13],
                ['left' => 14, 'right' => 15],
                ['left' => 16, 'right' => 17],
                ['left' => 18, 'right' => 19],
                ['left' => 20, 'right' => 21],
                ['left' => 22, 'right' => 23],
                ['left' => 24, 'right' => 25],
                ['left' => 26, 'right' => 27],
                ['left' => 28, 'right' => 29],
                ['left' => 30, 'right' => 31],
                ['left' => 32, 'right' => null],
            ]],
        ];
    }

    public function test_it_places_fractional_entries_in_distinct_segments_of_one_page(): void
    {
        $segments = FlatplanCalculator::segments(4, [
            $this->entry(1, 1, 0, 0.5),
            $this->entry(2, 1, 0.5, 0.5),
            $this->entry(3, 2, 0, 0.33),
            $this->entry(4, 2, 0.33, 0.67),
        ]);

        self::assertSame([
            ['entry_id' => 1, 'offset' => 0.0, 'extent' => 0.5],
            ['entry_id' => 2, 'offset' => 0.5, 'extent' => 0.5],
        ], $segments[1]);
        self::assertSame([
            ['entry_id' => 3, 'offset' => 0.0, 'extent' => 0.33],
            ['entry_id' => 4, 'offset' => 0.33, 'extent' => 0.67],
        ], $segments[2]);
    }

    public function test_it_splits_a_multi_page_entry_into_page_local_segments(): void
    {
        $segments = FlatplanCalculator::segments(5, [
            $this->entry(4, 2, 0.25, 1.5),
        ]);

        self::assertSame(['entry_id' => 4, 'offset' => 0.25, 'extent' => 0.75], $segments[2][0]);
        self::assertSame(['entry_id' => 4, 'offset' => 0.0, 'extent' => 0.75], $segments[3][0]);
        self::assertArrayNotHasKey(4, $segments);
    }

    public function test_it_reports_overlapping_fractional_entries(): void
    {
        $conflicts = FlatplanCalculator::conflicts(4, [
            $this->entry(1, 2, 0, 0.6),
            $this->entry(2, 2, 0.5, 0.5),
        ]);

        self::assertSame([[
            'entry_ids' => [1, 2],
            'page' => 2,
            'reason' => 'overlap',
        ]], $conflicts);
    }

    public function test_it_reports_page_overflow_and_invalid_true_spreads(): void
    {
        $conflicts = FlatplanCalculator::conflicts(4, [
            $this->entry(1, 4, 0.75, 0.5),
            $this->entry(2, 3, 0, 2, 'spread'),
        ]);

        self::assertSame([
            ['entry_ids' => [2], 'page' => 3, 'reason' => 'invalid_spread'],
            ['entry_ids' => [1], 'page' => 5, 'reason' => 'outside_issue'],
        ], $conflicts);
    }

    public function test_it_reports_zero_spans_and_offsets_outside_the_page(): void
    {
        $conflicts = FlatplanCalculator::conflicts(4, [
            $this->entry(1, 1, 0, 0),
            $this->entry(2, 1, 1, 0.25),
        ]);

        self::assertSame([
            ['entry_ids' => [1], 'page' => 1, 'reason' => 'invalid_extent'],
            ['entry_ids' => [2], 'page' => 1, 'reason' => 'invalid_offset'],
        ], $conflicts);
    }

    public function test_it_summarizes_placed_reserved_free_and_ready_capacity_separately(): void
    {
        $summary = FlatplanCalculator::summarize(8, [
            $this->entry(1, 2, 0, 1.5, 'normal', 'planned', 'layout_ready', 'published'),
            $this->entry(2, null, 0, 2),
            $this->entry(3, null, 0, 0.75, 'normal', 'ready', 'planned'),
            $this->entry(4, 6, 0, 0.5, 'normal', 'planned', 'planned', 'review'),
        ]);

        self::assertSame(8.0, $summary['total_pages']);
        self::assertSame(2.0, $summary['placed_pages']);
        self::assertSame(2.75, $summary['unplaced_reserved_pages']);
        self::assertSame(3.25, $summary['remaining_pages']);
        self::assertSame(0.0, $summary['overbooked_pages']);
        self::assertSame(1, $summary['editorially_ready_entries']);
        self::assertSame(2, $summary['print_ready_pages']);
    }

    public function test_editorial_completion_uses_the_issue_planning_status_for_linked_articles(): void
    {
        $summary = FlatplanCalculator::summarize(4, [
            $this->entry(1, 2, 0, 1, 'normal', 'ready', 'planned', 'draft'),
        ]);

        self::assertSame(1, $summary['editorially_ready_entries']);
    }

    public function test_it_reports_reserved_capacity_overbooking(): void
    {
        $summary = FlatplanCalculator::summarize(4, [
            $this->entry(1, 1, 0, 3),
            $this->entry(2, null, 0, 2),
        ]);

        self::assertSame(0.0, $summary['remaining_pages']);
        self::assertSame(1.0, $summary['overbooked_pages']);
    }

    private function entry(
        int $id,
        ?int $pageStart,
        float $offset,
        float $span,
        string $placementType = 'normal',
        string $planningStatus = 'planned',
        string $printStatus = 'planned',
        ?string $articleStatus = null,
    ): array {
        return [
            'id' => $id,
            'page_start' => $pageStart,
            'page_offset' => $offset,
            'page_span' => $span,
            'placement_type' => $placementType,
            'planning_status' => $planningStatus,
            'print_status' => $printStatus,
            'article_status' => $articleStatus,
        ];
    }
}
