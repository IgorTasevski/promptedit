<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudioButton from '@/components/studio/StudioButton.vue';
import { isInFlight } from '@/composables/usePollWhileInFlight';
import { describeSettings, presentStatus } from '@/lib/generationStatus';
import { usd, usdExact, usdRange } from '@/lib/money';
import { timeAgo } from '@/lib/time';
import { index as credits } from '@/routes/credits';
import { retry, show } from '@/routes/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const status = computed(() => presentStatus(props.generation));

const page = usePage();
const retryForm = useForm({});
const retried = ref(false);
const retryError = computed(() =>
    retried.value ? page.props.errors.credits : undefined,
);

function tryAgain(): void {
    retried.value = true;
    retryForm.post(retry.url(props.generation.id), { preserveScroll: true });
}
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-2xl border border-studio-line bg-studio-surface transition hover:-translate-y-0.5 hover:border-studio-edge hover:bg-studio-raised"
    >
        <Link
            :href="show(generation.id)"
            class="flex flex-1 flex-col rounded-t-2xl focus-ring"
        >
            <div
                class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-studio-raised p-4 text-center text-sm"
                :class="[
                    status.tone,
                    { 'bg-red-950/40': generation.status === 'failed' },
                ]"
            >
                <img
                    v-if="generation.image_url"
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                />
                <span
                    v-else
                    :class="{ 'animate-pulse': isInFlight(generation) }"
                >
                    {{ status.detail }}
                </span>
                <span
                    class="absolute top-3 left-3 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="status.pill"
                >
                    {{ status.label }}
                </span>
            </div>
            <div class="flex flex-1 flex-col gap-2 p-4">
                <p class="line-clamp-2 text-sm leading-snug text-zinc-200">
                    {{ generation.prompt }}
                </p>
                <p class="text-xs text-studio-muted">
                    {{ describeSettings(generation) }} ·
                    {{ timeAgo(generation.created_at) }}
                </p>
                <div
                    class="mt-auto flex items-baseline justify-between gap-3 border-t border-studio-line pt-3 text-xs text-studio-muted"
                >
                    <span>
                        Estimate
                        <span class="font-mono">{{
                            usdRange(
                                generation.estimated_min,
                                generation.estimated_max,
                            )
                        }}</span>
                    </span>
                    <span
                        v-if="
                            generation.status === 'succeeded' &&
                            generation.price !== null
                        "
                        class="font-mono text-[15px] font-medium text-zinc-100"
                        :title="`Charged exactly ${usdExact(generation.price)}`"
                    >
                        {{ usd(generation.price) }}
                    </span>
                    <span
                        v-else-if="generation.status === 'failed'"
                        class="font-mono text-[15px] text-green-200"
                    >
                        $0 · free
                    </span>
                    <span v-else class="font-mono">
                        holding {{ usd(generation.estimated_max) }}
                    </span>
                </div>
            </div>
        </Link>
        <div
            v-if="generation.status === 'failed'"
            class="flex flex-col gap-2 px-4 pb-4"
        >
            <StudioButton
                variant="secondary"
                class="w-full text-sm"
                :loading="retryForm.processing"
                @click="tryAgain"
            >
                {{ retryForm.processing ? 'Retrying…' : 'Try again' }}
            </StudioButton>
            <p v-if="retryError" role="alert" class="text-xs text-red-300">
                {{ retryError }}
                <Link :href="credits()" class="ml-1 link-lime">Top up →</Link>
            </p>
        </div>
    </article>
</template>
