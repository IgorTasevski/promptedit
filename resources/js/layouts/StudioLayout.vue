<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { usd } from '@/lib/money';
import { report } from '@/routes/admin';
import { index as credits } from '@/routes/credits';
import { create, index as library } from '@/routes/generations';

const page = usePage();
const user = computed(() => page.props.auth.user);
const available = computed(() => page.props.credits ?? 0);
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const links = computed(() => [
    { title: 'Create', href: create(), active: isCurrentUrl(create()) },
    {
        title: 'Library',
        href: library(),
        active: isCurrentOrParentUrl(library()),
    },
    ...(user.value.is_admin
        ? [{ title: 'Admin', href: report(), active: isCurrentUrl(report()) }]
        : []),
]);
</script>

<template>
    <div class="min-h-screen bg-studio font-sans text-studio-text">
        <header
            class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-6 py-4"
        >
            <Link
                :href="create()"
                class="mr-2 flex min-h-11 items-center rounded-sm font-display text-xl font-bold tracking-tight focus-ring"
            >
                prompt<span class="text-lime">edit</span>
            </Link>
            <nav class="flex gap-1">
                <Link
                    v-for="link in links"
                    :key="link.title"
                    :href="link.href"
                    :aria-current="link.active ? 'page' : undefined"
                    class="flex min-h-11 items-center rounded-full px-3 text-sm focus-ring transition-colors hover:bg-studio-raised hover:text-white"
                    :class="
                        link.active
                            ? 'font-semibold text-white'
                            : 'text-studio-muted'
                    "
                >
                    {{ link.title }}
                </Link>
            </nav>
            <span class="flex-1" />
            <Link
                :href="credits()"
                class="flex min-h-11 items-center gap-3 rounded-full border bg-studio-surface px-4 text-sm focus-ring transition-colors hover:border-lime hover:bg-studio-raised"
                :class="
                    available <= 0 ? 'border-red-800' : 'border-studio-edge'
                "
            >
                <span class="font-mono">{{ usd(available) }}</span>
                <span class="font-semibold text-lime">+ Top up</span>
            </Link>
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="flex size-11 items-center justify-center rounded-full border border-studio-edge bg-studio-surface text-sm font-semibold focus-ring transition-colors hover:border-zinc-300 hover:bg-studio-raised"
                    aria-label="Account menu"
                >
                    {{ getInitials(user.name) }}
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="end">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
        <main class="mx-auto max-w-6xl px-6 pt-4 pb-16">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
