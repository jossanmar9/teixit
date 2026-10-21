<?php
// Lectura de fulls de càlcul per a la importació: CSV i Excel (.xlsx)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// L'.xlsx és un zip amb XML. Com que el PHP portable de Teixit no porta l'extensió zip,
// es llig el directori central del zip a mà i es descomprimeix amb zlib (gzinflate).

const MAX_FILES_IMPORTACIO = 5000;

/** Llig un fitxer i retorna les files (arrays de cadenes). Llança RuntimeException amb un missatge per a l'usuari */
function llig_full(string $fitxer, string $nomOriginal): array
{
    $ext = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
    $inici = (string)file_get_contents($fitxer, false, null, 0, 8);
    if ($ext === 'xlsx' || str_starts_with($inici, "PK\x03\x04")) {
        $files = llig_xlsx($fitxer);
    } elseif (in_array($ext, ['xls', 'ods'], true) || str_starts_with($inici, "\xD0\xCF\x11\xE0")) {
        throw new RuntimeException(t('imp.err_format_antic'));
    } else {
        $files = llig_csv($fitxer);
    }
    // Fora files buides
    $files = array_values(array_filter($files, fn($f) => implode('', array_map('trim', $f)) !== ''));
    if (count($files) > MAX_FILES_IMPORTACIO + 1) {
        throw new RuntimeException(t('imp.err_massa', MAX_FILES_IMPORTACIO));
    }
    return $files;
}

