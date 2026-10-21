// Teixit: Gestió lliure per a associacions
// Visor propi per a Windows: la finestra de Teixit, amb el component WebView2 de Windows.
// No és cap navegador: no té pestanyes ni barra d'adreces i només obri les pàgines de Teixit;
// els enllaços de fora s'obrin amb el navegador habitual.
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Es compila amb el csc.exe de .NET Framework 4 (C# 5) i les biblioteques del SDK de WebView2.

using System;
using System.Diagnostics;
using System.Drawing;
using System.Globalization;
using System.IO;
using System.Runtime.InteropServices;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace Teixit
{
    class Visor : Form
    {
        static CoreWebView2Environment entorn;   // compartit per totes les finestres

        readonly Finestra principal;
        readonly int port;
        readonly string url;
        readonly bool esPrincipal;
        readonly CoreWebView2NewWindowRequestedEventArgs peticio;
        readonly CoreWebView2Deferral ajornament;
        readonly WebView2 web = new WebView2();
        readonly Label avis = new Label();
        bool carregat;
        bool tancant;

        const int WM_SYSCOMMAND = 0x112;
        const int ID_CARPETA = 0x1F10;
        [DllImport("user32.dll")] static extern IntPtr GetSystemMenu(IntPtr hWnd, bool bRevert);
        [DllImport("user32.dll", CharSet = CharSet.Unicode)] static extern bool AppendMenu(IntPtr hMenu, int uFlags, int uIDNewItem, string lpNewItem);

        public static string Carpeta
        {
            get { return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Teixit", "visor"); }
        }

        static string FitxerPosicio
        {
            get { return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Teixit", "finestra.txt"); }
        }

        /// <summary>Hi ha el component WebView2 de Windows?</summary>
        public static bool Disponible()
        {
            try { return !string.IsNullOrEmpty(CoreWebView2Environment.GetAvailableBrowserVersionString()); }
            catch { return false; }
        }

        /// <summary>Finestra principal de Teixit</summary>
        public Visor(Finestra principal, int port, string url) : this(principal, port, url, null, null) { }

        /// <summary>Finestra per a una pàgina que s'obri a part (impressions, documents, manuals)</summary>
        Visor(Finestra principal, int port, string url, CoreWebView2NewWindowRequestedEventArgs peticio, CoreWebView2Deferral ajornament)
        {
            this.principal = principal;
            this.port = port;
            this.url = url;
            this.peticio = peticio;
            this.ajornament = ajornament;
            esPrincipal = peticio == null;

            Text = "Teixit";
            Icon = principal.Icon;
            BackColor = Color.White;
            StartPosition = FormStartPosition.WindowsDefaultLocation;
            Size = esPrincipal ? new Size(1280, 860) : new Size(1000, 820);
            MinimumSize = new Size(640, 480);
            if (esPrincipal) RecuperaPosicio();

            web.Dock = DockStyle.Fill;
            web.DefaultBackgroundColor = Color.White;
            Controls.Add(web);

            avis.Dock = DockStyle.Fill;
            avis.BackColor = Color.White;
            avis.ForeColor = Color.FromArgb(93, 103, 120);
            avis.Font = new Font("Segoe UI", 12f);
            avis.TextAlign = ContentAlignment.MiddleCenter;
            avis.Text = Textos.T("Obrint Teixit…", "Abriendo Teixit…");
            Controls.Add(avis);
            avis.BringToFront();
            if (!esPrincipal) avis.Visible = false;

            Load += delegate { Inicia(); };
            FormClosing += Tancant;
        }

        async void Inicia()
        {
            try
            {
                if (entorn == null)
                {
                    Directory.CreateDirectory(Carpeta);
                    // Sense SmartScreen: Teixit només obri pàgines d'este ordinador i no cal enviar-les enlloc
                    var opcions = new CoreWebView2EnvironmentOptions("--disable-features=msSmartScreenProtection", CultureInfo.CurrentUICulture.Name);
                    entorn = await CoreWebView2Environment.CreateAsync(null, Carpeta, opcions);
                }
                await web.EnsureCoreWebView2Async(entorn);
            }
            catch (Exception ex)
            {
                if (!esPrincipal)
                {
                    try { ajornament.Complete(); } catch { }
                    Close();
                    return;
                }
                tancant = true;   // es tanca sense aturar Teixit: el llançador prova una altra manera
                Hide();
                principal.VisorHaFallat(ex.Message);
                return;
            }

            CoreWebView2 c = web.CoreWebView2;
            c.Settings.AreDevToolsEnabled = false;
            c.Settings.IsStatusBarEnabled = false;
            c.Settings.IsPasswordAutosaveEnabled = false;
            c.Settings.IsGeneralAutofillEnabled = false;

            c.DocumentTitleChanged += delegate { Text = string.IsNullOrEmpty(c.DocumentTitle) ? "Teixit" : c.DocumentTitle; };
            c.NavigationStarting += (s, e) =>
            {
                if (EsLocal(e.Uri)) return;
                e.Cancel = true;
                ObriFora(e.Uri);
            };
            c.NewWindowRequested += (s, e) =>
            {
                if (!EsLocal(e.Uri)) { e.Handled = true; ObriFora(e.Uri); return; }
                // Les pàgines per a imprimir i els documents s'obrin en una altra finestra de Teixit
                var finestra = new Visor(principal, port, null, e, e.GetDeferral());
                finestra.Show();
            };
            c.WindowCloseRequested += delegate { Close(); };
            c.ProcessFailed += (s, e) =>
            {
                if (e.ProcessFailedKind == CoreWebView2ProcessFailedKind.BrowserProcessExited) return;
                try { c.Reload(); } catch { }
            };
            web.NavigationCompleted += (s, e) =>
            {
                if (carregat) return;
                carregat = true;
                avis.Visible = false;
                if (esPrincipal) ProvaAutomatica(e.IsSuccess, c.DocumentTitle);
            };

            if (esPrincipal)
            {
                c.Navigate(url);
            }
            else
            {
                peticio.NewWindow = c;
                peticio.Handled = true;
                ajornament.Complete();
            }
        }

        /// <summary>A GitHub Actions es comprova que el visor obri Teixit: TEIXIT_PROVA_VISOR és el fitxer del resultat</summary>
        void ProvaAutomatica(bool ok, string titol)
        {
            string fitxer = Environment.GetEnvironmentVariable("TEIXIT_PROVA_VISOR");
            if (string.IsNullOrEmpty(fitxer)) return;
            try { File.WriteAllText(fitxer, (ok ? "ok " : "error ") + titol); } catch { }
            var t = new Timer { Interval = 1000 };
            t.Tick += delegate { t.Stop(); Close(); };
            t.Start();
        }

        bool EsLocal(string adreca)
        {
            Uri u;
            if (!Uri.TryCreate(adreca, UriKind.Absolute, out u)) return true;
            if (u.Scheme == "about" || u.Scheme == "data" || u.Scheme == "blob") return true;
            if (u.Scheme != Uri.UriSchemeHttp) return false;
            return (u.Host == "localhost" || u.Host == "127.0.0.1") && u.Port == port;
        }

        static void ObriFora(string adreca)
        {
            Uri u;
            if (!Uri.TryCreate(adreca, UriKind.Absolute, out u)) return;
            if (u.Scheme != Uri.UriSchemeHttp && u.Scheme != Uri.UriSchemeHttps && u.Scheme != Uri.UriSchemeMailto) return;
            try { Process.Start(new ProcessStartInfo(u.AbsoluteUri) { UseShellExecute = true }); } catch { }
        }

        /// <summary>Porta la finestra al davant (quan es torna a obrir Teixit mentre ja està en marxa)</summary>
        public void Porta()
        {
            if (WindowState == FormWindowState.Minimized) WindowState = FormWindowState.Normal;
            Show();
            Activate();
        }

        // «Carpeta de dades» al menú de la finestra (clic a la icona de dalt a l'esquerra, o Alt+Espai)
        protected override void OnHandleCreated(EventArgs e)
        {
            base.OnHandleCreated(e);
            if (!esPrincipal) return;
            try
            {
                IntPtr menu = GetSystemMenu(Handle, false);
                AppendMenu(menu, 0x800, 0, null);
                AppendMenu(menu, 0, ID_CARPETA, Textos.T("Carpeta de dades de Teixit", "Carpeta de datos de Teixit"));
            }
            catch { }
        }

        protected override void WndProc(ref Message m)
        {
            if (m.Msg == WM_SYSCOMMAND && (m.WParam.ToInt64() & 0xFFFF) == ID_CARPETA)
            {
                principal.ObriCarpeta();
                return;
            }
            base.WndProc(ref m);
        }

        void Tancant(object sender, FormClosingEventArgs e)
        {
            if (!esPrincipal || tancant) return;
            GuardaPosicio();
            if (e.CloseReason != CloseReason.UserClosing) return;
            // Es manté la finestra amb l'avís mentre es fa la còpia; després el programa s'acaba
            e.Cancel = true;
            tancant = true;
            avis.Text = Textos.T("Guardant la còpia de seguretat i tancant Teixit…", "Guardando la copia de seguridad y cerrando Teixit…");
            avis.Visible = true;
            avis.BringToFront();
            Cursor = Cursors.WaitCursor;
            Refresh();
            BeginInvoke((MethodInvoker)principal.TancaDesDelVisor);
        }

        void RecuperaPosicio()
        {
            try
            {
                if (!File.Exists(FitxerPosicio)) return;
                string[] p = File.ReadAllText(FitxerPosicio).Trim().Split(' ');
                var r = new Rectangle(int.Parse(p[0]), int.Parse(p[1]), int.Parse(p[2]), int.Parse(p[3]));
                bool visible = false;
                foreach (Screen s in Screen.AllScreens)
                    if (s.WorkingArea.IntersectsWith(r)) visible = true;
                if (!visible || r.Width < 640 || r.Height < 480) return;
                StartPosition = FormStartPosition.Manual;
                Bounds = r;
                if (p.Length > 4 && p[4] == "1") WindowState = FormWindowState.Maximized;
            }
            catch { }
        }

        void GuardaPosicio()
        {
            try
            {
                Rectangle r = WindowState == FormWindowState.Normal ? Bounds : RestoreBounds;
                Directory.CreateDirectory(Path.GetDirectoryName(FitxerPosicio));
                File.WriteAllText(FitxerPosicio, string.Format(CultureInfo.InvariantCulture, "{0} {1} {2} {3} {4}",
                    r.X, r.Y, r.Width, r.Height, WindowState == FormWindowState.Maximized ? 1 : 0));
            }
            catch { }
        }
    }
}
