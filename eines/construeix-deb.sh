#!/usr/bin/env bash
# Construeix el paquet .deb de Teixit a partir de la carpeta del programa ja preparada
# (la mateixa del .tar.gz: app, src, docs amb els PDF, teixit.sh, teixit.ini…).
# Ús: eines/construeix-deb.sh <carpeta Teixit> <versió> <carpeta d'eixida>
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

ORIGEN="$(readlink -f "$1")"
VERSIO="$2"
EIXIDA="$(readlink -f "$3")"
ARREL="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
P="$TMP/teixit"

# Programa a /usr/share/teixit (sense els scripts d'instal·lació manual, que ací no calen)
mkdir -p "$P/usr/share/teixit" "$P/usr/bin" "$P/usr/share/applications" "$P/usr/share/icons/hicolor/256x256/apps" "$P/usr/share/doc/teixit" "$P/DEBIAN"
cp -a "$ORIGEN/." "$P/usr/share/teixit/"
rm -f "$P/usr/share/teixit/installa.sh" "$P/usr/share/teixit/desinstalla.sh"
rm -rf "$P/usr/share/teixit/data"
chmod 755 "$P/usr/share/teixit/teixit.sh"
ln -s ../share/teixit/teixit.sh "$P/usr/bin/teixit"

cp "$ORIGEN/app/assets/icona.png" "$P/usr/share/icons/hicolor/256x256/apps/teixit.png"
cat > "$P/usr/share/applications/teixit.desktop" <<DESKTOP
[Desktop Entry]
Type=Application
Name=Teixit
GenericName=Gestió de l'associació
GenericName[es]=Gestión de la asociación
Comment=Gestió lliure per a associacions
Comment[es]=Gestión libre para asociaciones
Exec=teixit
Icon=teixit
Terminal=false
Categories=Office;Education;
DESKTOP

cp "$ARREL/launcher/linux/deb/copyright" "$P/usr/share/doc/teixit/copyright"
gzip -9n -c "$ORIGEN/CHANGELOG.md" > "$P/usr/share/doc/teixit/changelog.gz"

# Permisos estàndard: directoris 755, fitxers 644, el llançador executable
find "$P/usr" -type d -exec chmod 755 {} +
find "$P/usr" -type f -exec chmod 644 {} +
chmod 755 "$P/usr/share/teixit/teixit.sh"

MIDA="$(du -sk "$P/usr" | cut -f1)"
sed -e "s/@VERSIO@/$VERSIO/" -e "s/@MIDA@/$MIDA/" "$ARREL/launcher/linux/deb/control" > "$P/DEBIAN/control"

mkdir -p "$EIXIDA"
dpkg-deb --root-owner-group -Zxz --build "$P" "$EIXIDA/teixit_${VERSIO}_all.deb"
