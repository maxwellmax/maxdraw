<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import { getInitials } from '@/composables/useInitials';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

const page = usePage();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<InstanceType<typeof PranchetaButton> | null>(null);

const user = computed(() => page.props.auth.user);
const initials = computed(() => getInitials(user.value.name));

function close(): void {
    open.value = false;
}

/**
 * Escape fecha o menu e devolve o foco ao gatilho: sem isso o teclado cairia no
 * início da barra a cada fechamento.
 */
function closeFromKeyboard(): void {
    close();
    trigger.value?.$el?.focus();
}

function onFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget;

    if (!(next instanceof Node) || !root.value?.contains(next)) {
        close();
    }
}
</script>

<template>
    <div
        ref="root"
        class="relative"
        @keydown.esc="closeFromKeyboard"
        @focusout="onFocusOut"
    >
        <PranchetaButton
            ref="trigger"
            data-testid="user-menu-trigger"
            aria-haspopup="menu"
            :aria-expanded="open"
            title="Conta"
            @click="open = !open"
        >
            <span
                data-testid="user-menu-initials"
                class="inline-flex h-[19px] min-w-[19px] items-center justify-center rounded-full border border-sd-line-2 bg-sd-panel-2 px-1 text-[10px] font-semibold text-sd-ink-2"
                aria-hidden="true"
                >{{ initials }}</span
            >
            <span
                data-testid="user-menu-name"
                class="sr-only max-w-[140px] truncate md:not-sr-only"
                >{{ user.name }}</span
            >
        </PranchetaButton>

        <div
            v-if="open"
            data-testid="user-menu-popup"
            role="menu"
            class="absolute top-[calc(100%+6px)] right-0 z-[40] min-w-[186px] rounded-sd border border-sd-line-2 bg-sd-panel p-1 shadow-sd-2"
        >
            <Link
                data-testid="user-menu-settings"
                role="menuitem"
                :href="edit()"
                class="block w-full rounded-md px-2 py-1.5 text-left text-[12.5px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink"
                @click="close"
            >
                Configurações
            </Link>

            <div class="mx-0.5 my-1 h-px bg-sd-line"></div>

            <Link
                data-testid="user-menu-logout"
                role="menuitem"
                :href="logout()"
                as="button"
                class="block w-full cursor-pointer rounded-md px-2 py-1.5 text-left text-[12.5px] text-sd-ink-2 hover:bg-sd-panel-2 hover:text-sd-ink"
                @click="router.flushAll()"
            >
                Sair
            </Link>
        </div>
    </div>
</template>
