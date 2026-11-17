<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import StudioChip from '@/components/studio/StudioChip.vue';
import {
    isInFlight,
    usePollWhileInFlight,
} from '@/composables/usePollWhileInFlight';
import { create } from '@/routes/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generations: Generation[] }>();

usePollWhileInFlight(() => props.generations, ['generations']);

const filters = {
    all: { label: 'All', matches: () => true },
    inFlight: { label: 'In progress', matches: isInFlight },
    succeeded: {
        label: 'Succeeded',
        matches: (generation: Generation) => generation.status === 'succeeded',
    },
    failed: {
        label: 'Failed',
        matches: (generation: Generation) => generation.status === 'failed',
    },
};

type Filter = keyof typeof filters;

const active = ref<Filter>('all');

const visible = computed(() =>
    props.generations.filter(filters[active.value].matches),
);

function count(filter: Filter): number {
    return props.generations.filter(filters[filter].matches).length;
}
</script>

<template>
    <Head title="Library" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display text-4xl font-bold tracking-tight">
                Library
            </h1>
            <div class="flex flex-wrap gap-2">
                <StudioChip
                    v-for="(filter, key) in filters"
                    :key="key"
                    :pressed="active === key"
                    @click="active = key"
                >
                    {{ filter.label }}
                    <span v-if="key !== 'all'" class="opacity-70">
                        · {{ count(key) }}
                    </span>
                </StudioChip>
            </div>
        </div>

        <div
            v-if="visible.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <GenerationCard
                v-for="generation in visible"
                :key="generation.id"
                :generation="generation"
            />
        </div>
        <div
            v-else
            class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-studio-edge py-20 text-center"
        >
            <p class="text-studio-muted">Nothing here yet.</p>
            <Link :href="create()" class="link-lime">
                Make your first image →
            </Link>
        </div>
    </div>
</template>
