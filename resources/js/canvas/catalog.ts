/**
 * O catálogo de componentes como o servidor o entrega (`CatalogService`): as
 * categorias trazem a cor e os componentes dentro delas. O motor só lê — a cor
 * de um bloco é sempre a da categoria, e não existe caminho para o usuário
 * mudá-la.
 */

export type CatalogComponent = {
    slug: string;
    name: string;
    short_name: string;
    icon_key: string;
};

export type CatalogCategory = {
    slug: string;
    name: string;
    color_token: string;
    components: CatalogComponent[];
};

export type CatalogEntry = {
    component: CatalogComponent;
    category: CatalogCategory;
};

/** O componente e a categoria dele, por slug do componente. */
export type ComponentIndex = Map<string, CatalogEntry>;

/**
 * A categoria de quem usa o sistema. É a única que o motor precisa reconhecer
 * pelo nome: a numeração do fluxo inteiro começa pelo cliente (US-4.3).
 */
export const CLIENT_CATEGORY = 'client';

export function indexComponents(
    categories: readonly CatalogCategory[],
): ComponentIndex {
    const index: ComponentIndex = new Map();

    for (const category of categories) {
        for (const component of category.components) {
            index.set(component.slug, { component, category });
        }
    }

    return index;
}

/**
 * A cor de um tipo de bloco, como referência CSS pronta para o `style`. Um tipo
 * fora do catálogo cai no cinza de texto — o desenho antigo continua legível.
 */
export function colorOf(index: ComponentIndex, slug: string): string {
    return `var(${index.get(slug)?.category.color_token ?? '--ink-3'})`;
}

export function shortNameOf(index: ComponentIndex, slug: string): string {
    return index.get(slug)?.component.short_name ?? slug;
}

export function categoryOf(index: ComponentIndex, slug: string): string {
    return index.get(slug)?.category.slug ?? '';
}

export function isClientComponent(
    index: ComponentIndex,
    slug: string,
): boolean {
    return categoryOf(index, slug) === CLIENT_CATEGORY;
}

/**
 * As categorias do catálogo na ordem em que ele as entrega, sem repetição. O
 * índice é por componente, então a mesma categoria aparece uma vez por bloco
 * dela — e é a primeira aparição que fixa a posição.
 */
export function catalogCategories(index: ComponentIndex): CatalogCategory[] {
    const categories = new Map<string, CatalogCategory>();

    for (const entry of index.values()) {
        categories.set(entry.category.slug, entry.category);
    }

    return [...categories.values()];
}
