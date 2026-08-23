/**
 * O download no cliente: um Blob e um clique num link temporário. Nada trafega
 * pelo servidor — o arquivo nasce e morre no navegador (US-9.1).
 */
export function downloadFile(
    filename: string,
    contents: string,
    mimeType: string,
): void {
    const url = URL.createObjectURL(new Blob([contents], { type: mimeType }));
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename;
    anchor.rel = 'noopener';

    document.body.append(anchor);
    anchor.click();
    anchor.remove();

    URL.revokeObjectURL(url);
}
