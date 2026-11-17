<script setup lang="ts">
defineProps<{ pressed: boolean }>();
</script>

<template>
    <button
        type="button"
        :aria-pressed="pressed"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-full border px-4 text-sm focus-ring transition active:scale-[.97]"
        :class="
            pressed
                ? 'border-lime bg-lime font-semibold text-studio hover:border-lime-soft hover:bg-lime-soft'
                : 'border-studio-edge text-zinc-200 hover:border-zinc-300 hover:bg-studio-raised hover:text-white'
        "
    >
        <slot />
    </button>
</template>
