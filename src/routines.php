<?php
declare(strict_types=1);

/** Consecutive entry dates. Yesterday keeps the current streak alive until tonight. */
function diary_streak(array $dates, DateTimeImmutable $today): array
{
    $today = $today->setTime(0, 0);
    $dates = array_values(array_unique(array_filter($dates, fn($d) => $d <= $today->format('Y-m-d'))));
    sort($dates);
    $best = 0; $run = 0; $previous = null;
    foreach ($dates as $value) {
        $date = new DateTimeImmutable($value);
        $run = $previous !== null && $previous->modify('+1 day')->format('Y-m-d') === $value ? $run + 1 : 1;
        $best = max($best, $run); $previous = $date;
    }
    $set = array_fill_keys($dates, true);
    $enteredToday = isset($set[$today->format('Y-m-d')]);
    $cursor = $enteredToday ? $today : $today->modify('-1 day');
    $current = 0;
    while (isset($set[$cursor->format('Y-m-d')])) { $current++; $cursor = $cursor->modify('-1 day'); }
    return ['current'=>$current, 'best'=>$best, 'today'=>$enteredToday];
}

function routine_due(array $settings, DateTimeImmutable $now, string $kind): bool
{
    if (empty($settings[$kind . '_enabled'])) return false;
    $time = substr($settings[$kind . '_time'], 0, 5);
    $start = $now->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
    return $now >= $start && $now < $start->modify('+2 hours');
}
