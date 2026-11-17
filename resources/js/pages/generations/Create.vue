<script setup lang="ts">
import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import StudioButton from '@/components/studio/StudioButton.vue';
import StudioChip from '@/components/studio/StudioChip.vue';
import { usePollWhileInFlight } from '@/composables/usePollWhileInFlight';
import { usd, usdRange } from '@/lib/money';
import { index as credits } from '@/routes/credits';
import { estimate, index as library, store } from '@/routes/generations';
import type { Estimate, Generation, Option } from '@/types';

const MAX_REFERENCE_IMAGES = 4;
const DEFAULT_QUALITY = 'medium';

const props = defineProps<{
    sizes: Option[];
    qualities: Option[];
    recent: Generation[];
}>();

usePollWhileInFlight(() => props.recent, ['recent']);

const page = usePage();
const available = computed(() => page.props.credits ?? 0);
const creditsError = computed(() => page.props.errors.credits);

const form = useForm({
    prompt: '',
    size: props.sizes[0].value,
    quality: DEFAULT_QUALITY,
    reference_images: [] as string[],
});

const pricing = useHttp<
    {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
    },
    Estimate
>({ prompt: '', size: '', quality: '', reference_images: [] });

const price = ref<Estimate | null>(null);
const priceError = ref<string | null>(null);

const refreshEstimate = useDebounceFn(async () => {
    if (!form.prompt.trim()) {
        price.value = null;
        priceError.value = null;

        return;
    }

    pricing.prompt = form.prompt;
    pricing.size = form.size;
    pricing.quality = form.quality;
    pricing.reference_images = form.reference_images.filter(Boolean);

    try {
        price.value = await pricing.post(estimate.url());
        priceError.value = null;
    } catch {
        price.value = null;
        priceError.value =
            Object.values(pricing.errors)[0] ??
            'We couldn’t price this yet. Check the fields above.';
    }
}, 300);

watch(
    () => [
        form.prompt,
        form.size,
        form.quality,
        form.reference_images.filter(Boolean).join('|'),
    ],
    refreshEstimate,
);

const cannotAfford = computed(
    () => price.value !== null && available.value < price.value.max,
);

const blockedReason = computed(() => {
    if (!form.prompt.trim()) {
        return 'Write a prompt to see the price.';
    }

    if (priceError.value) {
        return priceError.value;
    }

    if (cannotAfford.value && price.value) {
        return `This can cost up to ${usd(price.value.max)} and you have ${usd(available.value)} available.`;
    }

    return null;
});

const referencesFull = computed(
    () => form.reference_images.length >= MAX_REFERENCE_IMAGES,
);

function addReference(): void {
    form.reference_images.push('');
}

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        reference_images: data.reference_images.filter(Boolean),
    })).post(store.url(), {
        onSuccess: () => form.reset('prompt', 'reference_images'),
    });
}
</script>

