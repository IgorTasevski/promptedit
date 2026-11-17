<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import StudioButton from '@/components/studio/StudioButton.vue';
import { usd, usdExact } from '@/lib/money';
import { timeAgo } from '@/lib/time';
import { store } from '@/routes/credits';

type Transaction = {
    id: number;
    type: 'purchase' | 'charge';
    amount: number;
    created_at: string;
    generation: { id: string; prompt: string } | null;
};

defineProps<{
    balance: number;
    held: number;
    available: number;
    packs: number[];
    transactions: Transaction[];
}>();

const form = useForm({ amount: 0 });

function buy(amount: number): void {
    form.amount = amount;
    form.post(store.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Credits" />

    <div class="flex flex-col gap-10">
        <section class="flex flex-wrap items-end gap-x-8 gap-y-3">
            <div class="flex flex-col gap-1">
                <span
                    class="text-xs tracking-widest text-studio-muted uppercase"
                >
                    Available
                </span>
                <span
                    class="font-mono text-5xl leading-none tracking-tight md:text-6xl"
                >
                    {{ usd(available) }}
                </span>
            </div>
            <span class="pb-1 text-sm text-studio-muted">
                Balance {{ usd(balance) }} ·
                {{
                    held
                        ? `${usd(held)} held for images in progress`
                        : 'nothing held'
                }}
            </span>
        </section>

        <section class="flex flex-col gap-4">
            <h2 class="font-display text-2xl font-semibold">Top up</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="pack in packs"
                    :key="pack"
                    class="flex flex-col gap-3 rounded-2xl border border-studio-line bg-studio-surface p-6"
                >
                    <span class="font-mono text-3xl">${{ pack }}</span>
                    <StudioButton
                        variant="secondary"
                        :loading="form.processing && form.amount === pack"
                        :disabled="form.processing"
                        @click="buy(pack)"
                    >
                        {{
                            form.processing && form.amount === pack
                                ? 'Adding…'
                                : `Add $${pack}`
                        }}
                    </StudioButton>
                </div>
            </div>
            <p class="text-xs text-studio-muted">
                Demo purchase — credits are added instantly, no payment is
                taken. Credits are US dollars.
            </p>
        </section>

        <section class="flex flex-col">
            <h2 class="mb-2 font-display text-2xl font-semibold">Activity</h2>
            <p
                v-if="!transactions.length"
                class="py-6 text-sm text-studio-muted"
            >
                No activity yet.
            </p>
            <div
                v-for="transaction in transactions"
                :key="transaction.id"
                class="flex items-center justify-between gap-4 border-b border-zinc-800 py-3"
            >
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="truncate text-sm">
                        {{
                            transaction.generation?.prompt ??
                            `${usd(transaction.amount, 2)} top up`
                        }}
                    </span>
                    <span class="text-xs text-studio-muted">
                        {{
                            transaction.type === 'charge'
                                ? 'Generation'
                                : 'Purchase'
                        }}
                        · {{ timeAgo(transaction.created_at) }}
                    </span>
                </div>
                <span
                    class="font-mono text-sm"
                    :class="{ 'text-lime': transaction.amount > 0 }"
                >
                    {{ transaction.amount > 0 ? '+' : ''
                    }}{{ usdExact(transaction.amount) }}
                </span>
            </div>
            <p class="pt-3 text-xs text-studio-muted">
                Amounts are exact to a billionth of a dollar. Failed generations
                never appear here — they cost nothing.
            </p>
        </section>
    </div>
</template>
