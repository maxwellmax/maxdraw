<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BrandLockup from '@/components/BrandLockup.vue';
import InputError from '@/components/InputError.vue';
import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';
import PranchetaInput from '@/components/prancheta/PranchetaInput.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Criar uma conta',
        description: 'Preencha os dados abaixo para começar a treinar',
    },
});
</script>

<template>
    <Head title="Criar conta" />

    <BrandLockup data-testid="brand-lockup" class="mb-6" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <label for="name" class="text-[12.5px] font-medium text-sd-ink-2">
                Nome
            </label>
            <PranchetaInput
                id="name"
                type="text"
                name="name"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Seu nome"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <label for="email" class="text-[12.5px] font-medium text-sd-ink-2">
                E-mail
            </label>
            <PranchetaInput
                id="email"
                type="email"
                name="email"
                required
                :tabindex="2"
                autocomplete="email"
                placeholder="voce@exemplo.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <label
                for="password"
                class="text-[12.5px] font-medium text-sd-ink-2"
            >
                Senha
            </label>
            <PranchetaInput
                id="password"
                type="password"
                name="password"
                required
                :tabindex="3"
                autocomplete="new-password"
                placeholder="Mínimo de 8 caracteres"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <label
                for="password_confirmation"
                class="text-[12.5px] font-medium text-sd-ink-2"
            >
                Confirmar senha
            </label>
            <PranchetaInput
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                placeholder="Repita a senha"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <PranchetaButton
            variant="primary"
            type="submit"
            class="w-full justify-center"
            :tabindex="5"
            :disabled="processing"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" />
            Criar conta
        </PranchetaButton>

        <p class="text-center text-[12.5px] text-sd-ink-2">
            Já tem conta?
            <Link
                :href="login()"
                class="text-sd-accent underline-offset-4 hover:underline"
                :tabindex="6"
            >
                Entrar
            </Link>
        </p>
    </Form>
</template>
