<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ScheduleRecurrenceService
{
    /**
     * Convert form/API recurrence fields into values that can be persisted.
     * scheduled_at always stores the next execution.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, ?Schedule $existing = null): array
    {
        $timezone = config('app.timezone');
        $recurrenceType = (string) ($data['recurrence_type'] ?? $existing?->recurrence_type ?? 'once');

        if ($recurrenceType === 'once') {
            $data['recurrence_type'] = 'once';
            $data['recurrence_time'] = null;
            $data['recurrence_days'] = null;
            $data['recurrence_day'] = null;
            $data['recurrence_ends_at'] = null;
            unset($data['starts_on'], $data['target_scope']);

            return $data;
        }

        $startDate = (string) ($data['starts_on']
            ?? $existing?->scheduled_at?->timezone($timezone)->format('Y-m-d')
            ?? now($timezone)->format('Y-m-d'));
        $time = substr((string) ($data['recurrence_time'] ?? $existing?->recurrence_time), 0, 5);
        $days = $data['recurrence_days'] ?? $existing?->recurrence_days;
        $dayOfMonth = $data['recurrence_day'] ?? $existing?->recurrence_day;
        $endsAt = array_key_exists('recurrence_ends_at', $data)
            ? $data['recurrence_ends_at']
            : $existing?->recurrence_ends_at;

        $definition = new Schedule([
            'recurrence_type' => $recurrenceType,
            'recurrence_time' => $time,
            'recurrence_days' => $recurrenceType === 'weekly' ? array_values(array_map('intval', $days ?? [])) : null,
            'recurrence_day' => $recurrenceType === 'monthly' ? (int) $dayOfMonth : null,
            'recurrence_ends_at' => $endsAt
                ? CarbonImmutable::parse($endsAt, $timezone)->endOfDay()
                : null,
        ]);

        $now = CarbonImmutable::now($timezone);
        $start = CarbonImmutable::parse($startDate, $timezone)->startOfDay();
        $after = $start->greaterThan($now) ? $start->subSecond() : $now;
        $next = $this->nextOccurrence($definition, $after);

        if ($next === null) {
            throw ValidationException::withMessages([
                'recurrence_ends_at' => 'La recurrencia no tiene una próxima ejecución dentro del rango seleccionado.',
            ]);
        }

        $data['recurrence_type'] = $recurrenceType;
        $data['recurrence_time'] = $time;
        $data['recurrence_days'] = $definition->recurrence_days;
        $data['recurrence_day'] = $definition->recurrence_day;
        $data['recurrence_ends_at'] = $definition->recurrence_ends_at;
        $data['scheduled_at'] = $next;
        unset($data['starts_on'], $data['target_scope']);

        return $data;
    }

    public function nextOccurrence(Schedule $schedule, CarbonInterface $after): ?CarbonImmutable
    {
        if (! $schedule->isRecurring()) {
            return null;
        }

        $timezone = config('app.timezone');
        $cursor = CarbonImmutable::instance($after)->timezone($timezone);
        [$hour, $minute] = array_pad(
            array_map('intval', explode(':', substr((string) $schedule->recurrence_time, 0, 5))),
            2,
            0,
        );

        $candidate = match ($schedule->recurrence_type) {
            'daily' => $this->nextDaily($cursor, $hour, $minute),
            'weekly' => $this->nextWeekly($cursor, $hour, $minute, $schedule->recurrence_days ?? []),
            'monthly' => $this->nextMonthly($cursor, $hour, $minute, (int) $schedule->recurrence_day),
            default => null,
        };

        if ($candidate === null) {
            return null;
        }

        $endsAt = $schedule->recurrence_ends_at
            ? CarbonImmutable::instance($schedule->recurrence_ends_at)->timezone($timezone)
            : null;

        return $endsAt !== null && $candidate->greaterThan($endsAt) ? null : $candidate;
    }

    private function nextDaily(CarbonImmutable $after, int $hour, int $minute): CarbonImmutable
    {
        $candidate = $after->startOfDay()->setTime($hour, $minute);

        return $candidate->lessThanOrEqualTo($after) ? $candidate->addDay() : $candidate;
    }

    /** @param array<int, int|string> $days */
    private function nextWeekly(CarbonImmutable $after, int $hour, int $minute, array $days): ?CarbonImmutable
    {
        $selectedDays = array_map('intval', $days);

        for ($offset = 0; $offset <= 7; $offset++) {
            $candidate = $after->startOfDay()->addDays($offset)->setTime($hour, $minute);

            if (in_array($candidate->dayOfWeekIso, $selectedDays, true) && $candidate->greaterThan($after)) {
                return $candidate;
            }
        }

        return null;
    }

    private function nextMonthly(CarbonImmutable $after, int $hour, int $minute, int $requestedDay): CarbonImmutable
    {
        for ($offset = 0; $offset <= 12; $offset++) {
            $month = $after->startOfMonth()->addMonthsNoOverflow($offset);
            $candidate = $month
                ->day(min($requestedDay, $month->daysInMonth))
                ->setTime($hour, $minute);

            if ($candidate->greaterThan($after)) {
                return $candidate;
            }
        }

        return $after->addMonth()->startOfMonth()->setTime($hour, $minute);
    }
}
