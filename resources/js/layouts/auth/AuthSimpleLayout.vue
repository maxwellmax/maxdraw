<script setup lang="ts">
/*
 * As telas de conta usam os tokens da prancheta (Phase 6): claro e escuro saem
 * do mesmo `data-theme` do resto do app, sem variante de tema do starter
 * kit nem cor literal.
 *
 * O layout não carrega marca: quem precisa dela renderiza o lockup na própria
 * página (Login e Register), o que evita a marca duplicada e deixa as demais
 * telas de conta apenas com título e descrição.
 */

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        data-testid="auth-shell"
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-sd-paper p-6 font-sd-ui text-sd-ink md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-7">
                <div class="flex flex-col items-center gap-4">
                    <div class="space-y-2 text-center">
                        <h1
                            class="text-lg font-bold tracking-[-0.01em] text-sd-ink"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-center text-[13px] text-sd-ink-2">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-sd border border-sd-line bg-sd-panel p-6 shadow-sd-2"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
