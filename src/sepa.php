<?php
// Adeutes directes SEPA: IBAN, identificador de creditor i fitxer XML de la remesa (pain.008)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

const FORMATS_SEPA = ['pain.008.001.02', 'pain.008.001.08'];

/** «es12 3456…» → «ES123456…» */
function iban_net(?string $iban): string
{
    return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$iban));
}

/** Resta mòdul 97 d'un text numèric llarg (sense bcmath) */
function mod97(string $numeric): int
{
    $r = 0;
    foreach (str_split($numeric, 7) as $tros) {
        $r = (int)(($r . $tros)) % 97;
    }
    return $r;
}

/** Converteix lletres en números (A=10 … Z=35), com demanen l'IBAN i l'identificador de creditor */
function lletres_a_numeros(string $s): string
{
    return preg_replace_callback('/[A-Z]/', fn($m) => (string)(ord($m[0]) - 55), $s);
}

function iban_valid(?string $iban): bool
{
    $i = iban_net($iban);
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $i)) {
        return false;
    }
    if (str_starts_with($i, 'ES') && strlen($i) !== 24) {
        return false;
    }
    return mod97(lletres_a_numeros(substr($i, 4) . substr($i, 0, 4))) === 1;
}

/** Accepta també el compte espanyol antic (CCC de 20 xifres) i el converteix en IBAN */
function iban_entrada(?string $s): string
{
    $i = iban_net($s);
    if (preg_match('/^\d{20}$/', $i)) {
        return 'ES' . sprintf('%02d', 98 - mod97($i . '142800')) . $i;
    }
    return $i;
}

/** «ES12 3456 7890 …» */
function iban_format(?string $iban): string
{
    return trim(chunk_split(iban_net($iban), 4, ' '));
}

/** «ES12 •••• •••• •••• •••• 1234»: per als llistats */
function iban_amagat(?string $iban): string
{
    $i = iban_net($iban);
    return strlen($i) < 8 ? $i : substr($i, 0, 4) . ' •••• ' . substr($i, -4);
}

function bic_valid(?string $bic): bool
{
    return (bool)preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper(trim((string)$bic)));
}

/**
 * Identificador de creditor SEPA (AT-02): país + dígits de control + sufix + NIF.
 * Els dígits de control es calculen amb el NIF i «ES00», sense el sufix.
 */
function identificador_creditor(string $nif, string $sufix = '000', string $pais = 'ES'): string
{
    $nif = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $nif));
    $sufix = strtoupper(substr(preg_replace('/[^0-9A-Za-z]/', '', $sufix) ?: '000', 0, 3));
    $control = 98 - mod97(lletres_a_numeros($nif . $pais . '00'));
    return $pais . sprintf('%02d', $control) . str_pad($sufix, 3, '0', STR_PAD_LEFT) . $nif;
}

/** Dades del creditor (la societat) per a les remeses; null si en falta alguna */
function creditor_sepa(): ?array
{
    $nif = config('sepa_nif') ?: config('societat_cif');
    $c = [
        'nom' => config('sepa_nom') ?: config('societat_nom'),
        'nif' => $nif,
        'iban' => iban_net(config('sepa_iban')),
        'bic' => strtoupper(trim((string)config('sepa_bic'))),
        'id' => $nif ? identificador_creditor($nif, config('sepa_sufix') ?: '000') : '',
        'format' => in_array(config('sepa_format'), FORMATS_SEPA, true) ? config('sepa_format') : FORMATS_SEPA[0],
    ];
    return $c['nom'] && $nif && iban_valid($c['iban']) ? $c : null;
}

/** Text amb només els caràcters permesos en SEPA (sense accents, ñ → n, ç → c) */
function text_sepa(?string $s, int $max): string
{
    $s = strtr((string)$s, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ñ' => 'n', 'Ñ' => 'N', 'ç' => 'c', 'Ç' => 'C', '·' => '.', 'ª' => 'a', 'º' => 'o',
        '’' => "'", '‘' => "'", '“' => '', '”' => '', '«' => '', '»' => '', '–' => '-', '—' => '-', '€' => 'EUR', '&' => '+',
    ]);
    $s = preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return mb_substr($s, 0, $max);
}

/** Identificador d'una transacció o missatge: sense espais i amb els caràcters bàsics */
function id_sepa(string $s, int $max = 35): string
{
    return substr(preg_replace('/[^A-Za-z0-9\-\/]/', '', $s), 0, $max);
}

/**
 * XML de la remesa (adeutes directes SEPA, esquema CORE) per a pujar a la banca electrònica.
 * $cobraments: [['rebut' => fila de rebuts, 'mandat' => fila de mandats, 'deutor' => nom]]
 */
