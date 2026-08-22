<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts } = useToast();
</script>

<template>
    <div
        data-testid="toast-host"
        role="status"
        aria-live="polite"
        class="pointer-events-none fixed bottom-[22px] left-1/2 z-200 flex max-w-[min(420px,calc(100vw-32px))] -translate-x-1/2 flex-col items-center gap-1.5"
    >
        <div
            v-for="entry in toasts"
            :key="entry.id"
            data-testid="toast"
            class="rounded-lg bg-sd-ink px-[15px] py-[9px] text-center text-[12.5px] font-medium text-sd-paper shadow-sd-2"
        >
            {{ entry.message }}
        </div>
    </div>
</template>
