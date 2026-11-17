const NANODOLLARS_PER_DOLLAR = 1_000_000_000;

function sign(nanodollars: number): string {
    return nanodollars < 0 ? '−' : '';
}

export function usd(nanodollars: number, decimals = 4): string {
    const dollars = Math.abs(nanodollars) / NANODOLLARS_PER_DOLLAR;

    return `${sign(nanodollars)}$${dollars.toFixed(decimals)}`;
}

export function usdExact(nanodollars: number): string {
    const absolute = Math.abs(nanodollars);
    const dollars = Math.floor(absolute / NANODOLLARS_PER_DOLLAR);
    const fraction = String(absolute % NANODOLLARS_PER_DOLLAR).padStart(9, '0');

    return `${sign(nanodollars)}$${dollars}.${fraction}`;
}

export function usdRange(min: number, max: number): string {
    return `${usd(min)} – ${usd(max)}`;
}
