<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    description?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const titleId = useId();
const sheet = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);

function close(): void {
    emit('update:open', false);
}

function onOverlayPointerDown(event: PointerEvent): void {
    if (event.target === event.currentTarget) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            trigger.value =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;

            document.addEventListener('keydown', onKeydown);

            await nextTick();
            sheet.value?.focus();

            return;
        }

        document.removeEventListener('keydown', onKeydown);
        trigger.value?.focus();
        trigger.value = null;
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            data-testid="overlay"
            class="fixed inset-0 z-100 grid place-items-center p-7"
            style="
                background: color-mix(
                    in srgb,
                    var(--paper) 62%,
                    rgba(0, 0, 0, 0.55)
                );
            "
            @pointerdown="onOverlayPointerDown"
        >
            <div
                ref="sheet"
                data-testid="sheet"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                class="flex max-h-[86vh] w-[min(760px,100%)] flex-col overflow-hidden rounded-xl border border-sd-line-2 bg-sd-panel shadow-sd-2 outline-none"
            >
                <header
                    class="flex items-center gap-3 border-b border-sd-line px-5 pt-4 pb-[13px]"
                >
                    <div>
                        <h2
                            :id="titleId"
                            class="m-0 text-base font-bold tracking-[-0.015em] text-sd-ink"
                        >
                            {{ title }}
                        </h2>
                        <p
                            v-if="description"
                            class="mt-0.5 mb-0 text-[12.5px] text-sd-ink-3"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <div class="flex-1"></div>
                    <slot name="actions" />
                </header>

                <div class="overflow-y-auto overscroll-contain p-3">
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="flex items-center gap-2 border-t border-sd-line bg-sd-panel-2 px-4 py-[11px]"
                >
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
