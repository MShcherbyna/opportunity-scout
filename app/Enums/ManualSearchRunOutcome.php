<?php

namespace App\Enums;

use App\Models\ThreadsSearchRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

enum ManualSearchRunOutcome: string
{
    case Started = 'started';
    case NotConnected = 'not_connected';
    case NoKeywords = 'no_keywords';
    case RunInProgress = 'run_in_progress';
    case Cooldown = 'cooldown';
    case Paused = 'paused';
    case Budget = 'budget';

    /**
     * The outcome of a StartManualSearchRun result; an unknown block reason counts as a run in progress.
     *
     * @param  array{run: ThreadsSearchRun|null, block: array{reason: string|null, availableAt: CarbonInterface|null, pausedUntil: CarbonInterface|null}}  $result
     */
    public static function fromResult(array $result): self
    {
        if ($result['run'] !== null) {
            return self::Started;
        }

        $reason = $result['block']['reason'];

        return $reason === null ? self::RunInProgress : (self::tryFrom($reason) ?? self::RunInProgress);
    }

    /**
     * The toast type for the outcome.
     */
    public function toastType(): string
    {
        return $this === self::Started ? 'success' : 'error';
    }

    /**
     * The toast copy; the time is the end of the cooldown or pause and is shown in the given zone.
     */
    public function toastMessage(?CarbonInterface $time, string $timezone): string
    {
        $formatted = $time === null ? '' : Carbon::instance($time)->setTimezone($timezone)->format('H:i');

        return match ($this) {
            self::Started => 'Search started.',
            self::NotConnected => 'Connect Threads to run a search.',
            self::NoKeywords => 'Add keywords to run a search.',
            self::Cooldown => 'You can run a search again at '.$formatted.'.',
            self::Paused => 'Search is paused until '.$formatted.'.',
            self::Budget => "Not enough searches left in today's budget.",
            self::RunInProgress => 'A search is already running.',
        };
    }

    /**
     * The toast to flash for the outcome.
     *
     * @param  array{reason: string|null, availableAt: CarbonInterface|null, pausedUntil: CarbonInterface|null}  $block
     * @return array{type: string, message: string}
     */
    public function toast(array $block, string $timezone): array
    {
        $time = match ($this) {
            self::Cooldown => $block['availableAt'],
            self::Paused => $block['pausedUntil'],
            default => null,
        };

        return ['type' => $this->toastType(), 'message' => $this->toastMessage($time, $timezone)];
    }
}