function xml_remesa(array $remesa, array $cobraments, array $creditor): string
{
    $format = $creditor['format'];
    $v08 = $format === 'pain.008.001.08';
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $arrel = $doc->createElementNS("urn:iso:std:iso:20022:tech:xsd:$format", 'Document');
    $doc->appendChild($arrel);
    $inici = $doc->createElement('CstmrDrctDbtInitn');
    $arrel->appendChild($inici);

    $el = function (DOMElement $pare, string $nom, ?string $valor = null, array $atributs = []) use ($doc): DOMElement {
        $e = $valor === null ? $doc->createElement($nom) : $doc->createElement($nom);
        if ($valor !== null) {
            $e->appendChild($doc->createTextNode($valor));
        }
        foreach ($atributs as $k => $v) {
            $e->setAttribute($k, $v);
        }
        $pare->appendChild($e);
        return $e;
    };
    $agent = function (DOMElement $pare, string $nom, string $bic) use ($el, $v08): void {
        $fin = $el($el($pare, $nom), 'FinInstnId');
        if ($bic !== '' && bic_valid($bic)) {
            $el($fin, $v08 ? 'BICFI' : 'BIC', strtoupper($bic));
        } else {
            $el($el($fin, 'Othr'), 'Id', 'NOTPROVIDED');
        }
    };
    $import = fn(float $x) => number_format($x, 2, '.', '');

    $total = array_sum(array_map(fn($c) => round((float)$c['rebut']['import'], 2), $cobraments));
    $gh = $el($inici, 'GrpHdr');
    $el($gh, 'MsgId', id_sepa($remesa['referencia']));
    $el($gh, 'CreDtTm', date('Y-m-d\TH:i:s'));
    $el($gh, 'NbOfTxs', (string)count($cobraments));
    $el($gh, 'CtrlSum', $import($total));
    $ip = $el($gh, 'InitgPty');
    $el($ip, 'Nm', text_sepa($creditor['nom'], 70));
    $el($el($el($el($ip, 'Id'), 'OrgId'), 'Othr'), 'Id', $creditor['id']);

    // Un bloc per tipus de seqüència: primer cobrament amb el mandat (FRST) o recurrent (RCUR)
    $blocs = [];
    foreach ($cobraments as $c) {
        $blocs[empty($c['mandat']['ultim_us']) ? 'FRST' : 'RCUR'][] = $c;
    }
    foreach ($blocs as $seq => $llista) {
        $pi = $el($inici, 'PmtInf');
        $el($pi, 'PmtInfId', id_sepa($remesa['referencia'] . '-' . $seq));
        $el($pi, 'PmtMtd', 'DD');
        $el($pi, 'BtchBookg', 'true');
        $el($pi, 'NbOfTxs', (string)count($llista));
        $el($pi, 'CtrlSum', $import(array_sum(array_map(fn($c) => round((float)$c['rebut']['import'], 2), $llista))));
        $tp = $el($pi, 'PmtTpInf');
        $el($el($tp, 'SvcLvl'), 'Cd', 'SEPA');
        $el($el($tp, 'LclInstrm'), 'Cd', 'CORE');
        $el($tp, 'SeqTp', $seq);
        $el($pi, 'ReqdColltnDt', $remesa['data_cobrament']);
        $el($el($pi, 'Cdtr'), 'Nm', text_sepa($creditor['nom'], 70));
        $el($el($el($pi, 'CdtrAcct'), 'Id'), 'IBAN', $creditor['iban']);
        $agent($pi, 'CdtrAgt', $creditor['bic']);
        $el($pi, 'ChrgBr', 'SLEV');
        $o = $el($el($el($el($pi, 'CdtrSchmeId'), 'Id'), 'PrvtId'), 'Othr');
        $el($o, 'Id', $creditor['id']);
        $el($el($o, 'SchmeNm'), 'Prtry', 'SEPA');

        foreach ($llista as $c) {
            $r = $c['rebut'];
            $m = $c['mandat'];
            $tx = $el($pi, 'DrctDbtTxInf');
            $el($el($tx, 'PmtId'), 'EndToEndId', id_sepa('R' . $r['id'] . '-' . str_replace('/', '-', (string)$r['numero'])));
            $el($tx, 'InstdAmt', $import((float)$r['import']), ['Ccy' => 'EUR']);
            $mr = $el($el($tx, 'DrctDbtTx'), 'MndtRltdInf');
            $el($mr, 'MndtId', id_sepa((string)$m['referencia']));
            $el($mr, 'DtOfSgntr', $m['data_signatura']);
            $agent($tx, 'DbtrAgt', (string)$m['bic']);
            $el($el($tx, 'Dbtr'), 'Nm', text_sepa($m['titular'] ?: $c['deutor'], 70));
            $el($el($el($tx, 'DbtrAcct'), 'Id'), 'IBAN', iban_net($m['iban']));
            $el($el($tx, 'RmtInf'), 'Ustrd', text_sepa($r['numero'] . ' ' . $r['concepte'], 140));
        }
    }
    return $doc->saveXML();
}
