<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BrandLockup from '@/components/BrandLockup.vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import PranchetaInput from '@/components/prancheta/PranchetaInput.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';

/*
 * A recuperação de senha está desligada no v1 (`Features::resetPasswords()`
 * comentado em `config/fortify.php`), então o Wayfinder não gera os helpers de
 * `@/routes/password` — as URLs padrão do Fortify entram literais até a feature
 * ser religada.
 */

defineOptions({
    layout: {
        title: 'Entrar na sua conta',
        description: 'Informe e-mail e senha para retomar o treino',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Entrar" />

    <BrandLockup data-testid="brand-lockup" class="mb-6" />

    <div
        v-if="status"
        class="mb-4 text-center text-[13px] font-medium text-sd-ok"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <label for="email" class="text-[12.5px] font-medium text-sd-ink-2">
                E-mail
            </label>
            <PranchetaInput
                id="email"
                type="email"
                name="email"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="voce@exemplo.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <label
                    for="password"
                    class="text-[12.5px] font-medium text-sd-ink-2"
                >
                    Senha
                </label>
                <Link
                    v-if="canResetPassword"
                    href="/forgot-password"
                    class="text-[12.5px] text-sd-accent underline-offset-4 hover:underline"
                    :tabindex="5"
                >
                    Esqueceu a senha?
                </Link>
            </div>
            <PranchetaInput
                id="password"
                type="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Sua senha"
            />
            <InputError :message="errors.password" />
        </div>

        <label
            for="remember"
            class="flex items-center gap-2.5 text-[12.5px] text-sd-ink-2"
        >
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <span>Lembrar de mim</span>
        </label>

        <PranchetaButton
            variant="primary"
            type="submit"
            class="w-full justify-center"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Entrar
        </PranchetaButton>

        <p class="text-center text-[12.5px] text-sd-ink-2">
            Ainda não tem conta?
            <Link
                :href="register()"
                class="text-sd-accent underline-offset-4 hover:underline"
                :tabindex="5"
            >
                Criar conta
            </Link>
        </p>
    </Form>
</template>
