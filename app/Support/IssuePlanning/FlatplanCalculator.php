<?php

namespace App\Support\IssuePlanning;

final class FlatplanCalculator
{
    private const EPSILON = 0.000001;

    /**
     * @return array<int, array{left: ?int, right: ?int}>
     */
    public static function spreads(int $pageCount): array
    {
        if ($pageCount < 1) {
            return [];
        }

        $spreads = [['left' => null, 'right' => 1]];

        for ($leftPage = 2; $leftPage <= $pageCount; $leftPage += 2) {
            $spreads[] = [
                'left' => $leftPage,
                'right' => $leftPage + 1 <= $pageCount ? $leftPage + 1 : null,
            ];
        }

        return $spreads;
    }

    /**
     * @param  iterable<array<string, mixed>>  $entries
     * @return array<int, array<int, array{entry_id: int, offset: float, extent: float}>>
     */
    public static function segments(int $pageCount, iterable $entries): array
    {
        $segments = [];

        foreach ($entries as $entry) {
            $pageStart = self::nullableInt($entry['page_start'] ?? null);
            $pageOffset = self::number($entry['page_offset'] ?? 0);
            $pageSpan = self::number($entry['page_span'] ?? 0);

            if ($pageStart === null || ! is_finite($pageSpan) || ! is_finite($pageOffset)
                || $pageSpan <= 0 || $pageOffset < 0 || $pageOffset >= 1) {
                continue;
            }

            $entryId = (int) ($entry['id'] ?? 0);
            $page = $pageStart;
            $offset = $pageOffset;
            $remaining = $pageSpan;

            while ($remaining > self::EPSILON) {
                $extent = min(1 - $offset, $remaining);

                if ($extent <= self::EPSILON) {
                    break;
                }

                $segments[$page][] = [
                    'entry_id' => $entryId,
                    'offset' => self::rounded($offset),
                    'extent' => self::rounded($extent),
                ];

                $remaining -= $extent;
                $page++;
                $offset = 0.0;
            }
        }

        foreach ($segments as &$pageSegments) {
            usort($pageSegments, static fn (array $left, array $right): int =>
                ($left['offset'] <=> $right['offset']) ?: ($left['entry_id'] <=> $right['entry_id'])
            );
        }
        unset($pageSegments);

        return $segments;
    }

    /**
     * @param  iterable<array<string, mixed>>  $entries
     * @return array<int, array{entry_ids: array<int, int>, page: ?int, reason: string}>
     */
    public static function conflicts(int $pageCount, iterable $entries): array
    {
        $entries = is_array($entries) ? array_values($entries) : iterator_to_array($entries, false);
        $conflicts = [];
        $validEntries = [];

        foreach ($entries as $entry) {
            $entryId = (int) ($entry['id'] ?? 0);
            $pageStart = self::nullableInt($entry['page_start'] ?? null);
            $pageOffset = self::number($entry['page_offset'] ?? 0);
            $pageSpan = self::number($entry['page_span'] ?? 0);
            $placementType = (string) ($entry['placement_type'] ?? 'normal');

            if (! is_finite($pageSpan) || $pageSpan <= 0) {
                $conflicts[] = ['entry_ids' => [$entryId], 'page' => $pageStart, 'reason' => 'invalid_extent'];
                continue;
            }

            if (! is_finite($pageOffset) || $pageOffset < 0 || $pageOffset >= 1) {
                $conflicts[] = ['entry_ids' => [$entryId], 'page' => $pageStart, 'reason' => 'invalid_offset'];
                continue;
            }

            if ($pageStart !== null && $pageStart < 1) {
                $conflicts[] = ['entry_ids' => [$entryId], 'page' => $pageStart, 'reason' => 'invalid_position'];
                continue;
            }

            if ($placementType === 'spread'
                && ($pageStart === null || $pageStart < 2 || $pageStart % 2 !== 0
                    || abs($pageOffset) > self::EPSILON || abs($pageSpan - 2) > self::EPSILON)) {
                $conflicts[] = ['entry_ids' => [$entryId], 'page' => $pageStart, 'reason' => 'invalid_spread'];
                continue;
            }

            $validEntries[] = $entry;
        }

        $segments = self::segments($pageCount, $validEntries);

        foreach ($segments as $page => $pageSegments) {
            if ($page > $pageCount) {
                foreach ($pageSegments as $segment) {
                    $conflicts[] = [
                        'entry_ids' => [$segment['entry_id']],
                        'page' => $page,
                        'reason' => 'outside_issue',
                    ];
                }

                continue;
            }

            for ($index = 0, $count = count($pageSegments); $index < $count; $index++) {
                $left = $pageSegments[$index];
                $leftEnd = $left['offset'] + $left['extent'];

                for ($next = $index + 1; $next < $count; $next++) {
                    $right = $pageSegments[$next];

                    if ($right['offset'] >= $leftEnd - self::EPSILON) {
                        break;
                    }

                    if ($left['entry_id'] !== $right['entry_id']) {
                        $conflicts[] = [
                            'entry_ids' => [$left['entry_id'], $right['entry_id']],
                            'page' => $page,
                            'reason' => 'overlap',
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  iterable<array<string, mixed>>  $entries
     * @return array<string, int|float>
     */
    public static function summarize(int $pageCount, iterable $entries): array
    {
        $entries = is_array($entries) ? array_values($entries) : iterator_to_array($entries, false);
        $placed = 0.0;
        $unplacedReserved = 0.0;
        $editoriallyReady = 0;
        $printReadyPages = [];
        $segments = self::segments($pageCount, $entries);
        $printReadyStatuses = ['layout_ready', 'proofing', 'approved'];

        foreach ($entries as $entry) {
            $span = max(0.0, self::number($entry['page_span'] ?? 0));

            if (self::nullableInt($entry['page_start'] ?? null) === null) {
                $unplacedReserved += $span;
            } else {
                $placed += $span;
            }

            if (($entry['planning_status'] ?? null) === 'ready') {
                $editoriallyReady++;
            }

            if (in_array($entry['print_status'] ?? null, $printReadyStatuses, true)) {
                foreach ($segments as $page => $pageSegments) {
                    foreach ($pageSegments as $segment) {
                        if ($segment['entry_id'] === (int) ($entry['id'] ?? 0) && $page <= $pageCount) {
                            $printReadyPages[$page] = true;
                            break;
                        }
                    }
                }
            }
        }

        $planned = $placed + $unplacedReserved;

        return [
            'total_pages' => (float) $pageCount,
            'placed_pages' => self::rounded($placed),
            'unplaced_reserved_pages' => self::rounded($unplacedReserved),
            'remaining_pages' => self::rounded(max(0.0, $pageCount - $planned)),
            'overbooked_pages' => self::rounded(max(0.0, $planned - $pageCount)),
            'editorially_ready_entries' => $editoriallyReady,
            'print_ready_pages' => count($printReadyPages),
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : NAN;
    }

    private static function rounded(float $value): float
    {
        return round($value, 2);
    }
}
