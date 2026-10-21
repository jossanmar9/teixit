#!/usr/bin/env python3
# Teixit: Gestió lliure per a associacions — visor propi per a Linux
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# La finestra de Teixit, amb WebKitGTK (el motor web de GNOME). No és cap navegador: no té pestanyes
# ni barra d'adreces i només obri les pàgines de Teixit; els enllaços de fora s'obrin amb el navegador
# habitual. El llança teixit.sh, que arranca el servidor i, quan esta finestra es tanca, fa la còpia.
#
# Ús: teixit-visor.py URL [--comprova]
#   --comprova  només diu si es pot utilitzar (eixida 0) o no (eixida 1)
# TEIXIT_PROVA_VISOR=fitxer: escriu el resultat de carregar la primera pàgina i es tanca (proves automàtiques)

import os
import sys
from urllib.parse import urlparse

os.environ.setdefault("WEBKIT_DISABLE_DMABUF_RENDERER", "1")  # evita finestres en blanc amb alguns controladors gràfics


def sandbox_possible():
    """WebKit aïlla les pàgines amb bubblewrap. Si el sistema no ho permet (per exemple, Ubuntu 24.04 en
    alguns ordinadors), les pàgines no es veurien. Teixit només mostra les seues pàgines locals, així que
    en eixe cas es desactiva l'aïllament."""
    import shutil
    import subprocess
    bwrap = shutil.which("bwrap")
    if not bwrap:
        return True
    try:
        return subprocess.run([bwrap, "--ro-bind", "/", "/", "--unshare-user", "true"],
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=10).returncode == 0
    except (OSError, subprocess.SubprocessError):
        return True

try:
    import gi
    gi.require_version("Gtk", "3.0")
    try:
        gi.require_version("WebKit2", "4.1")
    except ValueError:
        gi.require_version("WebKit2", "4.0")
    from gi.repository import Gio, GLib, Gtk, WebKit2
except (ImportError, ValueError):
    sys.exit(1)

if "--comprova" in sys.argv:
    sys.exit(0)

ES = (os.environ.get("LC_ALL") or os.environ.get("LC_MESSAGES") or os.environ.get("LANG") or "").startswith("es")


def t(ca, es):
    return es if ES else ca


ARREL = os.path.dirname(os.path.realpath(__file__))
ICONA = os.path.join(ARREL, "app", "assets", "icona.png")
DADES = os.path.join(os.environ.get("XDG_DATA_HOME") or os.path.expanduser("~/.local/share"), "teixit", "visor")
CACHE = os.path.join(os.environ.get("XDG_CACHE_HOME") or os.path.expanduser("~/.cache"), "teixit", "visor")


