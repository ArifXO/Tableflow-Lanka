<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10 text-[#3b1010]" style="background-color:#f5f5dc;">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="flex flex-col items-center gap-2">
                        <img src="/images/logo.png" alt="Logo" class="mb-2 w-16 h-16" />
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium text-[#3b1010]">{{ title }}</h1>
                        <p class="text-center text-sm text-[#3b1010]">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
