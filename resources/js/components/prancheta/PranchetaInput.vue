<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { computed, ref } from 'vue';

/*
 * O campo de texto da prancheta: mesma borda, mesmo fundo e mesmo anel de foco
 * do `.fld input` do protótipo, escritos com os tokens `sd-` da Phase 6.
 */

const props = withDefaults(
    defineProps<{
        type?: 'text' | 'email' | 'password';
    }>(),
    { type: 'text' },
);

defineOptions({ inheritAttrs: false });

const revealed = ref(false);

const inputType = computed(() =>
    props.type === 'password' && revealed.value ? 'text' : props.type,
);
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs"
            :type="inputType"
            class="h-[34px] w-full rounded-md border border-sd-line-2 bg-sd-panel-2 px-2.5 font-sd-ui text-[13px] text-sd-ink transition-colors outline-none placeholder:text-sd-ink-3 focus:border-sd-accent focus:bg-sd-panel focus:shadow-[0_0_0_3px_var(--accent-soft)]"
            :class="{ 'pr-10': type === 'password' }"
        />
        <button
            v-if="type === 'password'"
            type="button"
            :tabindex="-1"
            :aria-label="revealed ? 'Esconder a senha' : 'Mostrar a senha'"
            class="absolute inset-y-0 right-0 flex cursor-pointer items-center px-3 text-sd-ink-3 transition-colors hover:text-sd-ink"
            @click="revealed = !revealed"
        >
            <EyeOff v-if="revealed" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
