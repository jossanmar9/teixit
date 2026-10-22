#!/usr/bin/env bash
# Genera els manuals i les guies en PDF (docs/pdf). No es guarden al repositori:
# els fa GitHub Actions en publicar una versió i la web, i es poden fer a mà amb esta ordre.
# Cal PHP i Chrome o Chromium.
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu
cd "$(dirname "$0")/.."
NAV="${TEIXIT_NAVEGADOR:-}"
if [ -z "$NAV" ]; then
    for n in google-chrome google-chrome-stable chromium chromium-browser /opt/pw-browsers/chromium; do
        if command -v "$n" >/dev/null 2>&1; then NAV="$n"; break; fi
    done
fi
[ -n "$NAV" ] || { echo "Cal Chrome o Chromium per a fer els PDF" >&2; exit 1; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p docs/pdf
for d in manual-usuari manual-usuario manual-administracio manual-administracion guia-installacio guia-instalacion; do
    php eines/document-html.php "docs/$d.md" "$TMP/$d.html"
    "$NAV" --headless=new --no-sandbox --disable-gpu --no-first-run --user-data-dir="$TMP/perfil" \
        --no-pdf-header-footer --print-to-pdf="$PWD/docs/pdf/$d.pdf" "file://$TMP/$d.html" >/dev/null 2>&1
    [ -s "docs/pdf/$d.pdf" ] || { echo "No s'ha generat docs/pdf/$d.pdf" >&2; exit 1; }
    echo "docs/pdf/$d.pdf"
done
