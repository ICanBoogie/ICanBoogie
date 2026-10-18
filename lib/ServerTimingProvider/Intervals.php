<?php

namespace ICanBoogie\ServerTimingProvider;

use function usort;

/**
 * Helper to measure records that can nest, such as listeners emitting events.
 */
final class Intervals
{
    /**
     * Returns the length of the union of intervals, so nested work is counted once.
     *
     * @param iterable<array{ float, float }> $intervals
     *     Where each interval is a start time and a duration, in seconds.
     *
     * @return float The length in seconds with microsecond precision.
     */
    public static function union_length(iterable $intervals): float
    {
        $sorted = [];

        foreach ($intervals as [ $start, $duration ]) {
            $sorted[] = [ $start, $start + $duration ];
        }

        // Records are added when the work ends, so inner work comes before the work that nests it.
        usort($sorted, fn(array $a, array $b) => $a[0] <=> $b[0]);

        $length = 0.0;
        $end = null;

        foreach ($sorted as [ $interval_start, $interval_end ]) {
            if ($end === null || $interval_start > $end) {
                $length += $interval_end - $interval_start;
                $end = $interval_end;
            } elseif ($interval_end > $end) {
                $length += $interval_end - $end;
                $end = $interval_end;
            }
        }

        return $length;
    }
}
