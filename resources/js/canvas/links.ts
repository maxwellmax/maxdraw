/**
 * Os tipos de ligação como o servidor os entrega (`LinkTypeResource`). O tipo
 * manda no traço e no selo, nunca na cor: a cor da seta é sempre a da categoria
 * do bloco de origem (`database-schema.md` → Notes & Conventions).
 */

export type LinkType = {
    id: number;
    slug: string;
    name: string;
    badge_label: string;
    dash_array: string | null;
    is_bidirectional_default: boolean;
    gloss: string;
};

/** O tipo de ligação por slug, na ordem em que o catálogo o entrega. */
export type LinkTypeIndex = Map<string, LinkType>;

/**
 * O traço de quem foi tracejado na mão. Vale só quando o tipo escolhido não tem
 * padrão próprio — `http` tracejado no botão fica com este, `retry` continua com
 * o `2 4.5` dele.
 */
export const MANUAL_DASH_ARRAY = '5 4.5';

export function indexLinkTypes(types: readonly LinkType[]): LinkTypeIndex {
    return new Map(types.map((type) => [type.slug, type]));
}

/** "Sem tipo" é uma ligação legítima: string vazia e nulo caem no mesmo lugar. */
export function linkTypeOf(
    index: LinkTypeIndex,
    kind: string | null,
): LinkType | null {
    return (kind ? index.get(kind) : null) ?? null;
}

/** O padrão de traço do tipo, antes de a bandeira manual opinar. */
export function dashArrayOf(
    index: LinkTypeIndex,
    kind: string | null,
): string | null {
    return linkTypeOf(index, kind)?.dash_array ?? null;
}

export function isDashedByDefault(
    index: LinkTypeIndex,
    kind: string | null,
): boolean {
    return dashArrayOf(index, kind) !== null;
}

export function isBidirectionalByDefault(
    index: LinkTypeIndex,
    kind: string | null,
): boolean {
    return linkTypeOf(index, kind)?.is_bidirectional_default ?? false;
}

export function badgeLabelOf(
    index: LinkTypeIndex,
    kind: string | null,
): string {
    return linkTypeOf(index, kind)?.badge_label ?? '';
}

/**
 * A amostra do traço para o menu de tipos, como valor de `background`: contínuo
 * é cor cheia, tracejado e pontilhado repetem o próprio `dash_array` — é o
 * padrão real da seta, não um desenho parecido (US-4.1).
 */
export function dashSample(dash: string | null): string {
    if (dash === null) {
        return 'currentColor';
    }

    const [on, off] = dash.split(/\s+/).map(Number);

    return `repeating-linear-gradient(90deg, currentColor 0 ${on}px, transparent ${on}px ${on + off}px)`;
}
