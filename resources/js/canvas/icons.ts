/**
 * Mapa de ícones do motor do canvas — a chave é o `icon_key` do catálogo de
 * componentes e o valor é o miolo do `<svg viewBox="0 0 24 24">` do protótipo.
 *
 * Os traços herdam `stroke="currentColor"` do wrapper; as formas cheias trazem
 * `fill="currentColor"` explícito porque o wrapper desenha só contorno.
 */
const FILLED = 'fill="currentColor" stroke="none"';

export const CANVAS_ICONS = {
    browser: `<rect x="3" y="4.5" width="18" height="15" rx="2"/><path d="M3 9h18"/><circle cx="6" cy="6.75" r=".7" ${FILLED}/><circle cx="8.4" cy="6.75" r=".7" ${FILLED}/>`,
    mobile: `<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 18.5h3"/>`,
    thirdparty: `<path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 13 11"/><path d="M19 14v4.5A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-11A1.5 1.5 0 0 1 6.5 6H11"/>`,
    dns: `<circle cx="12" cy="12" r="8.4"/><path d="M3.6 12h16.8"/><ellipse cx="12" cy="12" rx="4" ry="8.4"/>`,
    cdn: `<circle cx="12" cy="12" r="4"/><circle cx="12" cy="3.7" r="1.6"/><circle cx="4.9" cy="17" r="1.6"/><circle cx="19.1" cy="17" r="1.6"/><path d="M12 5.3v2.7M8.5 14.3 6.3 15.7M15.5 14.3l2.2 1.4"/>`,
    lb: `<rect x="8.5" y="2.8" width="7" height="5" rx="1.4"/><rect x="2.2" y="16.2" width="5.6" height="5" rx="1.4"/><rect x="9.2" y="16.2" width="5.6" height="5" rx="1.4"/><rect x="16.2" y="16.2" width="5.6" height="5" rx="1.4"/><path d="M12 7.8v4.4"/><path d="M5 16.2v-2.5a1.5 1.5 0 0 1 1.5-1.5h11a1.5 1.5 0 0 1 1.5 1.5v2.5"/><path d="M12 12.2v4"/>`,
    gateway: `<path d="M5 20.5V7A3 3 0 0 1 8 4h8a3 3 0 0 1 3 3v13.5"/><path d="M2.6 20.5h18.8"/><path d="M9.6 20.5V14h4.8v6.5"/>`,
    service: `<path d="M12 2.9 19.9 7.4v9.2L12 21.1 4.1 16.6V7.4z"/><circle cx="12" cy="12" r="2.6"/>`,
    api: `<path d="M9.2 4.6 4.8 12l4.4 7.4"/><path d="M14.8 4.6 19.2 12l-4.4 7.4"/><circle cx="12" cy="12" r="1.35" ${FILLED}/>`,
    graphql: `<path d="M11 6.4 6.2 14.7M13 6.4l4.8 8.3M7.4 17.4h9.2"/><circle cx="12" cy="4.3" r="2.1"/><circle cx="18.6" cy="17.4" r="2.1"/><circle cx="5.4" cy="17.4" r="2.1"/>`,
    grpc: `<rect x="2.6" y="4.4" width="18.8" height="15.2" rx="2.6"/><path d="M6.2 10h9.4"/><path d="M13.2 7.6 15.6 10l-2.4 2.4"/><path d="M17.8 14.4H8.4"/><path d="M10.8 12 8.4 14.4l2.4 2.4"/>`,
    waf: `<rect x="2.8" y="4.6" width="18.4" height="14.8" rx="2"/><path d="M2.8 9.5h18.4M2.8 14.5h18.4M9.4 4.6v4.9M14.6 9.5v5M9.4 14.5v4.9"/>`,
    replica: `<ellipse cx="12" cy="9.4" rx="6.6" ry="2.5"/><path d="M5.4 9.4v9c0 1.4 2.95 2.5 6.6 2.5s6.6-1.1 6.6-2.5v-9"/><path d="M18.6 14.6c0 1.4-2.95 2.5-6.6 2.5s-6.6-1.1-6.6-2.5"/><path d="M4.4 4.1h12.9"/><path d="M15.1 2.1 17.4 4.1l-2.3 2.1"/>`,
    worker: `<circle cx="12" cy="12" r="3.2"/><path d="M12 2.7v2.3M12 19v2.3M21.3 12H19M5 12H2.7M18.6 5.4l-1.6 1.6M7 17l-1.6 1.6M18.6 18.6 17 17M7 7 5.4 5.4"/>`,
    cron: `<circle cx="12" cy="12" r="8.4"/><path d="M12 7.3V12l3.3 2"/>`,
    ws: `<path d="M12 18.6h.01" stroke-width="2.4"/><path d="M8.6 15.2a4.8 4.8 0 0 1 6.8 0"/><path d="M5.6 11.9a9.1 9.1 0 0 1 12.8 0"/><path d="M2.7 8.7a13.3 13.3 0 0 1 18.6 0"/>`,
    sql: `<ellipse cx="12" cy="6" rx="7.4" ry="2.9"/><path d="M4.6 6v12c0 1.6 3.3 2.9 7.4 2.9s7.4-1.3 7.4-2.9V6"/><path d="M19.4 12c0 1.6-3.3 2.9-7.4 2.9S4.6 13.6 4.6 12"/>`,
    nosql: `<rect x="3" y="3.8" width="18" height="5" rx="1.6"/><rect x="3" y="9.5" width="18" height="5" rx="1.6"/><rect x="3" y="15.2" width="18" height="5" rx="1.6"/><circle cx="6.6" cy="6.3" r=".8" ${FILLED}/><circle cx="6.6" cy="12" r=".8" ${FILLED}/><circle cx="6.6" cy="17.7" r=".8" ${FILLED}/>`,
    cache: `<rect x="3" y="4.5" width="18" height="15" rx="2.5"/><path d="M13.2 7.6 9.5 12.8h3.1l-1.4 3.9 3.9-5.3h-3.2z"/>`,
    blob: `<ellipse cx="12" cy="6.3" rx="7.8" ry="2.7"/><path d="M4.2 6.3 5.7 19.5a1.7 1.7 0 0 0 1.7 1.5h9.2a1.7 1.7 0 0 0 1.7-1.5L19.8 6.3"/>`,
    search: `<circle cx="10.4" cy="10.4" r="6.2"/><path d="m15 15 5.2 5.2"/><path d="M7.9 9h5M7.9 11.8h3.4"/>`,
    warehouse: `<rect x="3" y="4" width="18" height="16" rx="2.2"/><path d="M7.6 16.2v-4.6M12 16.2V7.8M16.4 16.2v-2.9"/>`,
    queue: `<rect x="2.4" y="8" width="5.2" height="8" rx="1.4"/><rect x="9.4" y="8" width="5.2" height="8" rx="1.4"/><path d="M17.2 12h4.4"/><path d="M19 9.4 21.6 12 19 14.6"/>`,
    dlq: `<rect x="2.4" y="8" width="5.2" height="8" rx="1.4"/><rect x="9.4" y="8" width="5.2" height="8" rx="1.4"/><path d="M17.6 9.9 21.8 14.1"/><path d="M21.8 9.9 17.6 14.1"/>`,
    stream: `<path d="M2.6 7.4h13.2"/><path d="M13.4 5 15.8 7.4 13.4 9.8"/><path d="M2.6 12h17.6"/><path d="M17.8 9.6 20.2 12l-2.4 2.4"/><path d="M2.6 16.6h9.8"/><path d="M10 14.2l2.4 2.4L10 19"/>`,
    auth: `<path d="M12 2.9 19.8 6v6c0 4.5-3.2 7.8-7.8 9.1C7.4 19.8 4.2 16.5 4.2 12V6z"/><circle cx="12" cy="10.9" r="1.8"/><path d="M12 12.7V15"/>`,
    monitor: `<rect x="3" y="4.5" width="18" height="15" rx="2.2"/><path d="M6.4 13.6H9l1.6-3.7 2.1 6.3 1.7-4.3 1.1 1.7h2.2"/>`,
    config: `<path d="M3.4 7.6h9.2M17.4 7.6h3.2M3.4 16.4h3.2M11.4 16.4h9.2"/><circle cx="15" cy="7.6" r="2.4"/><circle cx="9" cy="16.4" r="2.4"/>`,
} satisfies Record<string, string>;

export type CanvasIconKey = keyof typeof CANVAS_ICONS;

export function hasCanvasIcon(key: string): key is CanvasIconKey {
    return Object.hasOwn(CANVAS_ICONS, key);
}