function llig_csv(string $fitxer): array
{
    $text = (string)file_get_contents($fitxer);
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    } elseif (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
        $text = mb_convert_encoding(substr($text, 2), 'UTF-8', str_starts_with($text, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // Excel en Windows guarda així el CSV
    }
    $primera = strtok($text, "\r\n") ?: '';
    $sep = ';';
    $max = -1;
    foreach ([';', ',', "\t", '|'] as $s) {
        if (substr_count($primera, $s) > $max) {
            $max = substr_count($primera, $s);
            $sep = $s;
        }
    }
    $f = fopen('php://temp', 'w+');
    fwrite($f, $text);
    rewind($f);
    $files = [];
    while (($fila = fgetcsv($f, 0, $sep, '"', '')) !== false) {
        if ($fila !== [null]) {
            $files[] = array_map(fn($v) => trim((string)$v), $fila);
        }
        if (count($files) > MAX_FILES_IMPORTACIO + 1) {
            break;
        }
    }
    fclose($f);
    return $files;
}

/** Entrades d'un zip: nom => contingut (només les que es demanen) */
function llig_zip(string $fitxer, array $noms): array
{
    $d = (string)file_get_contents($fitxer);
    $fi = strrpos($d, "PK\x05\x06");
    if ($fi === false) {
        throw new RuntimeException(t('imp.err_llegir'));
    }
    $eocd = unpack('vdisc/vdisk/ventrades/vtotal/Vmida/Vinici', substr($d, $fi + 4, 16));
    $p = $eocd['inici'];
    $resultat = [];
    for ($i = 0; $i < $eocd['total']; $i++) {
        if (substr($d, $p, 4) !== "PK\x01\x02") {
            break;
        }
        $c = unpack('vfet/vcal/vflags/vmetode/vhora/vdata/Vcrc/Vcomprimit/Vmida/vnom/vextra/vcoment/vdisc/vinterns/Vexterns/Vlocal', substr($d, $p + 4, 42));
        $nom = substr($d, $p + 46, $c['nom']);
        $p += 46 + $c['nom'] + $c['extra'] + $c['coment'];
        if (!in_array($nom, $noms, true)) {
            continue;
        }
        $l = unpack('vnom/vextra', substr($d, $c['local'] + 26, 4));
        $dades = substr($d, $c['local'] + 30 + $l['nom'] + $l['extra'], $c['comprimit']);
        if ($c['metode'] === 8) {
            $dades = @gzinflate($dades);
        } elseif ($c['metode'] !== 0) {
            $dades = false;
        }
        if ($dades === false) {
            throw new RuntimeException(t('imp.err_llegir'));
        }
        $resultat[$nom] = $dades;
    }
    return $resultat;
}

function xml_segur(string $xml): ?SimpleXMLElement
{
    if (preg_match('/<!DOCTYPE/i', $xml)) {
        return null; // sense DTD: evita entitats externes
    }
    $x = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    return $x ?: null;
}

/** Primer full d'un .xlsx com a files de cadenes */
function llig_xlsx(string $fitxer): array
{
    $base = llig_zip($fitxer, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml']);
    $full = 'xl/worksheets/sheet1.xml';
    // El primer full del llibre (pot no ser sheet1.xml)
    $wb = isset($base['xl/workbook.xml']) ? xml_segur($base['xl/workbook.xml']) : null;
    $rels = isset($base['xl/_rels/workbook.xml.rels']) ? xml_segur($base['xl/_rels/workbook.xml.rels']) : null;
    if ($wb && $rels && isset($wb->sheets->sheet[0])) {
        $rid = (string)$wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($rels->Relationship as $r) {
            if ((string)$r['Id'] === $rid) {
                $t = ltrim((string)$r['Target'], '/');
                $full = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
            }
        }
    }
    $compartides = [];
    if (isset($base['xl/sharedStrings.xml']) && ($ss = xml_segur($base['xl/sharedStrings.xml']))) {
        foreach ($ss->si as $si) {
            $compartides[] = isset($si->t) ? (string)$si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
        }
    }
    $xml = llig_zip($fitxer, [$full])[$full] ?? null;
    $sh = $xml ? xml_segur($xml) : null;
    if (!$sh) {
        throw new RuntimeException(t('imp.err_llegir'));
    }
    $files = [];
    foreach ($sh->sheetData->row as $row) {
        $fila = [];
        foreach ($row->c as $c) {
            $col = columna_index(preg_replace('/\d+/', '', (string)$c['r']));
            $tipus = (string)$c['t'];
            if ($tipus === 's') {
                $v = $compartides[(int)$c->v] ?? '';
            } elseif ($tipus === 'inlineStr') {
                $v = (string)($c->is->t ?? '');
            } elseif ($tipus === 'b') {
                $v = (string)$c->v === '1' ? '1' : '0';
            } else {
                $v = (string)$c->v;
                // Els números enters arriben com «600123456» o «6.00123456E8»
                if (is_numeric($v) && stripos($v, 'e') !== false && (float)$v == floor((float)$v)) {
                    $v = number_format((float)$v, 0, '', '');
                }
            }
            $fila[$col] = trim($v);
        }
        if ($fila) {
            $max = max(array_keys($fila));
            $files[] = array_map(fn($i) => $fila[$i] ?? '', range(0, $max));
        }
        if (count($files) > MAX_FILES_IMPORTACIO + 1) {
            break;
        }
    }
    return $files;
}

/** «A» → 0, «AB» → 27 */
function columna_index(string $lletres): int
{
    $n = 0;
    foreach (str_split(strtoupper($lletres)) as $l) {
        $n = $n * 26 + (ord($l) - 64);
    }
    return max(0, $n - 1);
}

/** Interpreta una data d'un full de càlcul: 31/12/2010, 31-12-10, 2010-12-31, 31.12.2010 o número de sèrie d'Excel */
function data_importada(string $v): ?string
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (float)$v > 1000 && (float)$v < 80000) {
        return date('Y-m-d', (int)round(((float)$v - 25569) * 86400)); // sèrie d'Excel (dies des de 1899-12-30)
    }
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $v, $m)) {
        [$a, $mes, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $v, $m)) {
        [$d, $mes, $a] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($a < 100) {
            $a += $a > (int)date('y') ? 1900 : 2000;
        }
    } else {
        return null;
    }
    return checkdate($mes, $d, $a) ? sprintf('%04d-%02d-%02d', $a, $mes, $d) : null;
}

/** Text en minúscules i sense accents, per a comparar noms i capçaleres */
function normalitza_text(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '·' => '', 'ŀ' => 'l', 'º' => '', 'ª' => '']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

function normalitza_document(?string $d): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$d));
}
