<template>
    <aside
        data-testid="rail"
        class="flex flex-col overflow-y-auto overscroll-contain border-r border-sd-line bg-sd-panel pb-4"
    >
        <div
            data-testid="rail-head"
            class="px-3 pt-3 pb-1.5 font-mono text-[10px] font-semibold tracking-[0.1em] text-sd-ink-3 uppercase"
        >
            Componentes
        </div>
        <p
            data-testid="rail-hint"
            class="m-0 px-3 pb-2.5 text-[11.5px] leading-[1.4] text-sd-ink-3"
        >
            Clique para colocar no canvas. Arraste das bolinhas de um bloco para
            outro para ligar — depois escolha o tipo da seta (HTTP, gRPC,
            async…).
        </p>
        <div data-testid="palette">
            <slot />
        </div>
    </aside>
</template>
