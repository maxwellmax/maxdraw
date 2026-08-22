<script setup lang="ts">
import type { CatalogCategory } from '@/canvas/catalog';
import CanvasIcon from '@/components/prancheta/CanvasIcon.vue';

defineProps<{ categories: CatalogCategory[] }>();

defineEmits<{ pick: [slug: string] }>();
</script>

<template>
    <div>
        <template v-for="category in categories" :key="category.slug">
            <div
                data-testid="palette-category"
                class="flex items-center gap-[7px] px-3 pt-[9px] pb-[3px] font-mono text-[10px] font-medium tracking-[0.09em] uppercase after:h-px after:flex-1 after:bg-sd-line after:content-['']"
                :style="{ color: `var(${category.color_token})` }"
            >
                <i class="block size-[7px] rounded-[2px] bg-current"></i>
                <span class="text-sd-ink-3">{{ category.name }}</span>
            </div>
            <button
                v-for="component in category.components"
                :key="component.slug"
                type="button"
                data-testid="palette-item"
                :data-component="component.slug"
                :title="component.name"
                class="group mx-1.5 flex w-[calc(100%-12px)] cursor-pointer items-center gap-[9px] rounded-md px-[7px] py-1.5 text-left text-[13px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink active:bg-sd-panel-3"
                @click="$emit('pick', component.slug)"
            >
                <span
                    class="grid size-[26px] flex-none place-items-center rounded-[5px] border border-sd-line bg-sd-panel-2 group-hover:border-sd-line-2 group-hover:bg-sd-panel [&_svg]:size-4"
                    :style="{ color: `var(${category.color_token})` }"
                >
                    <CanvasIcon :icon-key="component.icon_key" />
                </span>
                <span>{{ component.name }}</span>
            </button>
        </template>
    </div>
</template>
