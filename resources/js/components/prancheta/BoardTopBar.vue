<script setup lang="ts">
import BoardUserMenu from '@/components/prancheta/BoardUserMenu.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import type { ChipState } from '@/components/prancheta/StateChip.vue';
import StateChip from '@/components/prancheta/StateChip.vue';

withDefaults(
    defineProps<{
        problemName?: string | null;
        saveState?: ChipState;
        saveLabel?: string;
    }>(),
    { problemName: null, saveState: 'saved', saveLabel: undefined },
);

defineEmits<{
    'pick-problem': [];
    'open-sessions': [];
    'toggle-theme': [];
    'export-svg': [];
    save: [];
}>();
</script>

<template>
    <div
        data-testid="topbar"
        class="z-30 col-span-full flex items-center gap-2.5 border-b border-sd-line bg-sd-panel px-3"
    >
        <div
            class="flex items-center gap-2 border-r border-sd-line pr-2.5 text-sm font-bold tracking-[-0.01em] text-sd-ink"
        >
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="text-sd-accent"
                aria-hidden="true"
            >
                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                <rect x="14" y="14" width="7" height="7" rx="1.5" />
                <path d="M10 6.5h2.5a1.5 1.5 0 0 1 1.5 1.5v9.5" />
                <path d="M6.5 10v3" />
            </svg>
            <span class="whitespace-nowrap">Prancheta</span>
        </div>

        <PranchetaButton
            data-testid="problem-picker"
            @click="$emit('pick-problem')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path
                    d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l2 2.5h6.5A1.5 1.5 0 0 1 20 8v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5z"
                />
            </svg>
            <span data-testid="problem-name">{{
                problemName ?? 'Escolher problema'
            }}</span>
        </PranchetaButton>

        <PranchetaButton
            data-testid="sessions-button"
            @click="$emit('open-sessions')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M4 7h16M4 12h16M4 17h10" />
            </svg>
            Sessões
        </PranchetaButton>

        <div data-testid="topbar-spacer" class="flex-1"></div>

        <StateChip
            data-testid="save-chip"
            :state="saveState"
            :label="saveLabel"
        />

        <PranchetaButton
            data-testid="theme-button"
            variant="icon"
            title="Alternar tema"
            aria-label="Alternar tema"
            @click="$emit('toggle-theme')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M20 13.5A8 8 0 0 1 10.5 4a8 8 0 1 0 9.5 9.5z" />
            </svg>
        </PranchetaButton>

        <PranchetaButton
            data-testid="export-button"
            title="Baixar o diagrama em SVG"
            @click="$emit('export-svg')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M12 4v11m0 0 4-4m-4 4-4-4" />
                <path d="M5 18.5h14" />
            </svg>
            SVG
        </PranchetaButton>

        <PranchetaButton
            data-testid="save-button"
            variant="primary"
            @click="$emit('save')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path
                    d="M5 5.5A1.5 1.5 0 0 1 6.5 4h9L20 8.5v10A1.5 1.5 0 0 1 18.5 20h-12A1.5 1.5 0 0 1 5 18.5z"
                />
                <path d="M8.5 4v5h7" />
                <path d="M8.5 20v-5h7v5" />
            </svg>
            Salvar
        </PranchetaButton>

        <BoardUserMenu data-testid="user-menu" />
    </div>
</template>
