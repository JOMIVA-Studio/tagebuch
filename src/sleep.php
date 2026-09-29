<?php
declare(strict_types=1);

function sleep_duration_label(array $sleep): string
{
    if (!empty($sleep['under_five_hours'])) {
        return 'Weniger als 5 Std.';
    }
    $minutes = (int) $sleep['duration_minutes'];
    return intdiv($minutes, 60) . ' Std. ' . ($minutes % 60) . ' Min.';
}
