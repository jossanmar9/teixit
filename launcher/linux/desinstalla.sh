#!/usr/bin/env bash
# Desinstal·la Teixit. Les dades i les còpies de seguretat NO s'esborren.
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu
BASE="${XDG_DATA_HOME:-$HOME/.local/share}"
case "${LC_ALL:-${LC_MESSAGES:-${LANG:-}}}" in es*) ES=1 ;; *) ES=0 ;; esac
t() { if [ "$ES" = 1 ]; then printf '%s\n' "$2"; else printf '%s\n' "$1"; fi; }
rm -rf "$BASE/teixit/programa"
rm -f "$HOME/.local/bin/teixit" "$BASE/applications/teixit.desktop" "$BASE/icons/hicolor/256x256/apps/teixit.png"
t "Teixit s'ha desinstal·lat. Les dades es conserven a $BASE/teixit/dades (esborra-la tu si ja no les vols)." \
  "Teixit se ha desinstalado. Los datos se conservan en $BASE/teixit/dades (bórrala tú si ya no los quieres)."
