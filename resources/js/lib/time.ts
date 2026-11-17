const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
    ['second', 1],
];

const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function timeAgo(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const [unit, size] =
        UNITS.find(([, length]) => Math.abs(seconds) >= length) ?? UNITS[3];

    return formatter.format(Math.round(seconds / size), unit);
}
