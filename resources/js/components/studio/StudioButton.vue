<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';

const {
    variant = 'primary',
    type = 'button',
    loading = false,
    disabled = false,
} = defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost';
    type?: 'button' | 'submit';
    loading?: boolean;
    disabled?: boolean;
}>();

const variants = {
    primary: 'bg-lime text-studio hover:bg-lime-soft',
    secondary:
        'border border-studio-edge text-zinc-100 hover:border-lime hover:bg-lime hover:text-studio',
    ghost: 'text-lime hover:bg-studio-raised hover:text-lime-soft',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 font-semibold focus-ring transition active:scale-[.97] disabled:opacity-50 disabled:active:scale-100"
        :class="[
            variants[variant],
            { 'disabled:hover:bg-transparent': variant !== 'primary' },
        ]"
    >
        <Spinner v-if="loading" class="size-4" />
        <slot :loading="loading" />
    </button>
</template>
