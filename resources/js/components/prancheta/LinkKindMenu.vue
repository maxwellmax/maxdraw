<script setup lang="ts">
import type { LinkType } from '@/canvas/links';
import { dashSample } from '@/canvas/links';

defineProps<{
    types: LinkType[];
    kind: string | null;
}>();

defineEmits<{
    pick: [kind: string | null];
}>();
</script>

<template>
    <div
        data-testid="kind-menu"
        class="absolute top-[calc(100%+6px)] left-0 z-[25] min-w-[186px] rounded-[9px] border border-sd-line-2 bg-sd-panel p-1 shadow-sd-2"
        @pointerdown.stop
    >
        <button
            v-for="type in types"
            :key="type.slug"
            type="button"
            data-testid="kind-option"
            :data-kind="type.slug"
            :class="[
                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] hover:bg-sd-panel-2 hover:text-sd-ink',
                kind === type.slug
                    ? 'font-semibold text-sd-accent'
                    : 'text-sd-ink-2',
            ]"
            @click="$emit('pick', type.slug)"
        >
            <span
                data-testid="kind-sample"
                class="h-0.5 w-6 flex-none rounded-[1px] opacity-80"
                :style="{ background: dashSample(type.dash_array) }"
                aria-hidden="true"
            ></span>
            {{ type.name }}
        </button>

        <div class="mx-0.5 my-1 h-px bg-sd-line"></div>

        <button
            type="button"
            data-testid="kind-option"
            data-kind=""
            :class="[
                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] hover:bg-sd-panel-2 hover:text-sd-ink',
                kind === null
                    ? 'font-semibold text-sd-accent'
                    : 'text-sd-ink-2',
            ]"
            @click="$emit('pick', null)"
        >
            <span
                data-testid="kind-sample"
                class="h-0.5 w-6 flex-none rounded-[1px] opacity-80"
                :style="{ background: dashSample(null) }"
                aria-hidden="true"
            ></span>
            Sem tipo
        </button>
    </div>
</template>
