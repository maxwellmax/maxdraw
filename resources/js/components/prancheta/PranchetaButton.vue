<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'default' | 'primary' | 'icon';
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    { variant: 'default', type: 'button', disabled: false },
);

const VARIANTS = {
    default: '',
    primary:
        'bg-sd-accent border-sd-accent text-sd-accent-ink font-semibold hover:bg-sd-accent-2 hover:border-sd-accent-2 hover:text-sd-accent-ink',
    icon: 'w-[30px] px-0 justify-center',
} as const;

const classes = computed(() => VARIANTS[props.variant]);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :data-variant="variant"
        class="inline-flex h-[30px] cursor-pointer items-center gap-1.5 rounded-md border border-sd-line-2 bg-sd-panel px-[11px] text-[13px] font-medium text-sd-ink-2 transition-colors hover:border-sd-ink-3 hover:bg-sd-panel-2 hover:text-sd-ink disabled:pointer-events-none disabled:opacity-45 [&_svg]:size-[15px] [&_svg]:shrink-0"
        :class="classes"
    >
        <slot />
    </button>
</template>
