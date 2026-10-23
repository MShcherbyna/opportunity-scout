import type { ManualRunBlockedReason, ThreadsDisconnectReason } from '@/types';

/** R-1 to R-4: why Threads is disconnected. */
export const disconnectReasonText: Record<ThreadsDisconnectReason, string> = {
    user: 'You disconnected Threads.',
    uninstalled: 'Access was removed in Threads.',
    token_invalid: 'Threads rejected the saved access.',
    expired: 'Threads access expired.',
};

const timeFormatter = new Intl.DateTimeFormat('en', {
    hour: '2-digit',
    minute: '2-digit',
});

const dateTimeFormatter = new Intl.DateTimeFormat('en', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const dateFormatter = new Intl.DateTimeFormat('en', { dateStyle: 'medium' });

export function formatTime(value: string): string {
    return timeFormatter.format(new Date(value));
}

export function formatDateTime(value: string): string {
    return dateTimeFormatter.format(new Date(value));
}

export function formatDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

const relativeFormatter = new Intl.RelativeTimeFormat('en', {
    numeric: 'auto',
});

export function formatRelativeTime(value: string, now = Date.now()): string {
    const seconds = Math.round((new Date(value).getTime() - now) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return relativeFormatter.format(Math.round(seconds / 60) || 0, 'minute');
}

/** M-1 to M-6: why "Run scout" is disabled. */
export function manualRunBlockedText(
    reason: ManualRunBlockedReason,
    availableAt: string | null,
    pausedUntil: string | null,
): string {
    switch (reason) {
        case 'not_connected':
            return 'Connect Threads to run a search.';
        case 'no_keywords':
            return 'Add keywords to run a search.';
        case 'run_in_progress':
            return 'A search is already running.';
        case 'cooldown':
            return availableAt
                ? `You can run a search again at ${formatTime(availableAt)}.`
                : 'You can run a search again soon.';
        case 'paused':
            return pausedUntil
                ? `Search is paused until ${formatTime(pausedUntil)}.`
                : 'Search is paused.';
        case 'budget':
            return "Not enough searches left in today's budget.";
    }
}
