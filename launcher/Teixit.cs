// Teixit: Gestió lliure per a associacions
// Llançador per a Windows: arranca el servidor integrat de PHP i obri Teixit en la seua finestra (Visor.cs,
// amb el component WebView2 de Windows). Si no hi és, prova Microsoft Edge o Google Chrome en mode
// aplicació i, si tampoc, el navegador habitual amb la finestra menuda.
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Es compila amb el csc.exe de .NET Framework 4 (C# 5), sense dependències externes.

using System;
using System.Diagnostics;
using System.Drawing;
using System.Globalization;
using System.IO;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Runtime.CompilerServices;
using System.Text;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: System.Reflection.AssemblyTitle("Teixit")]
[assembly: System.Reflection.AssemblyDescription("Gestió lliure per a associacions")]
[assembly: System.Reflection.AssemblyProduct("Teixit")]
[assembly: System.Reflection.AssemblyCopyright("© 2026 José Vicente Sanjuan · AGPL-3.0-or-later")]

namespace Teixit
{
    static class Textos
    {
        static readonly bool Es = CultureInfo.CurrentUICulture.TwoLetterISOLanguageName == "es"
            && !CultureInfo.CurrentUICulture.Name.StartsWith("ca", StringComparison.OrdinalIgnoreCase);

        public static string T(string ca, string es) { return Es ? es : ca; }
    }

