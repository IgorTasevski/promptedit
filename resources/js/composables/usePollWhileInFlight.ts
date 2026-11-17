import { usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { Generation } from '@/types';

const IN_FLIGHT = ['pending', 'queued', 'processing'];
const INTERVAL_MS = 2000;

export function isInFlight(generation: Generation): boolean {
    return IN_FLIGHT.includes(generation.status);
}

export function usePollWhileInFlight(
    generations: () => Generation[],
    only: string[],
): void {
    const hasInFlight = computed(() => generations().some(isInFlight));
    const { start, stop } = usePoll(
        INTERVAL_MS,
        { only: [...only, 'credits'] },
        { autoStart: false },
    );

    watch(hasInFlight, (polling) => (polling ? start() : stop()), {
        immediate: true,
    });
}
