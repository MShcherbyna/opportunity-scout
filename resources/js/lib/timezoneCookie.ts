/**
 * Writes the browser's IANA time zone to the `timezone` cookie so the server
 * can bucket dashboard numbers per local day. Runs on every full page load.
 */
export function initializeTimezoneCookie(): void {
    if (typeof document === 'undefined') {
        return;
    }

    let zone: string | undefined;

    try {
        zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
        return;
    }

    if (!zone) {
        return;
    }

    const maxAge = 365 * 24 * 60 * 60;

    document.cookie = `timezone=${encodeURIComponent(zone)};path=/;max-age=${maxAge};SameSite=Lax`;
}