    static class Program
    {
        [STAThread]
        static void Main()
        {
            bool nou;
            using (var mutex = new Mutex(true, "Teixit-GestioSocietatsMusicals", out nou))
            {
                var config = Config.Llig();
                if (!nou)
                {
                    // Ja està en marxa: es demana a la instància oberta que mostre la seua finestra
                    try
                    {
                        using (var avis = EventWaitHandle.OpenExisting(Finestra.NomAvis)) avis.Set();
                        return;
                    }
                    catch { }
                    string url = "http://localhost:" + config.Port + "/";
                    if (!config.Finestra || AppNavegador.Obri(url) == null) Finestra.ObriNavegador(url);
                    return;
                }
                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new Finestra(config));
            }
        }
    }

    class Config
    {
        public int Port = 8750;
        public bool Navegador = true;
        public bool Finestra = true;
        public string Arrel;

        static bool Si(string v)
        {
            v = v.ToLowerInvariant();
            return !(v == "0" || v == "no" || v == "false");
        }

        public static Config Llig()
        {
            var c = new Config();
            c.Arrel = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\');
            string fitxer = Path.Combine(c.Arrel, "teixit.ini");
            if (!File.Exists(fitxer)) return c;
            foreach (string linia in File.ReadAllLines(fitxer))
            {
                string l = linia.Trim();
                if (l.Length == 0 || l.StartsWith(";") || l.StartsWith("#") || !l.Contains("=")) continue;
                string clau = l.Substring(0, l.IndexOf('=')).Trim().ToLowerInvariant();
                string valor = l.Substring(l.IndexOf('=') + 1).Trim();
                int n;
                if (clau == "port" && int.TryParse(valor, out n) && n > 1023 && n < 65536) c.Port = n;
                if (clau == "navegador") c.Navegador = Si(valor);
                if (clau == "finestra") c.Finestra = Si(valor);
            }
            return c;
        }
    }

    /// <summary>
    /// Finestra pròpia de Teixit: Microsoft Edge (o Google Chrome) en mode aplicació, sense pestanyes
    /// ni barra d'adreces, amb un perfil separat del navegador de l'usuari.
    /// </summary>
    static class AppNavegador
    {
        static string Perfil
        {
            get { return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Teixit", "finestra"); }
        }

        static string Troba()
        {
            string pf86 = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86);
            string pf = Environment.GetEnvironmentVariable("ProgramW6432");
            if (string.IsNullOrEmpty(pf)) pf = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
            string local = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string[] candidats =
            {
                RutaRegistrada("msedge.exe"),
                Path.Combine(pf86, @"Microsoft\Edge\Application\msedge.exe"),
                Path.Combine(pf, @"Microsoft\Edge\Application\msedge.exe"),
                Path.Combine(local, @"Microsoft\Edge\Application\msedge.exe"),
                RutaRegistrada("chrome.exe"),
                Path.Combine(pf, @"Google\Chrome\Application\chrome.exe"),
                Path.Combine(pf86, @"Google\Chrome\Application\chrome.exe"),
                Path.Combine(local, @"Google\Chrome\Application\chrome.exe")
            };
            foreach (string c in candidats)
            {
                try { if (!string.IsNullOrEmpty(c) && File.Exists(c)) return c; } catch { }
            }
            return null;
        }

        static string RutaRegistrada(string exe)
        {
            foreach (RegistryKey arrel in new[] { Registry.LocalMachine, Registry.CurrentUser })
            {
                try
                {
                    using (RegistryKey k = arrel.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\" + exe))
                    {
                        if (k == null) continue;
                        string v = k.GetValue(null) as string;
                        if (!string.IsNullOrEmpty(v)) return v.Trim('"');
                    }
                }
                catch { }
            }
            return null;
        }

        /// <summary>Obri una finestra de Teixit i en retorna el procés, o null si no hi ha Edge ni Chrome</summary>
        public static Process Obri(string url)
        {
            string exe = Troba();
            if (exe == null) return null;
            try
            {
                Directory.CreateDirectory(Perfil);
                string args = "--app=\"" + url + "\" --user-data-dir=\"" + Perfil + "\""
                    + " --no-first-run --no-default-browser-check --disable-background-mode --disable-sync"
                    + " --disable-features=msEdgeStartupBoost,msImplicitSignin,msSmartScreenProtection,Translate --window-size=1280,860";
                return Process.Start(new ProcessStartInfo(exe, args) { UseShellExecute = false });
            }
            catch { return null; }
        }
    }

    class Finestra : Form
    {
        readonly Config config;
        Process php;
        readonly Label estat = new Label();
        readonly LinkLabel enllac = new LinkLabel();
        readonly string token = Guid.NewGuid().ToString("N") + Guid.NewGuid().ToString("N");
        readonly Button obri = new Button();
        StreamWriter registre;
        bool tancant;
        bool visible;              // la finestra menuda només es mostra si cal (error, o sense Edge ni Chrome)
        bool arrancat;
        bool tancaSensePreguntar;  // l'usuari ha tancat la finestra de Teixit: no cal confirmar
        Process app;
        DateTime appObertA;
        NotifyIcon safata;
        Form visor;                // la finestra de Teixit (Visor), si el component WebView2 hi és
        EventWaitHandle avisObri;  // una segona execució de Teixit.exe demana que es mostre la finestra

        public const string NomAvis = @"Local\Teixit-ObriFinestra";

        string Url { get { return "http://localhost:" + config.Port + "/"; } }
        string Dades
        {
            get
            {
                string d = Environment.GetEnvironmentVariable("TEIXIT_DADES");
                return string.IsNullOrEmpty(d) ? Path.Combine(config.Arrel, "data") : d;
            }
        }

        public Finestra(Config c)
        {
            config = c;
            Text = "Teixit";
            try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch { }
            FormBorderStyle = FormBorderStyle.FixedSingle;
            MaximizeBox = false;
            StartPosition = FormStartPosition.CenterScreen;
            ClientSize = new Size(440, 300);
            BackColor = Color.White;
            Font = new Font("Segoe UI", 9.5f);

            var logo = new PictureBox { Location = new Point(20, 20), Size = new Size(72, 72), SizeMode = PictureBoxSizeMode.Zoom };
            try
            {
                using (var s = typeof(Finestra).Assembly.GetManifestResourceStream("icona.png"))
                    if (s != null) logo.Image = Image.FromStream(s);
            }
            catch { }
            Controls.Add(logo);

            Controls.Add(new Label { Text = "Teixit", Location = new Point(108, 22), AutoSize = true, Font = new Font("Segoe UI", 18f, FontStyle.Bold), ForeColor = Color.FromArgb(78, 49, 30) });
            Controls.Add(new Label { Text = Textos.T("Gestió lliure per a associacions", "Gestión libre para asociaciones"), Location = new Point(110, 62), AutoSize = true, ForeColor = Color.FromArgb(93, 103, 120) });

            estat.Location = new Point(20, 112);
            estat.Size = new Size(400, 22);
            estat.Text = Textos.T("Arrancant…", "Arrancando…");
            Controls.Add(estat);

            enllac.Location = new Point(20, 136);
            enllac.AutoSize = true;
            enllac.Text = Url;
            enllac.LinkClicked += delegate { ObriNavegador(Url); };
            Controls.Add(enllac);


            obri.Text = Textos.T("Obri Teixit", "Abrir Teixit");
            obri.Location = new Point(20, 212);
            obri.Size = new Size(130, 34);
            obri.Enabled = false;
            obri.Click += delegate { ObriFinestra(); };
            Controls.Add(obri);

            var carpeta = new Button { Text = Textos.T("Carpeta de dades", "Carpeta de datos"), Location = new Point(156, 212), Size = new Size(130, 34) };
            carpeta.Click += delegate { ObriCarpeta(); };
            Controls.Add(carpeta);

            var atura = new Button { Text = Textos.T("Atura i tanca", "Detener y cerrar"), Location = new Point(292, 212), Size = new Size(128, 34) };
            atura.Click += delegate { Close(); };
            Controls.Add(atura);

            Controls.Add(new Label
            {
                Text = Textos.T("Mentre esta finestra estiga oberta, Teixit funciona.", "Mientras esta ventana esté abierta, Teixit funciona."),
                Location = new Point(20, 262), AutoSize = true, ForeColor = Color.FromArgb(93, 103, 120), Font = new Font("Segoe UI", 8.5f)
            });

            visible = !config.Finestra;
            FormClosing += Tancant;
            EscoltaAvisos();
        }

        void EscoltaAvisos()
        {
            try { avisObri = new EventWaitHandle(false, EventResetMode.AutoReset, NomAvis); }
            catch { return; }
            var fil = new Thread(delegate ()
            {
                while (true)
                {
                    try
                    {
                        avisObri.WaitOne();
                        if (tancant || IsDisposed) return;
                        BeginInvoke((MethodInvoker)ObriFinestra);
                    }
                    catch { return; }
                }
            });
            fil.IsBackground = true;
            fil.Start();
        }

        void Log(string text)
        {
            if (registre == null) return;
            lock (registre) { try { registre.WriteLine("[Teixit] " + text); } catch { } }
        }

        // Mentre no cal, la finestra menuda queda amagada (però el programa continua en marxa)
        protected override void SetVisibleCore(bool value)
        {
            if (!IsHandleCreated) CreateHandle();
            if (!arrancat)
            {
                arrancat = true;
                BeginInvoke((MethodInvoker)Arranca);
            }
            base.SetVisibleCore(visible && value);
        }

        void Mostra()
        {
            if (visible) return;
            visible = true;
            Show();
            Activate();
        }

        void CreaSafata()
        {
            var menu = new ContextMenuStrip();
            menu.Items.Add(Textos.T("Obri Teixit", "Abrir Teixit"), null, delegate { ObriFinestra(); });
            menu.Items.Add(Textos.T("Carpeta de dades", "Carpeta de datos"), null, delegate { ObriCarpeta(); });
            menu.Items.Add(new ToolStripSeparator());
            menu.Items.Add(Textos.T("Atura i tanca", "Detener y cerrar"), null, delegate { Close(); });
            safata = new NotifyIcon
            {
                Text = "Teixit",
                Icon = this.Icon ?? SystemIcons.Application,
                ContextMenuStrip = menu,
                Visible = true
            };
            safata.DoubleClick += delegate { ObriFinestra(); };
        }

        public void ObriCarpeta()
        {
            try
            {
                Directory.CreateDirectory(Dades);
                Process.Start("explorer.exe", "\"" + Dades + "\"");
            }
            catch { }
        }

        void ObriFinestra()
        {
            if (visor != null && !visor.IsDisposed) { PortaVisor(); return; }
            if (!config.Finestra || AppNavegador.Obri(Url) == null) ObriNavegador(Url);
        }

        // Els tipus de WebView2 només es carreguen ací: si falten les biblioteques, el llançador continua igual
        [MethodImpl(MethodImplOptions.NoInlining)]
        static bool VisorDisponible() { return Visor.Disponible(); }

        [MethodImpl(MethodImplOptions.NoInlining)]
        Form CreaVisor() { return new Visor(this, config.Port, Url); }

        [MethodImpl(MethodImplOptions.NoInlining)]
        void PortaVisor() { ((Visor)visor).Porta(); }

        /// <summary>Obri la finestra de Teixit; quan l'usuari la tanca, Teixit fa la còpia i s'atura</summary>
        void ObriFinestraPropia()
        {
            bool ok = false;
            try { ok = VisorDisponible(); } catch (Exception ex) { Log("Visor: " + ex.Message); }
            if (ok)
            {
                try
                {
                    visor = CreaVisor();
                    visor.Show();
                    return;
                }
                catch (Exception ex) { Log("Visor: " + ex.Message); visor = null; }
            }
            ObriAppNavegador();
        }

        /// <summary>El visor no s'ha pogut iniciar: es prova Edge o Chrome en mode aplicació</summary>
        public void VisorHaFallat(string motiu)
        {
            Log("Visor: " + motiu);
            if (visor != null) { try { visor.Dispose(); } catch { } visor = null; }
            ObriAppNavegador();
        }

        /// <summary>L'usuari ha tancat la finestra de Teixit: còpia de seguretat i fi</summary>
        public void TancaDesDelVisor()
        {
            tancaSensePreguntar = true;
            Close();
        }

        void ObriAppNavegador()
        {
            app = AppNavegador.Obri(Url);
            if (app != null) CreaSafata();
            if (app == null)
            {
                // Sense Edge ni Chrome: navegador habitual i finestra menuda, com abans
                Mostra();
                if (config.Navegador) ObriNavegador(Url);
                return;
            }
            appObertA = DateTime.Now;
            app.EnableRaisingEvents = true;
            app.Exited += delegate
            {
                if (tancant) return;
                try
                {
                    BeginInvoke((MethodInvoker)delegate
                    {
                        if (tancant) return;
                        // Si s'acaba de seguida, la finestra l'ha agafada un altre procés del mateix perfil i no
                        // sabrem quan es tanca: es mostra la finestra menuda perquè l'usuari puga aturar Teixit.
                        if ((DateTime.Now - appObertA).TotalSeconds < 5) { Mostra(); return; }
                        tancaSensePreguntar = true;
                        Close();
                    });
                }
                catch { }
            };
        }

        void Arranca()
        {
            string phpExe = Path.Combine(config.Arrel, "php", "php.exe");
            string app = Path.Combine(config.Arrel, "app");
            if (!File.Exists(phpExe) || !File.Exists(Path.Combine(app, "index.php")))
            {
                Error(Textos.T("Falten fitxers del programa (php o app). Torna a descomprimir o instal·lar Teixit.",
                               "Faltan archivos del programa (php o app). Vuelve a descomprimir o instalar Teixit."));
                return;
            }
            if (!PortLliure(config.Port))
            {
                Error(string.Format(Textos.T("El port {0} ja està en ús per un altre programa. Canvia'l a teixit.ini.",
                                             "El puerto {0} ya está en uso por otro programa. Cámbialo en teixit.ini."), config.Port));
                return;
            }
            Directory.CreateDirectory(Dades);
            try
            {
                registre = new StreamWriter(Path.Combine(Dades, "servidor.log"), RotaRegistre(), Encoding.UTF8) { AutoFlush = true };
            }
            catch { registre = null; }

            // Només s'escolta en este ordinador
            string host = "127.0.0.1";
            string phpDir = Path.Combine(config.Arrel, "php");
            var args = new StringBuilder();
            args.Append("-c \"").Append(Path.Combine(phpDir, "php.ini")).Append("\" ");
            args.Append("-d extension_dir=\"").Append(Path.Combine(phpDir, "ext")).Append("\" ");
            args.Append("-d error_log=\"").Append(Path.Combine(Dades, "php-errors.log")).Append("\" ");
            args.Append("-S ").Append(host).Append(':').Append(config.Port).Append(' ');
            args.Append("-t \"").Append(app).Append("\" \"").Append(Path.Combine(app, "index.php")).Append('"');

            var psi = new ProcessStartInfo(phpExe, args.ToString())
            {
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardError = true,
                RedirectStandardOutput = true,
                WorkingDirectory = config.Arrel
            };
            psi.EnvironmentVariables["TEIXIT_DADES"] = Dades;
            psi.EnvironmentVariables["TEIXIT_TOKEN"] = token;
            php = new Process { StartInfo = psi, EnableRaisingEvents = true };
            php.ErrorDataReceived += Registra;
            php.OutputDataReceived += Registra;
            php.Exited += delegate
            {
                if (tancant) return;
                BeginInvoke((MethodInvoker)delegate
                {
                    Error(Textos.T("El servidor s'ha aturat. Mira data\\servidor.log i torna a obrir Teixit.",
                                   "El servidor se ha detenido. Mira data\\servidor.log y vuelve a abrir Teixit."));
                });
            };
            try
            {
                php.Start();
                php.BeginErrorReadLine();
                php.BeginOutputReadLine();
            }
            catch (Exception ex)
            {
                Error(ex.Message);
                return;
            }

            var esperant = new System.Windows.Forms.Timer { Interval = 250 };
            int intents = 0;
            esperant.Tick += delegate
            {
                intents++;
                if (php.HasExited) { esperant.Stop(); return; }
                if (Respon())
                {
                    esperant.Stop();
                    estat.Text = "✔ " + Textos.T("Teixit està en marxa en este ordinador:", "Teixit está en marcha en este ordenador:");
                    estat.ForeColor = Color.FromArgb(76, 106, 42);
                    obri.Enabled = true;
                    if (config.Finestra) ObriFinestraPropia();
                    else if (config.Navegador) ObriNavegador(Url);
                }
                else if (intents > 60)
                {
                    esperant.Stop();
                    Error(Textos.T("El servidor no respon. Mira data\\servidor.log.", "El servidor no responde. Mira data\\servidor.log."));
                }
            };
            esperant.Start();
        }

        bool RotaRegistre()
        {
            // Si el registre passa de 2 MB, comencem de nou
            string f = Path.Combine(Dades, "servidor.log");
            return !(File.Exists(f) && new FileInfo(f).Length > 2 * 1024 * 1024);
        }

        void Registra(object sender, DataReceivedEventArgs e)
        {
            if (e.Data == null || registre == null) return;
            lock (registre)
            {
                try { registre.WriteLine(e.Data); } catch { }
            }
        }

        bool Respon()
        {
            try
            {
                using (var c = new TcpClient())
                {
                    var r = c.BeginConnect(IPAddress.Loopback, config.Port, null, null);
                    bool ok = r.AsyncWaitHandle.WaitOne(200);
                    if (ok) c.EndConnect(r);
                    return ok && c.Connected;
                }
            }
            catch { return false; }
        }

        static bool PortLliure(int port)
        {
            foreach (var ep in IPGlobalProperties.GetIPGlobalProperties().GetActiveTcpListeners())
                if (ep.Port == port) return false;
            return true;
        }

        void Error(string text)
        {
            estat.Text = "✖ " + Textos.T("No s'ha pogut arrancar", "No se ha podido arrancar");
            estat.ForeColor = Color.FromArgb(179, 38, 30);
            obri.Enabled = false;
            Mostra();
            MessageBox.Show(this, text, "Teixit", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }

        void Tancant(object sender, FormClosingEventArgs e)
        {
            if (php != null && !php.HasExited && e.CloseReason == CloseReason.UserClosing && !tancaSensePreguntar)
            {
                var r = MessageBox.Show(this,
                    Textos.T("Vols tancar Teixit? Si hi ha hagut canvis, abans es farà una còpia de seguretat.",
                             "¿Quieres cerrar Teixit? Si ha habido cambios, antes se hará una copia de seguridad."),
                    "Teixit", MessageBoxButtons.YesNo, MessageBoxIcon.Question);
                if (r != DialogResult.Yes) { e.Cancel = true; return; }
            }
            tancant = true;
            // Si es tanca des de la finestra menuda o la safata, també es tanca la finestra de Teixit
            try { if (app != null && !app.HasExited) app.CloseMainWindow(); } catch { }
            try { if (avisObri != null) avisObri.Set(); } catch { }
            if (safata != null) safata.Text = Textos.T("Teixit: guardant la còpia…", "Teixit: guardando la copia…");
            if (php != null && !php.HasExited)
            {
                // Còpia de seguretat en tancar (només si hi ha hagut canvis)
                estat.Text = Textos.T("Guardant la còpia de seguretat…", "Guardando la copia de seguridad…");
                estat.ForeColor = Color.FromArgb(93, 103, 120);
                Cursor = Cursors.WaitCursor;
                Refresh();
                try
                {
                    var peticio = (HttpWebRequest)WebRequest.Create("http://127.0.0.1:" + config.Port + "/?r=copia_tancament&token=" + token);
                    peticio.Proxy = null;
                    peticio.Timeout = 60000;
                    using (var resposta = (HttpWebResponse)peticio.GetResponse())
                    using (var lector = new StreamReader(resposta.GetResponseStream()))
                    {
                        string r = lector.ReadToEnd();
                        if (r == "ok-externa-error")
                            MessageBox.Show(this, Textos.T("S'ha fet la còpia, però no s'ha pogut guardar a la carpeta externa. Comprova que el disc està connectat.",
                                                           "Se ha hecho la copia, pero no se ha podido guardar en la carpeta externa. Comprueba que el disco está conectado."),
                                            "Teixit", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    }
                }
                catch (Exception ex)
                {
                    MessageBox.Show(this, Textos.T("No s'ha pogut fer la còpia en tancar: ", "No se ha podido hacer la copia al cerrar: ") + ex.Message,
                                    "Teixit", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                }
            }
            try { if (php != null && !php.HasExited) { php.Kill(); php.WaitForExit(3000); } } catch { }
            if (registre != null) { try { registre.Dispose(); } catch { } }
            if (safata != null) { safata.Visible = false; safata.Dispose(); }
        }

        public static void ObriNavegador(string url)
        {
            try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); } catch { }
        }
    }
}
