#!/usr/bin/env bash
# Teixit: Gestió lliure per a associacions — llançador per a Linux
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Arranca el servidor de PHP només a 127.0.0.1 i obri Teixit en la seua finestra (teixit-visor.py, amb
# WebKitGTK). Quan es tanca la finestra, fa una còpia de seguretat si hi ha hagut canvis i s'atura.
# Si WebKitGTK no hi és, prova Chromium, Google Chrome, Microsoft Edge o Brave en mode aplicació i, si
# tampoc, obri el navegador habitual i Teixit funciona mentre el terminal estiga obert (Ctrl+C per a tancar-lo).
#
# Variables opcionals: TEIXIT_DADES (carpeta de dades), TEIXIT_PHP (ruta de php), TEIXIT_PORT,
# TEIXIT_NAVEGADOR=0 (no obrir res), TEIXIT_FINESTRA=0 (navegador habitual en lloc de finestra pròpia).

set -u

ARREL="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
DADES="${TEIXIT_DADES:-${XDG_DATA_HOME:-$HOME/.local/share}/teixit/dades}"
PHP="${TEIXIT_PHP:-php}"
PORT=8750
NAVEGADOR=1
FINESTRA=1

# Missatges en valencià o castellà segons l'idioma del sistema
case "${LC_ALL:-${LC_MESSAGES:-${LANG:-}}}" in
    es*) ES=1 ;;
    *) ES=0 ;;
esac
t() { if [ "$ES" = 1 ]; then printf '%s\n' "$2"; else printf '%s\n' "$1"; fi; }

EN_TERMINAL=0
[ -t 1 ] && EN_TERMINAL=1

# Missatge en una finestra, si s'ha obert des del menú (sense terminal)
avis_grafic() {
    local tipus="$1" text="$2"
    if command -v zenity >/dev/null 2>&1; then
        zenity "--$tipus" --title="Teixit" --width=420 --text="$text" >/dev/null 2>&1
    elif command -v kdialog >/dev/null 2>&1; then
        if [ "$tipus" = error ]; then kdialog --title "Teixit" --error "$text"; else kdialog --title "Teixit" --msgbox "$text"; fi >/dev/null 2>&1
    elif command -v xmessage >/dev/null 2>&1; then
        xmessage -center "$text" >/dev/null 2>&1
    else
        return 1
    fi
}

error() {
    printf '\n%s\n\n' "$1" >&2
    if [ "$EN_TERMINAL" = 1 ]; then
        # Si el terminal s'ha obert des del menú, que l'error no desaparega en un instant
        if [ -t 0 ]; then
            read -r -p "$(t 'Prem Retorn per a tancar.' 'Pulsa Intro para cerrar.')" _ || true
        fi
    else
        avis_grafic error "$1"
    fi
    exit 1
}

# Configuració (mateix teixit.ini que a Windows). Si l'usuari en té un a ~/.config/teixit, mana el seu
# (amb el paquet .deb el programa és a /usr/share/teixit i no s'hi pot escriure).
CONFIG_USUARI="${XDG_CONFIG_HOME:-$HOME/.config}/teixit/teixit.ini"
for INI in "$ARREL/teixit.ini" "$CONFIG_USUARI"; do
    [ -f "$INI" ] || continue
    while IFS='=' read -r clau valor; do
        clau="$(printf '%s' "$clau" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]')"
        valor="$(printf '%s' "${valor:-}" | tr -d '[:space:]\r')"
        case "$clau" in
            port) [[ "$valor" =~ ^[0-9]+$ ]] && [ "$valor" -gt 1023 ] && [ "$valor" -lt 65536 ] && PORT="$valor" ;;
            navegador) case "$valor" in 0|no|false) NAVEGADOR=0 ;; esac ;;
            finestra) case "$valor" in 0|no|false) FINESTRA=0 ;; esac ;;
        esac
    done < <(grep -v '^[[:space:]]*[;#]' "$INI")
done
PORT="${TEIXIT_PORT:-$PORT}"
NAVEGADOR="${TEIXIT_NAVEGADOR:-$NAVEGADOR}"
FINESTRA="${TEIXIT_FINESTRA:-$FINESTRA}"
URL="http://localhost:$PORT/"

# Finestra pròpia de Teixit (WebKitGTK)
VISOR=""
if [ "$NAVEGADOR" = 1 ] && [ "$FINESTRA" = 1 ] && [ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ] \
   && command -v python3 >/dev/null 2>&1 && python3 "$ARREL/teixit-visor.py" --comprova 2>/dev/null; then
    VISOR=1
fi

