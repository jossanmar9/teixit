; Instal·lador de Teixit (Inno Setup 6)
; Es compila des de GitHub Actions:  iscc /DVersio=1.0.0 /DOrigen=..\dist\Teixit launcher\teixit.iss

#ifndef Versio
  #define Versio "0.0.0"
#endif
#ifndef VersioNum
  #define VersioNum "0.0.0"
#endif
#ifndef Origen
  #define Origen "..\dist\Teixit"
#endif

[Setup]
AppId={{DA867E35-919C-4A8A-A2A2-416BD0A079BA}
AppName=Teixit
AppVersion={#Versio}
AppVerName=Teixit {#Versio}
AppPublisher=José Vicente Sanjuan
AppComments=Gestió lliure per a associacions
; Nom i versió a les propietats de l'instal·lador (Windows: botó dret → Propietats → Detalls)
VersionInfoVersion={#VersioNum}
VersionInfoProductName=Teixit
VersionInfoProductVersion={#VersioNum}
VersionInfoProductTextVersion={#Versio}
VersionInfoDescription=Instal·lador de Teixit: gestió lliure per a associacions
VersionInfoCompany=Teixit
VersionInfoCopyright=© 2026 José Vicente Sanjuan · AGPL-3.0-or-later
DefaultDirName={localappdata}\Programs\Teixit
DefaultGroupName=Teixit
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\dist
OutputBaseFilename=Teixit-{#Versio}-installador
SetupIconFile=teixit.ico
UninstallDisplayIcon={app}\Teixit.exe
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible

[Languages]
Name: "ca"; MessagesFile: "compiler:Languages\Catalan.isl"; LicenseFile: "llicencia-ca.txt"; InfoBeforeFile: "privacitat-ca.txt"
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"; LicenseFile: "llicencia-es.txt"; InfoBeforeFile: "privacitat-es.txt"

[Tasks]
Name: "escriptori"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
Source: "{#Origen}\*"; DestDir: "{app}"; Excludes: "data\*,teixit.ini"; Flags: recursesubdirs createallsubdirs ignoreversion
; teixit.ini no se sobreescriu si ja existeix (conserva la configuració)
Source: "{#Origen}\teixit.ini"; DestDir: "{app}"; Flags: onlyifdoesntexist uninsneveruninstall

[Dirs]
; Les dades no s'esborren mai en desinstal·lar
Name: "{app}\data"; Flags: uninsneveruninstall

[Icons]
Name: "{group}\Teixit"; Filename: "{app}\Teixit.exe"
Name: "{group}\Manual de Teixit"; Filename: "{app}\docs\pdf\manual-usuari.pdf"; Languages: ca
Name: "{group}\Manual de Teixit"; Filename: "{app}\docs\pdf\manual-usuario.pdf"; Languages: es
Name: "{group}\Manual d'administració de Teixit"; Filename: "{app}\docs\pdf\manual-administracio.pdf"; Languages: ca
Name: "{group}\Manual de administración de Teixit"; Filename: "{app}\docs\pdf\manual-administracion.pdf"; Languages: es
Name: "{autodesktop}\Teixit"; Filename: "{app}\Teixit.exe"; Tasks: escriptori

[UninstallDelete]
; Configuració de la finestra de Teixit (WebView2, o Edge o Chrome en mode aplicació). Les dades de Teixit no es toquen.
Type: filesandordirs; Name: "{localappdata}\Teixit\visor"
Type: filesandordirs; Name: "{localappdata}\Teixit\finestra"
Type: files; Name: "{localappdata}\Teixit\finestra.txt"
Type: dirifempty; Name: "{localappdata}\Teixit"

[Run]
Filename: "{app}\Teixit.exe"; Description: "{cm:LaunchProgram,Teixit}"; Flags: nowait postinstall skipifsilent
