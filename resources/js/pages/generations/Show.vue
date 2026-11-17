<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudioButton from '@/components/studio/StudioButton.vue';
import { usePollWhileInFlight } from '@/composables/usePollWhileInFlight';
import { describeSettings, presentStatus } from '@/lib/generationStatus';
import { usd, usdExact } from '@/lib/money';
import { timeAgo } from '@/lib/time';
import { index as credits } from '@/routes/credits';
import { index as library, retry } from '@/routes/generations';
import type { Generation } from '@/types';

type Breakdown = {
    lines: { type: string; tokens: number; rate: string; cost: number }[];
    provider_cost: number;
    markup: number;
    waived: number;
    price: number;
};

const props = defineProps<{
    generation: Generation;
    breakdown: Breakdown | null;
}>();

usePollWhileInFlight(() => [props.generation], ['generation', 'breakdown']);

const page = usePage();
const creditsError = computed(() => page.props.errors.credits);
const retryForm = useForm({});

function tryAgain(): void {
    retryForm.post(retry.url(props.generation.id));
}

const status = computed(() => presentStatus(props.generation));

const labels: Record<string, string> = {
    input_text_tokens: 'Prompt text',
    input_image_tokens: 'Reference images',
    output_tokens: 'Output image',
    reasoning_tokens: 'Reasoning',
};
</script>

<template>
    <Head title="Generation" />

    <div class="flex flex-col gap-6">
        <Link
            :href="library()"
            class="self-start rounded-sm py-2 text-sm text-studio-muted focus-ring transition-colors hover:text-white"
        >
            ← Library
        </Link>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-3xl border border-studio-line bg-studio-raised p-6 text-center"
                :class="status.tone"
            >
                <img
                    v-if="generation.image_url"
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    class="size-full object-contain"
                />
                <span
                    v-else
                    :class="{
                        'animate-pulse':
                            !breakdown && generation.status !== 'failed',
                    }"
                >
                    {{ status.detail }}
                </span>
            </div>

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-3">
                    <span
                        class="self-start rounded-full px-3 py-1 text-xs font-semibold"
                        :class="status.pill"
                    >
                        {{ status.label }}
                    </span>
                    <h1
                        class="font-display text-2xl leading-snug font-semibold"
                    >
                        {{ generation.prompt }}
                    </h1>
                    <p class="text-sm text-studio-muted">
                        {{ describeSettings(generation) }} · created
                        {{ timeAgo(generation.created_at) }}
                    </p>
                    <a
                        v-if="generation.image_url"
                        :href="generation.image_url"
                        target="_blank"
                        rel="noopener"
                        class="self-start link-lime text-sm"
                    >
                        Open full image ↗
                    </a>
                </div>

                <section
                    class="flex flex-col gap-4 rounded-2xl border border-studio-line bg-studio-surface p-5"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <span class="text-sm text-studio-muted">Estimated</span>
                        <span class="font-mono text-sm">
                            {{ usdExact(generation.estimated_min) }} –
                            {{ usdExact(generation.estimated_max) }}
                        </span>
                    </div>

                    <template v-if="breakdown">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead
                                    class="text-left text-[11px] tracking-wider text-studio-muted uppercase"
                                >
                                    <tr>
                                        <th class="pb-2 font-semibold">
                                            Tokens
                                        </th>
                                        <th
                                            class="pb-2 text-right font-semibold"
                                        >
                                            Count
                                        </th>
                                        <th
                                            class="pb-2 text-right font-semibold"
                                        >
                                            $ / 1M
                                        </th>
                                        <th
                                            class="pb-2 text-right font-semibold"
                                        >
                                            Cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="font-mono">
                                    <tr
                                        v-for="line in breakdown.lines"
                                        :key="line.type"
                                        class="border-t border-zinc-800"
                                    >
                                        <td class="py-2 font-sans">
                                            {{ labels[line.type] ?? line.type }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ line.tokens.toLocaleString() }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ line.rate }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ usdExact(line.cost) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <dl
                            class="flex flex-col gap-2 border-t border-studio-line pt-3 text-sm"
                        >
                            <div class="flex justify-between">
                                <dt class="text-studio-muted">
                                    Pixelforge cost
                                </dt>
                                <dd class="font-mono">
                                    {{ usdExact(breakdown.provider_cost) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-studio-muted">+ 25% markup</dt>
                                <dd class="font-mono">
                                    {{ usdExact(breakdown.markup) }}
                                </dd>
                            </div>
                            <div
                                v-if="breakdown.waived > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-studio-muted">
                                    Covered by PromptEdit
                                </dt>
                                <dd class="font-mono text-green-200">
                                    −{{ usdExact(breakdown.waived) }}
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <dt class="font-semibold">You were charged</dt>
                                <dd class="font-mono text-lg text-lime">
                                    {{ usdExact(breakdown.price) }}
                                </dd>
                            </div>
                        </dl>
                    </template>
                    <div
                        v-else-if="generation.status === 'failed'"
                        class="flex flex-col gap-3"
                    >
                        <p class="text-sm text-green-200">
                            Not charged. Failed generations are free.
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <StudioButton
                                :loading="retryForm.processing"
                                @click="tryAgain"
                            >
                                {{
                                    retryForm.processing
                                        ? 'Retrying…'
                                        : 'Try again'
                                }}
                            </StudioButton>
                            <span class="text-xs text-studio-muted">
                                Same prompt and settings, estimated
                                {{ usd(generation.estimated_min) }} –
                                {{ usd(generation.estimated_max) }}
                            </span>
                        </div>
                        <p
                            v-if="creditsError"
                            role="alert"
                            class="text-sm text-red-300"
                        >
                            {{ creditsError }}
                            <Link :href="credits()" class="ml-1 link-lime">
                                Top up →
                            </Link>
                        </p>
                    </div>
                    <p v-else class="text-sm text-studio-muted">
                        Holding {{ usd(generation.estimated_max) }} until it
                        finishes, then you pay the exact price.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