# Si no, un navegador en mode aplicació (sense pestanyes ni barra d'adreces)
APP=""
if [ "$NAVEGADOR" = 1 ] && [ "$FINESTRA" = 1 ]; then
    for b in chromium chromium-browser google-chrome google-chrome-stable microsoft-edge microsoft-edge-stable brave-browser; do
        if command -v "$b" >/dev/null 2>&1; then APP="$(command -v "$b")"; break; fi
    done
fi
# Perfil separat del navegador de l'usuari. El Chromium en snap no pot escriure a carpetes ocultes del $HOME.
PERFIL="${XDG_DATA_HOME:-$HOME/.local/share}/teixit/finestra"
case "$(readlink -f "$APP" 2>/dev/null)" in
    /snap/*) PERFIL="$HOME/snap/$(basename "$(readlink -f "$APP")")/common/teixit-finestra" ;;
esac

obri_finestra() {
    mkdir -p "$PERFIL" 2>/dev/null
    "$APP" --app="$URL" --user-data-dir="$PERFIL" --no-first-run --no-default-browser-check \
        --disable-background-mode --disable-sync --window-size=1280,860 >/dev/null 2>&1
}

# Obert des del menú, sense finestra pròpia possible: cal un terminal perquè l'usuari puga tancar Teixit
if [ "$EN_TERMINAL" = 0 ] && [ "$NAVEGADOR" = 1 ] && [ -z "$VISOR" ] && [ -z "$APP" ] && [ -z "${TEIXIT_EN_TERMINAL:-}" ] \
   && [ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ]; then
    export TEIXIT_EN_TERMINAL=1
    JO="$(readlink -f "$0")"
    if command -v x-terminal-emulator >/dev/null 2>&1; then exec x-terminal-emulator -e "$JO"
    elif command -v gnome-terminal >/dev/null 2>&1; then exec gnome-terminal -- "$JO"
    elif command -v konsole >/dev/null 2>&1; then exec konsole -e "$JO"
    elif command -v xfce4-terminal >/dev/null 2>&1; then exec xfce4-terminal -x "$JO"
    elif command -v xterm >/dev/null 2>&1; then exec xterm -e "$JO"
    fi
fi

# PHP i extensions
command -v "$PHP" >/dev/null 2>&1 || error "$(t \
    "No s'ha trobat PHP. Instal·la'l (Debian, Ubuntu, Linux Mint): sudo apt install php-cli php-sqlite3 php-mbstring php-xml" \
    "No se ha encontrado PHP. Instálalo (Debian, Ubuntu, Linux Mint): sudo apt install php-cli php-sqlite3 php-mbstring php-xml")"
"$PHP" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || error "$(t \
    "Teixit necessita PHP 8.1 o posterior. Ara tens el $("$PHP" -r 'echo PHP_VERSION;')." \
    "Teixit necesita PHP 8.1 o posterior. Ahora tienes el $("$PHP" -r 'echo PHP_VERSION;').")"
FALTEN="$("$PHP" -r 'foreach (["pdo_sqlite" => "php-sqlite3", "mbstring" => "php-mbstring"] as $e => $p) { if (!extension_loaded($e)) echo "$p "; }')"
[ -z "$FALTEN" ] || error "$(t \
    "A PHP li falten extensions. Instal·la: sudo apt install $FALTEN" \
    "A PHP le faltan extensiones. Instala: sudo apt install $FALTEN")"
# Recomanades: sense elles Teixit funciona, però no es pot importar des d'Excel o xifrar les còpies
"$PHP" -r 'exit(extension_loaded("SimpleXML") ? 0 : 1);' || t \
    "Avís: sense php-xml no es poden importar fitxers d'Excel (sudo apt install php-xml)." \
    "Aviso: sin php-xml no se pueden importar archivos de Excel (sudo apt install php-xml)."
"$PHP" -r 'exit(extension_loaded("sodium") ? 0 : 1);' || t \
    "Avís: este PHP no porta sodium i no es poden xifrar les còpies externes." \
    "Aviso: este PHP no incluye sodium y no se pueden cifrar las copias externas."

mkdir -p "$DADES" || error "$(t "No es pot crear la carpeta de dades: $DADES" "No se puede crear la carpeta de datos: $DADES")"

# Fa una petició HTTP local amb el mateix PHP (així no cal curl ni wget)
peticio() { "$PHP" -r '$c = stream_context_create(["http" => ["timeout" => (float)$argv[2], "ignore_errors" => true]]); $r = @file_get_contents($argv[1], false, $c); if ($r === false) exit(1); echo $r;' "$1" "${2:-2}"; }

obri_navegador() {
    [ "$NAVEGADOR" = 1 ] || return 0
    if [ -n "$VISOR" ]; then
        setsid python3 "$ARREL/teixit-visor.py" "$URL" >/dev/null 2>&1 &
    elif [ -n "$APP" ]; then
        obri_finestra &
    elif command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$URL" >/dev/null 2>&1 &
    fi
}

# Ja està obert? Només cal obrir el navegador.
if RESPOSTA="$(peticio "$URL" 1)"; then
    if printf '%s' "$RESPOSTA" | grep -q 'Teixit'; then
        t "Teixit ja està obert: $URL" "Teixit ya está abierto: $URL"
        obri_navegador
        exit 0
    fi
    error "$(t \
        "El port $PORT l'utilitza un altre programa. Posa un altre port (per exemple, port = 8750) a $CONFIG_USUARI." \
        "El puerto $PORT lo usa otro programa. Pon otro puerto (por ejemplo, port = 8750) en $CONFIG_USUARI.")"
fi

TOKEN="$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')"
TEIXIT_DADES="$DADES" TEIXIT_TOKEN="$TOKEN" "$PHP" \
    -d display_errors=0 -d log_errors=1 -d error_log="$DADES/php-errors.log" \
    -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=64M -d max_execution_time=120 \
    -S "127.0.0.1:$PORT" -t "$ARREL/app" "$ARREL/app/index.php" >>"$DADES/servidor.log" 2>&1 &
SERVIDOR=$!

tanca() {
    local codi="${1:-0}"
    trap - EXIT INT TERM HUP
    if kill -0 "$SERVIDOR" 2>/dev/null; then
        t "Fent la còpia de seguretat i tancant Teixit…" "Haciendo la copia de seguridad y cerrando Teixit…"
        peticio "http://127.0.0.1:$PORT/?r=copia_tancament&token=$TOKEN" 60 >/dev/null || true
        kill "$SERVIDOR" 2>/dev/null
        wait "$SERVIDOR" 2>/dev/null
    fi
    exit "$codi"
}
trap 'tanca $?' EXIT
trap 'tanca 0' INT TERM HUP

# Espera que el servidor responga
for _ in $(seq 1 50); do
    peticio "$URL" 1 >/dev/null && break
    kill -0 "$SERVIDOR" 2>/dev/null || error "$(t \
        "Teixit no ha pogut arrancar. Mira $DADES/servidor.log" \
        "Teixit no ha podido arrancar. Mira $DADES/servidor.log")"
    sleep 0.2
done

printf '\n  Teixit · %s\n\n' "$URL"
t "  Les dades es guarden a: $DADES" "  Los datos se guardan en: $DADES"

if [ -n "$VISOR" ]; then
    t "  Teixit s'ha obert en la seua finestra. En tancar-la, es fa la còpia de seguretat i s'atura." \
      "  Teixit se ha abierto en su ventana. Al cerrarla, se hace la copia de seguridad y se detiene."
    echo
    python3 "$ARREL/teixit-visor.py" "$URL" 2>>"$DADES/visor.log" && exit 0
    # Si el visor falla, es continua amb un navegador
    t "  No s'ha pogut obrir la finestra de Teixit (mira $DADES/visor.log)." \
      "  No se ha podido abrir la ventana de Teixit (mira $DADES/visor.log)."
    VISOR=""
fi

if [ -n "$APP" ]; then
    t "  Teixit s'ha obert en una finestra pròpia. En tancar-la, es fa la còpia de seguretat i s'atura." \
      "  Teixit se ha abierto en una ventana propia. Al cerrarla, se hace la copia de seguridad y se detiene."
    echo
    INICI=$SECONDS
    obri_finestra
    # Si el navegador torna de seguida, la finestra l'ha agafada un altre procés amb el mateix perfil:
    # no sabem quan es tancarà, així que es continua com amb el navegador habitual.
    if [ $((SECONDS - INICI)) -ge 5 ] || ! kill -0 "$SERVIDOR" 2>/dev/null; then
        exit 0   # el trap fa la còpia i atura el servidor
    fi
    NAVEGADOR=0   # la finestra ja està oberta
    if [ "$EN_TERMINAL" = 0 ]; then
        avis_grafic info "$(t "Teixit està en marxa. Prem D'acord quan vulgues tancar-lo (es farà una còpia de seguretat)." \
                             "Teixit está en marcha. Pulsa Aceptar cuando quieras cerrarlo (se hará una copia de seguridad).")" && exit 0
    fi
fi

t "  Deixa esta finestra oberta mentre utilitzes Teixit. Per a tancar-lo, prem Ctrl+C o tanca la finestra." \
  "  Deja esta ventana abierta mientras usas Teixit. Para cerrarlo, pulsa Ctrl+C o cierra la ventana."
echo
obri_navegador
wait "$SERVIDOR"
