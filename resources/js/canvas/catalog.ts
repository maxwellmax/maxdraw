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