class Visor:
    def __init__(self, url):
        self.url = url
        u = urlparse(url)
        self.servidor = (u.hostname, u.port)
        self.finestres = []
        self.carregat = False

        os.makedirs(DADES, exist_ok=True)
        gestor = WebKit2.WebsiteDataManager(base_data_directory=DADES, base_cache_directory=CACHE)
        self.context = WebKit2.WebContext.new_with_website_data_manager(gestor)
        self.context.get_cookie_manager().set_persistent_storage(
            os.path.join(DADES, "galetes.sqlite"), WebKit2.CookiePersistentStorage.SQLITE)
        self.context.connect("download-started", self.baixada)

        self.principal = self.finestra(self.vista_nova(), principal=True)
        self.principal.vista.load_uri(url)

    # Pàgines i finestres

    def vista_nova(self, relacionada=None):
        if relacionada is not None:
            vista = WebKit2.WebView.new_with_related_view(relacionada)
        else:
            vista = WebKit2.WebView.new_with_context(self.context)
        s = vista.get_settings()
        s.set_enable_developer_extras(False)
        s.set_javascript_can_open_windows_automatically(True)
        s.set_enable_back_forward_navigation_gestures(True)
        vista.connect("decide-policy", self.decideix)
        vista.connect("create", self.crea)
        vista.connect("close", lambda v: v.finestra.destroy())
        vista.connect("notify::title", self.titol)
        vista.connect("load-changed", self.carrega)
        return vista

    def finestra(self, vista, principal=False):
        f = Gtk.Window(title="Teixit")
        f.set_default_size(1280, 860) if principal else f.set_default_size(1000, 820)
        if os.path.isfile(ICONA):
            f.set_icon_from_file(ICONA)
        f.set_wmclass("teixit", "Teixit")
        f.add(vista)
        f.vista = vista
        vista.finestra = f
        if principal:
            f.connect("destroy", lambda *_: Gtk.main_quit())
        else:
            f.connect("destroy", lambda w: self.finestres.remove(w) if w in self.finestres else None)
            self.finestres.append(f)
        f.show_all()
        return f

    def crea(self, vista, accio):
        uri = accio.get_request().get_uri()
        if not self.es_local(uri):
            self.obri_fora(uri)
            return None
        nova = self.vista_nova(relacionada=vista)
        nova.connect("ready-to-show", lambda v: v.finestra.present())
        self.finestra(nova)
        return nova

    def titol(self, vista, _param):
        vista.finestra.set_title(vista.get_title() or "Teixit")

    def carrega(self, vista, esdeveniment):
        if esdeveniment != WebKit2.LoadEvent.FINISHED or self.carregat or vista is not self.principal.vista:
            return
        self.carregat = True
        prova = os.environ.get("TEIXIT_PROVA_VISOR")
        if prova:
            with open(prova, "w", encoding="utf-8") as f:
                f.write("ok " + (vista.get_title() or ""))
            GLib.timeout_add(1000, lambda: (self.principal.destroy(), False)[1])

    # Què es pot obrir ací i què s'obri fora

    def es_local(self, uri):
        u = urlparse(uri)
        if u.scheme in ("about", "data", "blob"):
            return True
        return u.scheme == "http" and u.hostname in ("localhost", "127.0.0.1") and u.port == self.servidor[1]

    def obri_fora(self, uri):
        if urlparse(uri).scheme in ("http", "https", "mailto"):
            try:
                Gio.AppInfo.launch_default_for_uri(uri, None)
            except GLib.Error:
                pass

    def decideix(self, vista, decisio, tipus):
        if tipus in (WebKit2.PolicyDecisionType.NAVIGATION_ACTION, WebKit2.PolicyDecisionType.NEW_WINDOW_ACTION):
            uri = decisio.get_navigation_action().get_request().get_uri()
            if not self.es_local(uri):
                decisio.ignore()
                self.obri_fora(uri)
                return True
        elif tipus == WebKit2.PolicyDecisionType.RESPONSE:
            # El que no es pot mostrar (CSV, còpies, PDF) es baixa
            if not decisio.is_mime_type_supported():
                decisio.download()
                return True
        return False

    # Baixades: es guarden a la carpeta Baixades; els PDF s'obrin amb el programa habitual

    def baixada(self, _context, baixada):
        baixada.connect("decide-destination", self.desti)
        baixada.connect("finished", self.baixada_feta)
        baixada.connect("failed", lambda b, error: self.avis(Gtk.MessageType.ERROR,
                                                             t("No s'ha pogut guardar el fitxer.", "No se ha podido guardar el archivo."),
                                                             error.message))

    def desti(self, baixada, suggerit):
        carpeta = GLib.get_user_special_dir(GLib.UserDirectory.DIRECTORY_DOWNLOAD) or os.path.expanduser("~")
        os.makedirs(carpeta, exist_ok=True)
        nom = os.path.basename(suggerit or "") or "teixit"
        base, ext = os.path.splitext(nom)
        cami, n = os.path.join(carpeta, nom), 1
        while os.path.exists(cami):
            n += 1
            cami = os.path.join(carpeta, "%s (%d)%s" % (base, n, ext))
        baixada.cami = cami
        baixada.set_destination(GLib.filename_to_uri(cami, None))
        return True

    def baixada_feta(self, baixada):
        cami = getattr(baixada, "cami", None)
        if not cami or not os.path.exists(cami):
            return
        if cami.lower().endswith(".pdf"):
            self.obri_fora_fitxer(cami)
            return
        dialeg = Gtk.MessageDialog(transient_for=self.principal, modal=True, message_type=Gtk.MessageType.INFO,
                                   buttons=Gtk.ButtonsType.NONE, text=t("S'ha guardat el fitxer", "Se ha guardado el archivo"))
        dialeg.format_secondary_text(cami)
        dialeg.add_button(t("Obri la carpeta", "Abrir la carpeta"), 1)
        dialeg.add_button(t("D'acord", "Aceptar"), Gtk.ResponseType.OK)
        if dialeg.run() == 1:
            self.obri_fora_fitxer(os.path.dirname(cami))
        dialeg.destroy()

    @staticmethod
    def obri_fora_fitxer(cami):
        try:
            Gio.AppInfo.launch_default_for_uri(GLib.filename_to_uri(cami, None), None)
        except GLib.Error:
            pass

    def avis(self, tipus, text, detall=""):
        d = Gtk.MessageDialog(transient_for=self.principal, modal=True, message_type=tipus,
                              buttons=Gtk.ButtonsType.OK, text=text)
        if detall:
            d.format_secondary_text(detall)
        d.run()
        d.destroy()


def main():
    if len(sys.argv) < 2:
        print("Ús: teixit-visor.py URL", file=sys.stderr)
        return 2
    if not sandbox_possible():
        os.environ["WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS"] = "1"
    GLib.set_prgname("teixit")
    GLib.set_application_name("Teixit")
    Visor(sys.argv[1])
    Gtk.main()
    return 0


if __name__ == "__main__":
    sys.exit(main())
