import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** Two-letter initials from a `@handle`, e.g. `@jessicadev` -> `JE`. */
export function initialsFromHandle(handle: string): string {
    return handle.replace(/^@/, '').slice(0, 2).toUpperCase();
}
