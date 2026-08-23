<script setup lang="ts">
import type { LegendData } from '@/canvas/legend';
import {
    ORDER_GLOSS,
    ORDER_NAME,
    UNTYPED_GLOSS,
    UNTYPED_NAME,
} from '@/canvas/legend';
import { SEQ_PILL_HEIGHT, SEQ_PILL_PADDING, seqPillWidth } from '@/canvas/svg';
import LegendLine from '@/components/prancheta/LegendLine.vue';

defineProps<{ data: LegendData }>();

/**
 * A amostra da ordem é o mesmo pill do chip, com a geometria vinda das mesmas
 * constantes — só a cor muda, porque a legenda é neutra (US-5.1, UI-01).
 */
const samplePill = {
    minWidth: `${seqPillWidth(1)}px`,
    height: `${SEQ_PILL_HEIGHT}px`,
    paddingInline: `${SEQ_PILL_PADDING}px`,
};
</script>

<template>
    <div data-testid="legend-content">
        <template v-if="data.categories.length">
            <div
                data-testid="legend-section"
                class="mt-2.5 mb-1 font-mono text-[9.5px] tracking-[0.09em] text-sd-ink-3 uppercase first:mt-2"
            >
                Blocos
            </div>
            <div
                v-for="category in data.categories"
                :key="category.slug"
                data-testid="legend-category"
                :data-category="category.slug"
                class="flex items-center gap-2 py-0.5 text-[12px] text-sd-ink-2"
            >
                <i
                    data-testid="legend-swatch"
                    class="size-2 flex-none rounded-[2px]"
                    :style="{ background: category.color }"
                    aria-hidden="true"
                ></i>
                <span class="min-w-0 flex-1 truncate">{{ category.name }}</span>
                <span
                    data-testid="legend-count"
                    class="font-mono text-[10.5px] text-sd-ink-3 tabular-nums"
                    >{{ category.count }}</span
                >
            </div>
        </template>

        <template v-if="data.links.length || data.untyped">
            <div
                data-testid="legend-section"
                class="mt-2.5 mb-1 font-mono text-[9.5px] tracking-[0.09em] text-sd-ink-3 uppercase first:mt-2"
            >
                Ligações
            </div>
            <div class="divide-y divide-sd-line">
                <div
                    v-for="link in data.links"
                    :key="link.slug"
                    data-testid="legend-link"
                    :data-kind="link.slug"
                    class="pt-1 pb-[5px]"
                >
                    <div class="flex items-center gap-[7px]">
                        <LegendLine :dash="link.dash" :bidir="link.bidir" />
                        <span
                            data-testid="legend-badge"
                            class="font-mono text-[10px] font-semibold tracking-[0.02em] text-sd-ink-2"
                            >{{ link.badge }}</span
                        >
                        <span class="truncate text-[11.5px] text-sd-ink-3">{{
                            link.name
                        }}</span>
                    </div>
                    <div
                        data-testid="legend-gloss"
                        class="mt-0.5 pl-[47px] text-[10.5px] leading-[1.35] text-sd-ink-3"
                    >
                        {{ link.gloss }}
                    </div>
                </div>

                <div
                    v-if="data.untyped"
                    data-testid="legend-link"
                    data-kind=""
                    class="pt-1 pb-[5px]"
                >
                    <div class="flex items-center gap-[7px]">
                        <LegendLine />
                        <span class="truncate text-[11.5px] text-sd-ink-3">{{
                            UNTYPED_NAME
                        }}</span>
                    </div>
                    <div
                        data-testid="legend-gloss"
                        class="mt-0.5 pl-[47px] text-[10.5px] leading-[1.35] text-sd-ink-3"
                    >
                        {{ UNTYPED_GLOSS }}
                    </div>
                </div>
            </div>
        </template>

        <template v-if="data.sequence">
            <div
                data-testid="legend-section"
                class="mt-2.5 mb-1 font-mono text-[9.5px] tracking-[0.09em] text-sd-ink-3 uppercase first:mt-2"
            >
                Sequência
            </div>
            <div data-testid="legend-sequence" class="pt-1 pb-[5px]">
                <div class="flex items-center gap-[7px]">
                    <span class="flex w-10 flex-none justify-center">
                        <b
                            data-testid="legend-order-pill"
                            class="grid place-items-center rounded-full bg-sd-ink-3 font-mono text-[9px] leading-none font-semibold text-sd-paper tabular-nums"
                            :style="samplePill"
                            >1</b
                        >
                    </span>
                    <span class="truncate text-[11.5px] text-sd-ink-3">{{
                        ORDER_NAME
                    }}</span>
                </div>
                <div
                    data-testid="legend-gloss"
                    class="mt-0.5 pl-[47px] text-[10.5px] leading-[1.35] text-sd-ink-3"
                >
                    {{ ORDER_GLOSS }}
                </div>
            </div>
        </template>
    </div>
</template>
