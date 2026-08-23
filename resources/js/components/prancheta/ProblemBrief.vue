<script setup lang="ts">
import ProblemItemList from '@/components/prancheta/ProblemItemList.vue';
import type { ProblemOption } from '@/prancheta/problems';
import { NO_PROBLEM_LABEL, TOPICS_SUMMARY } from '@/prancheta/problems';

defineProps<{ problem: ProblemOption | null }>();
</script>

<template>
    <div data-testid="enunciado">
        <p
            v-if="!problem"
            data-testid="problem-empty"
            class="mt-1 text-[11.5px] leading-[1.5] text-sd-ink-3"
        >
            Nenhum problema escolhido. Abra
            <b>{{ NO_PROBLEM_LABEL }}</b> na barra de cima para carregar um
            enunciado — ou use a prancheta livre.
        </p>

        <template v-else>
            <h2
                data-testid="problem-title"
                class="m-0 mb-0.5 text-[18px] font-bold tracking-[-0.015em] text-balance text-sd-ink"
            >
                {{ problem.name }}
            </h2>

            <div
                data-testid="problem-tag"
                class="mb-2.5 font-sd-mono text-[10px] tracking-[0.08em] text-sd-ink-3 uppercase"
            >
                {{ problem.tag }} ·
                <span data-testid="problem-level">{{
                    problem.level_name
                }}</span>
            </div>

            <p
                data-testid="problem-context"
                class="m-0 mb-4 font-sd-serif text-[15px] leading-[1.6] text-sd-ink-2"
            >
                {{ problem.context }}
            </p>

            <div
                class="mt-4 mb-[7px] flex items-center gap-[7px] font-sd-mono text-[10px] font-semibold tracking-[0.1em] text-sd-ink-3 uppercase after:h-px after:flex-1 after:bg-sd-line after:content-['']"
            >
                Requisitos funcionais
            </div>
            <ProblemItemList :items="problem.requirements" />

            <div
                class="mt-4 mb-[7px] flex items-center gap-[7px] font-sd-mono text-[10px] font-semibold tracking-[0.1em] text-sd-ink-3 uppercase after:h-px after:flex-1 after:bg-sd-line after:content-['']"
            >
                Escala e restrições
            </div>
            <ProblemItemList :items="problem.scale" variant="scale" />

            <!-- O gabarito nasce fechado em toda sessão: `details` sem `open`
                 recolhe de novo a cada carregamento, e a chave por problema
                 refaz isso ao trocar de enunciado. A ferramenta não julga o
                 desenho — abrir é decisão do usuário, e nada mais acontece. -->
            <details
                :key="problem.id"
                data-testid="topics-reveal"
                class="group mt-[18px] overflow-hidden rounded-sd border border-sd-line"
            >
                <summary
                    data-testid="topics-summary"
                    class="flex cursor-pointer list-none items-center gap-[7px] border-sd-line bg-sd-panel-2 px-3 py-[9px] text-[12px] text-sd-ink-3 group-open:border-b hover:text-sd-ink-2 [&::-webkit-details-marker]:hidden"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-[13px] shrink-0"
                        aria-hidden="true"
                    >
                        <path
                            d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"
                        />
                        <circle cx="12" cy="12" r="2.6" />
                    </svg>
                    {{ TOPICS_SUMMARY }}
                </summary>

                <div data-testid="topics-body" class="px-3 py-[11px]">
                    <ProblemItemList :items="problem.topics" />
                </div>
            </details>
        </template>
    </div>
</template>
