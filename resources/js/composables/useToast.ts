import type { Ref } from 'vue';
import { readonly, ref } from 'vue';
import type { BoardWarning } from '@/prancheta/warnings';
import { BOARD_WARNINGS } from '@/prancheta/warnings';

export const TOAST_DURATION_MS = 2600;

export type Toast = {
    id: number;
    message: string;
};

export type UseToastReturn = {
    toasts: Readonly<Ref<readonly Toast[]>>;
    toast: (message: string) => number;
    warn: (warning: BoardWarning) => number;
    dismiss: (id: number) => void;
};

const toasts = ref<Toast[]>([]);

let lastId = 0;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((entry) => entry.id !== id);
}

function toast(message: string): number {
    const id = ++lastId;

    toasts.value = [...toasts.value, { id, message }];

    setTimeout(() => dismiss(id), TOAST_DURATION_MS);

    return id;
}

export function useToast(): UseToastReturn {
    return {
        toasts: readonly(toasts),
        toast,
        warn: (warning: BoardWarning) => toast(BOARD_WARNINGS[warning]),
        dismiss,
    };
}
