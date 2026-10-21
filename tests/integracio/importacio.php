<?php
// Importació de socis i alumnat amb IBAN des d'un full (sense servidor: crida les funcions directament)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require SRC_DIR . '/entitats.php'; require SRC_DIR . '/crud.php';
foreach (glob(SRC_DIR . '/pages/*.php') as $f) require $f;
session_id('prova'); @session_start(); $_SESSION['usuari_id'] = 1;
$ok = function ($c, $m) { echo ($c ? 'ok: ' : 'FALLA: ') . $m . "\n"; };
// Assignació automàtica de columnes
$ok(endevina_camp('IBAN', 'socis') === 'iban', 'IBAN → iban');
$ok(endevina_camp('Número de cuenta', 'socis') === 'iban', 'Número de cuenta → iban (no número de soci)');
$ok(endevina_camp('Nº socio', 'socis') === 'numero', 'Nº socio continua sent el número');
$ok(endevina_camp('Fecha firma', 'socis') === 'data_mandat', 'Fecha firma → data_mandat');
// Socis
$files = [['Nom', 'Cognoms', 'Naixement', 'DNI', 'Telèfon', 'IBAN', 'Signatura'],
    ['Soci', 'Banc1', '1970-01-01', '11111111H', '600', 'ES91 2100 0418 4502 0005 1332', '2020-05-01'],
    ['Soci', 'Banc2', '1971-01-01', '22222222J', '600', '21000418450200051332', ''],
    ['Soci', 'Jove', '2015-01-01', '', '', 'ES9121000418450200051332', ''],
    ['Soci', 'Dolent', '1972-01-01', '33333333P', '600', 'ES12 3456', ''],
    ['Membre1', 'Exemple', '', '', '', 'DE89370400440532013000', '']];
$imp = ['tipus' => 'socis', 'nom_fitxer' => 'prova.csv', 'capcalera' => true, 'files' => $files,
    'assignacio' => ['nom', 'cognoms', 'data_naixement', 'document', 'telefon', 'iban', 'data_mandat']];
$a = analitza_importacio($imp);
foreach ($a as $r) echo '   ', $r['n'], ' ', $r['estat'], ' ', implode(' | ', $r['avisos']), "\n";
$mateixa = []; foreach ($a as $i => $r) $mateixa[$i] = $r['persona_id'] ? 1 : 0;
$fet = executa_importacio($imp, $a, $mateixa);
print_r($fet);
$m = fn($nom) => fila("SELECT m.* FROM mandats m JOIN persones p ON p.id = m.persona_id WHERE p.cognoms = ? AND m.estat = 'actiu'", [$nom]);
$ok(($m('Banc1')['data_signatura'] ?? '') === '2020-05-01', 'soci amb IBAN i data de signatura');
$ok(($m('Banc2')['iban'] ?? '') === 'ES9121000418450200051332', 'soci amb CCC convertit');
$ok($m('Jove') === null, 'menor sense domiciliació');
$ok($m('Dolent') === null, 'IBAN incorrecte no s importa');
$ok($fet['domiciliacions_diferents'] === 1, 'Membre1 ja tenia domiciliació diferent: no es canvia');
// Alumnes amb el tutor
$files = [['Alumne', 'Naix', 'Tutor', 'DNI tutor', 'IBAN'], ['Nen Prova', '2016-03-03', 'Mare Prova', '44444444A', 'ES9121000418450200051332']];
$imp = ['tipus' => 'alumnes', 'nom_fitxer' => 'a.csv', 'capcalera' => true, 'files' => $files, 'assignacio' => ['nom_complet', 'data_naixement', 'tutor_nom_complet', 'tutor_document', 'iban']];
$a = analitza_importacio($imp); echo '   ', implode(' | ', $a[0]['avisos']), "\n";
$fet = executa_importacio($imp, $a, [0]);
$ok(($m('Prova')['persona_id'] ?? 0) == valor("SELECT id FROM persones WHERE nom = 'Mare'"), 'alumne: domiciliació per al tutor/a');