<template>
    <Head title="Create" />

    <div class="flex flex-col gap-10">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <h1
                class="font-display text-4xl leading-tight font-bold tracking-tight md:text-5xl"
            >
                What should we make?
            </h1>

            <div
                v-if="creditsError"
                role="alert"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-900 bg-red-950/40 px-5 py-4 text-sm"
            >
                <span class="text-red-100">{{ creditsError }}</span>
                <Link :href="credits()" class="link-lime">Top up →</Link>
            </div>

            <div
                class="flex flex-col gap-5 rounded-3xl border border-studio-line bg-studio-surface p-5 transition-colors focus-within:border-studio-edge"
            >
                <div class="flex flex-col gap-2">
                    <label for="prompt" class="sr-only">Prompt</label>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="3"
                        maxlength="4000"
                        placeholder="A lighthouse on a basalt cliff at dusk, long exposure…"
                        class="resize-none rounded-xl bg-transparent p-2 text-lg leading-relaxed text-zinc-100 focus-ring placeholder:text-studio-muted"
                    />
                    <p v-if="form.errors.prompt" class="text-sm text-red-300">
                        {{ form.errors.prompt }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <fieldset class="flex flex-wrap items-center gap-2">
                        <legend
                            class="float-left mr-1 text-[11px] font-semibold tracking-widest text-studio-muted uppercase"
                        >
                            Size
                        </legend>
                        <StudioChip
                            v-for="size in sizes"
                            :key="size.value"
                            :pressed="form.size === size.value"
                            @click="form.size = size.value"
                        >
                            {{ size.label }}
                        </StudioChip>
                    </fieldset>
                    <fieldset class="flex flex-wrap items-center gap-2">
                        <legend
                            class="float-left mr-1 text-[11px] font-semibold tracking-widest text-studio-muted uppercase"
                        >
                            Quality
                        </legend>
                        <StudioChip
                            v-for="quality in qualities"
                            :key="quality.value"
                            :pressed="form.quality === quality.value"
                            @click="form.quality = quality.value"
                        >
                            {{ quality.label }}
                        </StudioChip>
                    </fieldset>
                </div>

                <div class="flex flex-col gap-2">
                    <div
                        v-for="(_, index) in form.reference_images"
                        :key="index"
                        class="flex flex-col gap-1"
                    >
                        <div class="flex gap-2">
                            <label :for="`reference-${index}`" class="sr-only">
                                Reference image URL {{ index + 1 }}
                            </label>
                            <input
                                :id="`reference-${index}`"
                                v-model="form.reference_images[index]"
                                type="url"
                                placeholder="https://… image URL"
                                class="min-h-11 flex-1 rounded-xl border border-studio-edge bg-studio px-3 text-sm focus-ring placeholder:text-studio-muted"
                            />
                            <button
                                type="button"
                                class="size-11 rounded-xl border border-studio-edge text-lg text-zinc-300 focus-ring transition hover:border-red-400 hover:bg-red-950/40 hover:text-red-200 active:scale-[.97]"
                                :aria-label="`Remove reference image ${index + 1}`"
                                @click="removeReference(index)"
                            >
                                ×
                            </button>
                        </div>
                        <p
                            v-if="form.errors[`reference_images.${index}`]"
                            class="text-sm text-red-300"
                        >
                            {{ form.errors[`reference_images.${index}`] }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <StudioButton
                            variant="ghost"
                            class="-ml-3 text-sm"
                            :disabled="referencesFull"
                            @click="addReference"
                        >
                            ＋ Add reference image
                        </StudioButton>
                        <span class="text-xs text-studio-muted">
                            {{ form.reference_images.length }} of
                            {{ MAX_REFERENCE_IMAGES }} · each adds about $0.0096
                        </span>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-studio-line pt-4"
                >
                    <div class="flex flex-col gap-1" aria-live="polite">
                        <span class="text-sm text-studio-muted">
                            Estimated
                            <span
                                class="font-mono text-[15px] text-zinc-100 transition-opacity"
                                :class="{ 'opacity-50': pricing.processing }"
                            >
                                {{
                                    price ? usdRange(price.min, price.max) : '—'
                                }}
                            </span>
                        </span>
                        <span
                            v-if="blockedReason"
                            class="text-xs"
                            :class="
                                cannotAfford || priceError
                                    ? 'text-red-300'
                                    : 'text-studio-muted'
                            "
                        >
                            {{ blockedReason }}
                            <Link
                                v-if="cannotAfford"
                                :href="credits()"
                                class="ml-1 link-lime"
                            >
                                Top up →
                            </Link>
                        </span>
                        <span
                            v-else-if="price"
                            class="text-xs text-studio-muted"
                        >
                            You pay the exact price when it finishes — at most
                            {{ usd(price.max) }}. Failed images are free.
                        </span>
                    </div>
                    <StudioButton
                        type="submit"
                        class="min-h-12 px-7"
                        :loading="form.processing"
                        :disabled="!form.prompt.trim() || cannotAfford"
                    >
                        {{ form.processing ? 'Generating…' : 'Generate' }}
                    </StudioButton>
                </div>
            </div>
        </form>

        <section v-if="recent.length" class="flex flex-col gap-4">
            <div class="flex items-baseline justify-between">
                <h2 class="font-display text-xl font-semibold">Recent</h2>
                <Link :href="library()" class="link-lime text-sm">
                    Open library →
                </Link>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <GenerationCard
                    v-for="generation in recent"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>
        </section>
    </div>
</template>
