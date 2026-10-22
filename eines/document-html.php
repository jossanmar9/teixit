<?php
// Converteix un manual o guia (Markdown) en una pàgina HTML preparada per a imprimir en PDF.
// Ús: php eines/document-html.php docs/manual-usuari.md eixida.html
// Després: navegador sense interfície (Chrome/Edge) --print-to-pdf
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

if ($argc < 3) {
    fwrite(STDERR, "Ús: php eines/document-html.php entrada.md eixida.html\n");
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

$md = file_get_contents($argv[1]);
if ($md === false) {
    fwrite(STDERR, "No es pot llegir $argv[1]\n");
    exit(1);
}
$titol = preg_match('/^# (.+)$/m', $md, $m) ? trim($m[1]) : 'Teixit';
$docsDir = dirname(realpath($argv[1]));
$cos = markdown_simple(preg_replace('/^# .*\R/', '', $md, 1), function (string $ruta) use ($docsDir) {
    $f = $docsDir . '/' . $ruta;
    if (!preg_match('#^imatges/(ca|es)/[\w-]+\.(jpg|png|webp)$#', $ruta) || !is_file($f)) {
        fwrite(STDERR, "Falta la imatge $ruta\n");
        return null;
    }
    // Les captures són WebP; Chrome les guardaria al PDF sense comprimir (i el manual passaria de 12 a 40 MB),
    // així que, si PHP té GD, es converteixen a JPEG per al PDF
    if (str_ends_with($f, '.webp') && function_exists('imagecreatefromwebp') && ($im = @imagecreatefromwebp($f))) {
        ob_start();
        imagejpeg($im, null, 78);
        return 'data:image/jpeg;base64,' . base64_encode((string)ob_get_clean());
    }
    if (str_ends_with($f, '.webp') && !isset($GLOBALS['avis_gd'])) {
        $GLOBALS['avis_gd'] = fwrite(STDERR, "Avís: PHP no té GD amb WebP; el PDF serà més gran\n");
    }
    return 'data:image/' . (str_ends_with($f, '.png') ? 'png' : (str_ends_with($f, '.webp') ? 'webp' : 'jpeg')) . ';base64,' . base64_encode(file_get_contents($f));
});
$castella = str_contains(basename($argv[1]), 'usuario') || str_contains(basename($argv[1]), 'instalacion') || str_contains(basename($argv[1]), 'administracion');
$lema = $castella ? 'Gestión libre para asociaciones' : 'Gestió lliure per a associacions';
$versio = ($castella ? 'Versión ' : 'Versió ') . (getenv('TEIXIT_VERSIO') ?: TEIXIT_VERSIO);
$logo = 'data:image/png;base64,' . base64_encode(file_get_contents(ARREL_DIR . '/app/assets/logo.png'));

// En imprimir no hi ha desplaçament: les imatges no poden tindre càrrega diferida
$cos = str_replace(' loading="lazy"', '', $cos);
$castellaLang = $castella ? 'es' : 'ca-valencia';
$titolH = h($titol);
$lemaH = h($lema);
$versioH = h($versio);

$html = <<<HTML
<!doctype html>
<html lang="{$castellaLang}">
<head>
<meta charset="utf-8">
<title>{$titolH}</title>
<style>
@page {
  size: A4; margin: 18mm 17mm 20mm;
  @bottom-center { content: "Teixit · " counter(page) " / " counter(pages); font-family: "Segoe UI", Arial, sans-serif; font-size: 8pt; color: #8a93a3; }
}
@page :first { @bottom-center { content: none; } }
* { box-sizing: border-box; }
body { margin: 0; color: #2a2420; font-family: "Segoe UI", system-ui, Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; }
.portada { height: 255mm; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; page-break-after: always; }
.portada img { width: 85mm; }
.portada h1 { font-size: 24pt; color: #3a2a20; margin: 14mm 0 3mm; }
.portada p { color: #5d6778; margin: 0; }
.portada .versio { margin-top: 2mm; font-size: 9.5pt; }
.portada .autor { margin-top: 24mm; font-size: 10.5pt; color: #3b4558; }
h2 { color: #1f5e67; font-size: 15pt; margin: 9mm 0 3mm; padding-bottom: 1.5mm; border-bottom: 1.5px solid #e39c27; page-break-after: avoid; }
h3 { color: #3a2a20; font-size: 12pt; margin: 6mm 0 2mm; page-break-after: avoid; }
h4 { font-size: 11pt; margin: 5mm 0 2mm; }
p, li { orphans: 3; widows: 3; }
ul, ol { padding-left: 6mm; }
li { margin: 1mm 0; }
code { background: #e3eff0; padding: 0 1.2mm; border-radius: 1mm; font-family: Consolas, "Courier New", monospace; font-size: 9.5pt; }
pre { background: #f4f6fa; border: 1px solid #dde3ec; border-radius: 2mm; padding: 3mm 4mm; font-family: Consolas, "Courier New", monospace; font-size: 9pt; line-height: 1.35; white-space: pre-wrap; page-break-inside: avoid; }
blockquote { margin: 4mm 0; padding: 2.5mm 4mm; background: #fdf3e1; border-left: 3px solid #e39c27; border-radius: 1mm; page-break-inside: avoid; }
table { width: 100%; border-collapse: collapse; margin: 3mm 0; page-break-inside: avoid; }
th, td { border: 1px solid #c9d2df; padding: 1.8mm 2.5mm; text-align: left; vertical-align: top; }
table:has(td.permis) th:not(:first-child) { text-align: center; }
td.permis { text-align: center; width: 28mm; font-weight: 600; color: #4c6a2a; }
td.permis.no { color: #888; font-weight: 400; }
th { background: #e3eff0; font-size: 9.5pt; }
a { color: #1f5e67; text-decoration: none; }
.taula-wrap { overflow: visible; }
figure.captura { margin: 3mm 0 5mm; page-break-inside: avoid; text-align: center; }
figure.captura img { max-width: 100%; max-height: 120mm; border: 1px solid #c9d2df; border-radius: 1.5mm; box-shadow: 0 1mm 3mm rgba(58,42,32,.12); }
figure.captura figcaption { font-size: 8.5pt; color: #5d6778; margin-top: 1.5mm; font-style: italic; }
</style>
</head>
<body>
<section class="portada">
  <img src="{$logo}" alt="Teixit">
  <h1>{$titolH}</h1>
  <p>{$lemaH}</p>
  <p class="versio">{$versioH}</p>
  <p class="autor">José Vicente Sanjuan</p>
</section>
<main>{$cos}</main>
</body>
</html>
HTML;

file_put_contents($argv[2], $html);
