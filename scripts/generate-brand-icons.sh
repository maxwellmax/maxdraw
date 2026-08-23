#!/usr/bin/env bash
#
# generate-brand-icons.sh
#
# Gera os três ícones do app — public/favicon.svg, public/favicon.ico e
# public/apple-touch-icon.png — a partir do mark maxdraw
# (.spec/init/design/logo/maxdraw-mark.svg), a fonte única dos três.
#
# Execução única, no host, pelo desenvolvedor: os binários resultantes entram
# versionados. Não é passo de build, não entra em package.json/composer.json e
# não acrescenta dependência ao projeto — o rasterizador é pacote de sistema,
# instalado por apt-get fora do repositório.
#
# Uso:
#   bash scripts/generate-brand-icons.sh
#
set -euo pipefail

readonly REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly MARK="${REPO_ROOT}/.spec/init/design/logo/maxdraw-mark.svg"
readonly PUBLIC_DIR="${REPO_ROOT}/public"
readonly TOUCH_ICON_SIZE=180
readonly TOUCH_ICON_BACKGROUND='#0A0E13'
readonly ICO_SIZES=(16 32 48)

fail() {
    echo "generate-brand-icons: $*" >&2
    exit 1
}

has() {
    command -v "$1" >/dev/null 2>&1
}

# Rasterizador do SVG (PNG) e empacotador do .ico, resolvidos por command -v.
PNG_BACKEND=''
ICO_BACKEND=''

resolve_backends() {
    if has rsvg-convert; then
        PNG_BACKEND='rsvg-convert'
    elif has magick; then
        PNG_BACKEND='magick'
    elif has convert; then
        PNG_BACKEND='convert'
    else
        fail "nenhum rasterizador de SVG encontrado (rsvg-convert, magick, convert). Instale um no host: 'sudo apt-get install librsvg2-bin' (rsvg-convert) ou 'sudo apt-get install imagemagick' (magick/convert)."
    fi

    if has magick; then
        ICO_BACKEND='magick'
    elif has convert; then
        ICO_BACKEND='convert'
    elif has icotool; then
        ICO_BACKEND='icotool'
    else
        fail "rasterizador '${PNG_BACKEND}' encontrado, mas nenhum empacotador de .ico (icotool, magick, convert). Instale no host: 'sudo apt-get install icoutils' (icotool) ou 'sudo apt-get install imagemagick' (magick/convert)."
    fi
}

# Renderiza o mark em PNG quadrado. $2 é a cor de fundo ou 'none' para preservar
# a transparência do mark.
render_png() {
    local size="$1" background="$2" output="$3"

    case "${PNG_BACKEND}" in
        rsvg-convert)
            rsvg-convert --width "${size}" --height "${size}" --background-color "${background}" --output "${output}" "${MARK}"
            ;;
        magick | convert)
            "${PNG_BACKEND}" -background "${background}" "${MARK}" -resize "${size}x${size}" -flatten "${output}"
            ;;
    esac

    [[ -s "${output}" ]] || fail "o rasterizador '${PNG_BACKEND}' devolveu um arquivo vazio em ${output}."
}

build_ico() {
    local output="$1"
    shift

    case "${ICO_BACKEND}" in
        magick | convert)
            "${ICO_BACKEND}" "$@" "${output}"
            ;;
        icotool)
            icotool --create --output "${output}" "$@"
            ;;
    esac

    [[ -s "${output}" ]] || fail "o empacotador '${ICO_BACKEND}' devolveu um arquivo vazio em ${output}."
}

resolve_backends

[[ -f "${MARK}" ]] || fail "mark de origem não encontrado em ${MARK}."
[[ -d "${PUBLIC_DIR}" ]] || fail "diretório público não encontrado em ${PUBLIC_DIR}."

echo "generate-brand-icons: rasterizador '${PNG_BACKEND}', empacotador de .ico '${ICO_BACKEND}'."

cp "${MARK}" "${PUBLIC_DIR}/favicon.svg"
echo "generate-brand-icons: public/favicon.svg = cópia do mark."

render_png "${TOUCH_ICON_SIZE}" "${TOUCH_ICON_BACKGROUND}" "${PUBLIC_DIR}/apple-touch-icon.png"
echo "generate-brand-icons: public/apple-touch-icon.png = ${TOUCH_ICON_SIZE}×${TOUCH_ICON_SIZE} sobre ${TOUCH_ICON_BACKGROUND}."

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

ico_frames=()

for size in "${ICO_SIZES[@]}"; do
    frame="${WORK_DIR}/favicon-${size}.png"
    render_png "${size}" 'none' "${frame}"
    ico_frames+=("${frame}")
done

build_ico "${PUBLIC_DIR}/favicon.ico" "${ico_frames[@]}"
echo "generate-brand-icons: public/favicon.ico = ${ICO_SIZES[*]} px."

echo "generate-brand-icons: pronto. Commite os três arquivos de public/."
