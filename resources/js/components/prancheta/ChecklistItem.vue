<script setup lang="ts">
defineProps<{ itemId: number; content: string; checked: boolean }>();

defineEmits<{ toggle: [] }>();
</script>

<template>
    <button
        type="button"
        data-testid="chk"
        :data-item="itemId"
        :data-checked="checked"
        :aria-pressed="checked"
        class="group flex w-full cursor-pointer items-start gap-2 py-1 text-left text-[12.5px] leading-[1.4] text-sd-ink-2 hover:text-sd-ink"
        @click="$emit('toggle')"
    >
        <span
            data-testid="chk-box"
            class="mt-px grid size-[15px] flex-none place-items-center rounded-[4px] border-[1.5px] border-sd-line-2 transition-colors group-aria-pressed:border-sd-accent group-aria-pressed:bg-sd-accent"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3.2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                class="size-2.5 text-sd-accent-ink opacity-0 group-aria-pressed:opacity-100"
            >
                <path d="M5 12.5 10 17.5 19 7" />
            </svg>
        </span>
        <span
            class="group-aria-pressed:text-sd-ink-3 group-aria-pressed:line-through group-aria-pressed:decoration-sd-line-2"
        >
            {{ content }}
        </span>
    </button>
</template>
