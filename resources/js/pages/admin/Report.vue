<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudioButton from '@/components/studio/StudioButton.vue';
import { usdExact } from '@/lib/money';
import { report } from '@/routes/admin';

type Day = {
    date: string;
    generations: number;
    charged: number;
    provider_cost: number;
    pixelforge_cost: number | null;
    profit: number;
};

type UserRow = {
    email: string;
    succeeded: number;
    failed: number;
    charged: number;
    provider_cost: number;
    profit: number;
};

const props = defineProps<{
    start_date: string;
    end_date: string;
    days: Day[];
    users: UserRow[];
    usage_error: string | null;
}>();

const filters = useForm({
    start_date: props.start_date,
    end_date: props.end_date,
});

const totals = computed(() => {
    const sum = (key: 'charged' | 'provider_cost' | 'profit') =>
        props.days.reduce((total, day) => total + day[key], 0);

    return [
        { label: 'Charged', value: usdExact(sum('charged')), tone: '' },
        {
            label: 'Pixelforge cost',
            value: usdExact(sum('provider_cost')),
            tone: '',
        },
        { label: 'Profit', value: usdExact(sum('profit')), tone: 'text-lime' },
        {
            label: 'Matches Pixelforge',
            value: `${props.days.filter((day) => day.pixelforge_cost === day.provider_cost).length} / ${props.days.length} days`,
            tone: '',
        },
    ];
});

function apply(): void {
    filters.get(report.url(), { preserveState: true });
}
</script>

<template>
    <Head title="Operator report" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-4xl font-bold tracking-tight">
                    Operator report
                </h1>
                <p class="mt-1 text-sm text-studio-muted">
                    UTC days · a generation counts on the day it completed, like
                    Pixelforge’s usage report.
                </p>
            </div>
            <form
                class="flex flex-wrap items-end gap-2 text-sm"
                @submit.prevent="apply"
            >
                <label class="flex flex-col gap-1 text-studio-muted">
                    From
                    <input
                        v-model="filters.start_date"
                        type="date"
                        class="min-h-11 rounded-xl border border-studio-edge bg-studio-surface px-3 text-zinc-100 focus-ring"
                    />
                </label>
                <label class="flex flex-col gap-1 text-studio-muted">
                    To
                    <input
                        v-model="filters.end_date"
                        type="date"
                        class="min-h-11 rounded-xl border border-studio-edge bg-studio-surface px-3 text-zinc-100 focus-ring"
                    />
                </label>
                <StudioButton type="submit" :loading="filters.processing">
                    Show
                </StudioButton>
            </form>
        </div>
        <p
            v-if="filters.errors.end_date || filters.errors.start_date"
            class="text-sm text-red-300"
        >
            {{ filters.errors.end_date ?? filters.errors.start_date }}
        </p>
        <p
            v-if="usage_error"
            role="alert"
            class="rounded-2xl border border-amber-800 bg-amber-950/40 px-5 py-3 text-sm text-amber-100"
        >
            Couldn’t load Pixelforge’s usage report: {{ usage_error }}
        </p>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="total in totals"
                :key="total.label"
                class="flex flex-col gap-1"
            >
                <span
                    class="text-xs tracking-widest text-studio-muted uppercase"
                >
                    {{ total.label }}
                </span>
                <span class="font-mono text-2xl" :class="total.tone">
                    {{ total.value }}
                </span>
            </div>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-xl font-semibold">
                Daily — checked against Pixelforge
            </h2>
            <div
                class="overflow-x-auto rounded-2xl border border-studio-line bg-studio-surface"
            >
                <table class="w-full text-sm whitespace-nowrap">
                    <thead
                        class="text-left text-[11px] tracking-wider text-studio-muted uppercase"
                    >
                        <tr class="border-b border-studio-line">
                            <th class="px-4 py-3">Day</th>
                            <th class="px-4 py-3 text-right">Gens</th>
                            <th class="px-4 py-3 text-right">Charged</th>
                            <th class="px-4 py-3 text-right">
                                Our provider cost
                            </th>
                            <th class="px-4 py-3 text-right">
                                Pixelforge says
                            </th>
                            <th class="px-4 py-3">Check</th>
                            <th class="px-4 py-3 text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono">
                        <tr
                            v-for="day in [...days].reverse()"
                            :key="day.date"
                            class="border-b border-zinc-800 last:border-0"
                        >
                            <td class="px-4 py-3 font-sans">{{ day.date }}</td>
                            <td class="px-4 py-3 text-right">
                                {{ day.generations }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(day.charged) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(day.provider_cost) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{
                                    day.pixelforge_cost === null
                                        ? '—'
                                        : usdExact(day.pixelforge_cost)
                                }}
                            </td>
                            <td class="px-4 py-3 font-sans">
                                <span
                                    v-if="day.pixelforge_cost === null"
                                    class="text-studio-muted"
                                    >— unavailable</span
                                >
                                <span
                                    v-else-if="
                                        day.pixelforge_cost ===
                                        day.provider_cost
                                    "
                                    class="text-lime"
                                    >✓ match</span
                                >
                                <span v-else class="text-amber-300"
                                    >≠ awaiting a completion</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(day.profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-xl font-semibold">Per user</h2>
            <div
                class="overflow-x-auto rounded-2xl border border-studio-line bg-studio-surface"
            >
                <table class="w-full text-sm whitespace-nowrap">
                    <thead
                        class="text-left text-[11px] tracking-wider text-studio-muted uppercase"
                    >
                        <tr class="border-b border-studio-line">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3 text-right">Succeeded</th>
                            <th class="px-4 py-3 text-right">Failed</th>
                            <th class="px-4 py-3 text-right">Charged</th>
                            <th class="px-4 py-3 text-right">Provider cost</th>
                            <th class="px-4 py-3 text-right">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono">
                        <tr v-if="!users.length">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center font-sans text-studio-muted"
                            >
                                No completed generations in this range.
                            </td>
                        </tr>
                        <tr
                            v-for="user in users"
                            :key="user.email"
                            class="border-b border-zinc-800 last:border-0"
                        >
                            <td class="px-4 py-3 font-sans">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ user.succeeded }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ user.failed }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(user.charged) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(user.provider_cost) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ usdExact(user.profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
