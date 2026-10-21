<?php
// Importació de persones, socis i alumnes des d'un full de càlcul (Excel o CSV)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Passos: 1) pujar el fitxer  2) dir què és cada columna  3) revisar (duplicats i errors)  4) importar.
// Entre passos, les files es guarden en un fitxer temporal de la carpeta de dades (no en la sessió).

require_once SRC_DIR . '/fulls.php';

const TIPUS_IMPORTACIO = ['persones' => 'persones.edit', 'socis' => 'socis.edit', 'alumnes' => 'escola.edit'];
/** Mòdul del qual depén cada tipus d'importació */
const MODUL_IMPORTACIO = ['alumnes' => 'escola'];

/** Tipus d'importació que l'usuari pot fer (amb el permís i el mòdul actiu) */
function tipus_importacio_disponibles(): array
{
    return array_filter(TIPUS_IMPORTACIO, fn($cap, $tp) => pot($cap) && (!isset(MODUL_IMPORTACIO[$tp]) || modul_actiu(MODUL_IMPORTACIO[$tp])), ARRAY_FILTER_USE_BOTH);
}

/** Camps que es poden importar segons el tipus */
function camps_importacio(string $tipus): array
{
    $persona = ['nom', 'cognoms', 'cognom2', 'nom_complet', 'data_naixement', 'document', 'telefon', 'email', 'adreca', 'codi_postal', 'poblacio', 'provincia', 'observacions', 'iban', 'data_mandat'];
    return match ($tipus) {
        'socis' => array_merge($persona, ['numero', 'tipus_soci', 'data_alta']),
        'alumnes' => array_merge($persona, ['data_alta', 'tutor_nom', 'tutor_cognoms', 'tutor_nom_complet', 'tutor_document', 'tutor_telefon', 'tutor_email', 'tutor_relacio']),
        default => $persona,
    };
}

/** Endevina el camp d'una capçalera del full */
function endevina_camp(string $capcalera, string $tipus): ?string
{
    $c = normalitza_text($capcalera);
    if ($c === '') {
        return null;
    }
    $tutor = (bool)preg_match('/\b(tutor|tutora|pare|mare|padre|madre|progenitor|familia|responsable)\b/', $c);
    $sinonims = [
        'nom_complet' => ['nom i cognoms', 'nom complet', 'nombre y apellidos', 'nombre completo', 'apellidos y nombre', 'cognoms i nom', 'cognoms nom', 'apellidos nombre'],
        'cognom2' => ['segon cognom', 'cognom 2', 'cognom2', '2n cognom', 'segundo apellido', 'apellido 2', 'apellido2', '2 apellido'],
        'cognoms' => ['cognoms', 'cognom', 'apellidos', 'apellido', 'primer cognom', '1r cognom', 'primer apellido', 'apellido 1', 'apellido1', 'surname', 'last name'],
        'data_naixement' => ['naixement', 'nacimiento', 'data naix', 'f nac', 'fecha nac', 'data de naixement', 'fecha de nacimiento', 'birth'],
        'document' => ['dni', 'nif', 'nie', 'document', 'documento', 'dni nie', 'dni nif'],
        'telefon' => ['telefon', 'telefono', 'tel', 'tlf', 'tfn', 'mobil', 'movil', 'telefon mobil', 'phone'],
        'email' => ['email', 'e mail', 'correu', 'correo', 'correu electronic', 'correo electronico', 'mail'],
        'adreca' => ['adreca', 'direccion', 'domicili', 'domicilio', 'carrer', 'calle', 'address'],
        'codi_postal' => ['cp', 'c p', 'codi postal', 'codigo postal', 'cod postal'],
        'poblacio' => ['poblacio', 'poblacion', 'localitat', 'localidad', 'municipi', 'municipio', 'ciutat', 'ciudad', 'poble', 'pueblo'],
        'provincia' => ['provincia'],
        'observacions' => ['observacions', 'observaciones', 'notes', 'notas', 'comentaris', 'comentarios'],
        'iban' => ['iban', 'compte', 'cuenta', 'compte bancari', 'cuenta bancaria', 'ccc', 'numero de compte', 'numero de cuenta', 'num compte', 'num cuenta',
            'n compte', 'n cuenta', 'domiciliacio', 'domiciliacion', 'compte corrent', 'cuenta corriente', 'iban del compte', 'iban de la cuenta'],
        'data_mandat' => ['data mandat', 'fecha mandato', 'data signatura', 'fecha firma', 'data domiciliacio', 'fecha domiciliacion', 'data de signatura', 'fecha de firma', 'data del mandat', 'fecha del mandato'],
        'numero' => ['numero', 'num', 'n', 'n soci', 'num soci', 'numero soci', 'numero de soci', 'n socio', 'num socio', 'numero socio', 'numero de socio', 'nº'],
        'tipus_soci' => ['tipus', 'tipo', 'tipus soci', 'tipus de soci', 'tipo socio', 'tipo de socio', 'categoria', 'modalitat', 'modalidad'],
        'data_alta' => ['alta', 'data alta', 'fecha alta', 'data d alta', 'fecha de alta', 'antiguitat', 'antiguedad', 'data inscripcio', 'fecha inscripcion'],
        'tutor_relacio' => ['relacio', 'relacion', 'parentiu', 'parentesco'],
        'nom' => ['nom', 'nombre', 'name', 'first name'],
    ];
    if ($tutor) {
        $sense = trim(preg_replace('/\b(del|de la|de|dels|tutor|tutora|pare|mare|padre|madre|progenitor|familia|responsable|legal|o)\b/', ' ', $c));
        $sense = trim(preg_replace('/\s+/', ' ', $sense));
        if ($sense === '') {
            return 'tutor_nom_complet';
        }
        foreach (['nom_complet', 'cognoms', 'document', 'telefon', 'email', 'tutor_relacio', 'nom'] as $camp) {
            foreach ($sinonims[$camp] as $s) {
                if ($sense === $s || str_contains(" $sense ", " $s ")) {
                    $r = str_starts_with($camp, 'tutor_') ? $camp : 'tutor_' . $camp;
                    return in_array($r, camps_importacio($tipus), true) ? $r : null;
                }
            }
        }
        return null;
    }
    foreach ($sinonims as $camp => $llista) {
        if (in_array($c, $llista, true)) {
            return in_array($camp, camps_importacio($tipus), true) ? $camp : null;
        }
    }
    foreach ($sinonims as $camp => $llista) {
        foreach ($llista as $s) {
            if (strlen($s) > 3 && str_contains(" $c ", " $s ")) {
                return in_array($camp, camps_importacio($tipus), true) ? $camp : null;
            }
        }
    }
    return null;
}

/* ---------- Fitxer temporal entre passos ---------- */

function carpeta_importacions(): string
{
    $d = DADES_DIR . DIRECTORY_SEPARATOR . 'importacions';
    if (!is_dir($d)) {
        mkdir($d, 0770, true);
    }
    foreach (glob($d . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) {
            @unlink($f);
        }
    }
    return $d;
}

function fitxer_importacio(): ?string
{
    $tok = $_SESSION['importacio'] ?? '';
    return preg_match('/^[a-f0-9]{24}$/', $tok) ? carpeta_importacions() . DIRECTORY_SEPARATOR . $tok . '.json' : null;
}

function llig_importacio(): ?array
{
    $f = fitxer_importacio();
    return $f && is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
}

function desa_importacio(array $d): void
{
    if (empty($_SESSION['importacio'])) {
        $_SESSION['importacio'] = bin2hex(random_bytes(12));
    }
    file_put_contents(fitxer_importacio(), json_encode($d, JSON_UNESCAPED_UNICODE));
}

function esborra_importacio(): void
{
    if (($f = fitxer_importacio()) && is_file($f)) {
        unlink($f);
    }
    unset($_SESSION['importacio']);
}

/* ---------- Anàlisi ---------- */

/**
 * Tipus de soci d'una cel·la del full: [id, trobat]. Es busca pel nom dels tipus de la societat
 * (igual, o que el continga) i, per als quatre tipus inicials, per paraules semblants. Si no es troba, el primer tipus actiu.
 */
function tipus_soci_importat(string $v): array
{
    $tipus = q("SELECT id, nom, codi FROM tipus_socis WHERE estat = 'actiu' ORDER BY COALESCE(ordre, 999999), nom")->fetchAll();
    $defecte = isset($tipus[0]) ? (int)$tipus[0]['id'] : null;
    $n = normalitza_text($v);
    if ($n === '') {
        return [$defecte, true];
    }
    foreach ($tipus as $t) {
        if (normalitza_text($t['nom']) === $n) {
            return [(int)$t['id'], true];
        }
    }
    foreach ($tipus as $t) {
        $nt = normalitza_text($t['nom']);
        if ($nt !== '' && (str_contains($n, $nt) || str_contains($nt, $n))) {
            return [(int)$t['id'], true];
        }
    }
    $codi = match (true) {
        str_contains($n, 'protect') => 'protector',
        str_contains($n, 'honor') => 'honorari',
        str_contains($n, 'juven') || str_contains($n, 'jove') || str_contains($n, 'infantil') => 'juvenil',
        str_contains($n, 'ordin') || str_contains($n, 'numer') => 'ordinari',
        default => null,
    };
    foreach ($tipus as $t) {
        if ($codi !== null && $t['codi'] === $codi) {
            return [(int)$t['id'], true];
        }
    }
    return [$defecte, false];
}

function relacio_importada(string $v): ?string
{
    $n = normalitza_text($v);
    return match (true) {
        $n === '' => null,
        str_contains($n, 'mare') || str_contains($n, 'madre') => 'mare',
        str_contains($n, 'pare') || str_contains($n, 'padre') => 'pare',
        str_contains($n, 'tutor') => 'tutor_legal',
        default => 'altre',
    };
}

/** Separa «Cognoms, Nom» o «Nom Cognom1 Cognom2». Retorna [nom, cognoms, dubtós] */
function separa_nom(string $complet): array
{
    $complet = trim(preg_replace('/\s+/', ' ', $complet));
    if (str_contains($complet, ',')) {
        [$c, $n] = array_map('trim', explode(',', $complet, 2));
        return [$n, $c, false];
    }
    $parts = explode(' ', $complet);
    if (count($parts) === 1) {
        return [$complet, '', false];
    }
    return [array_shift($parts), implode(' ', $parts), count($parts) > 2];
}

/** Valor d'un camp a partir de la fila i l'assignació de columnes */
function valor_columna(array $fila, array $assignacio, string $camp): string
{
    $col = array_search($camp, $assignacio, true);
    return $col === false ? '' : trim((string)($fila[$col] ?? ''));
}

/**
 * Analitza totes les files: què es crearà, què ja existeix i què falla.
 * Cada resultat: n (fila del full), persona (camps), extra, tutor, estat, persona_id, avisos, error
 */
function analitza_importacio(array $imp): array
{
    $assignacio = $imp['assignacio'];
    $tipus = $imp['tipus'];
    $files = $imp['files'];
    $primera = $imp['capcalera'] ? 2 : 1;
    if ($imp['capcalera']) {
        array_shift($files);
    }
    // Índexs de les persones que ja hi ha
    $perDoc = $perNomData = $perNom = [];
    foreach (q('SELECT id, nom, cognoms, data_naixement, document FROM persones')->fetchAll() as $p) {
        if ($p['document']) {
            $perDoc[normalitza_document($p['document'])] = (int)$p['id'];
        }
        $k = normalitza_text($p['nom'] . ' ' . $p['cognoms']);
        if ($p['data_naixement']) {
            $perNomData[$k . '|' . $p['data_naixement']] = (int)$p['id'];
        }
        $perNom[$k][] = (int)$p['id'];
    }
    $socis = array_flip(array_column(q('SELECT persona_id FROM socis')->fetchAll(), 'persona_id'));
    $alumnes = array_flip(array_column(q('SELECT persona_id FROM alumnes')->fetchAll(), 'persona_id'));
    $numeros = array_flip(array_filter(array_column(q('SELECT numero FROM socis')->fetchAll(), 'numero')));
    $vistos = [];
    $resultat = [];

    foreach ($files as $i => $fila) {
        $v = fn(string $c) => valor_columna($fila, $assignacio, $c);
        $r = ['n' => $i + $primera, 'estat' => 'nova', 'persona_id' => null, 'avisos' => [], 'error' => null, 'extra' => [], 'tutor' => null];
        $nom = $v('nom');
        $cognoms = trim($v('cognoms') . ' ' . $v('cognom2'));
        // Sense columna de cognoms, la columna del nom porta el nom complet
        $nomComplet = $v('nom_complet') !== '' && $nom === '' ? $v('nom_complet')
            : (!in_array('cognoms', $assignacio, true) && !in_array('cognom2', $assignacio, true) && str_contains($nom, ' ') ? $nom : '');
        if ($nomComplet !== '') {
            [$nom, $cognoms2, $dubtos] = separa_nom($nomComplet);
            $cognoms = $cognoms ?: $cognoms2;
            if ($dubtos) {
                $r['avisos'][] = t('imp.av_nom_partit');
            }
        }
        $naix = data_importada($v('data_naixement'));
        if ($v('data_naixement') !== '' && !$naix) {
            $r['avisos'][] = t('imp.av_data', $v('data_naixement'));
        } elseif ($naix && $naix > date('Y-m-d')) {
            $r['avisos'][] = t('imp.av_data', $v('data_naixement'));
            $naix = null;
        }
        $doc = strtoupper(preg_replace('/\s+/', '', $v('document')));
        $email = $v('email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $r['avisos'][] = t('imp.av_email', $email);
        }
        $r['persona'] = [
            'nom' => $nom, 'cognoms' => $cognoms, 'data_naixement' => $naix, 'document' => $doc,
            'telefon' => $v('telefon'), 'email' => $email, 'adreca' => $v('adreca'), 'codi_postal' => $v('codi_postal'),
            'poblacio' => $v('poblacio'), 'provincia' => $v('provincia'), 'observacions' => $v('observacions'),
        ];
        if ($nom === '') {
            $r['estat'] = 'error';
            $r['error'] = t('imp.err_sense_nom');
            $resultat[] = $r;
            continue;
        }
        if ($cognoms === '' || !$naix || (es_major_edat($naix) && ($doc === '' || $v('telefon') === ''))) {
            $r['avisos'][] = t('imp.av_incompleta');
        }

        // Repetida dins del mateix fitxer?
        $kNom = normalitza_text($nom . ' ' . $cognoms);
        $claus = array_filter([$doc !== '' ? 'd:' . normalitza_document($doc) : null, $naix ? 'n:' . $kNom . '|' . $naix : null, !$naix && $doc === '' ? 'o:' . $kNom : null]);
        foreach ($claus as $k) {
            if (isset($vistos[$k])) {
                $r['estat'] = 'repetida';
                $r['error'] = t('imp.err_repetida', $vistos[$k]);
            }
        }
        if ($r['estat'] === 'repetida') {
            $resultat[] = $r;
            continue;
        }
        foreach ($claus as $k) {
            $vistos[$k] = $r['n'];
        }

        // Ja existeix?
        if ($doc !== '' && isset($perDoc[normalitza_document($doc)])) {
            $r['estat'] = 'existeix';
            $r['persona_id'] = $perDoc[normalitza_document($doc)];
        } elseif ($naix && isset($perNomData[$kNom . '|' . $naix])) {
            $r['estat'] = 'existeix';
            $r['persona_id'] = $perNomData[$kNom . '|' . $naix];
        } elseif (isset($perNom[$kNom]) && count($perNom[$kNom]) === 1) {
            $r['estat'] = 'possible';
            $r['persona_id'] = $perNom[$kNom][0];
        }

        // IBAN: es crearà una domiciliació per a qui paga (a l'escola, el tutor/a; si no, la mateixa persona si és major d'edat)
        $r['iban'] = null;
        if ($v('iban') !== '') {
            $iban = iban_entrada($v('iban'));
            if (!iban_valid($iban)) {
                $r['avisos'][] = t('imp.av_iban', $v('iban'));
            } else {
                $dataMandat = data_importada($v('data_mandat'));
                if ($v('data_mandat') !== '' && !$dataMandat) {
                    $r['avisos'][] = t('imp.av_data', $v('data_mandat'));
                }
                $r['iban'] = ['iban' => $iban, 'data' => $dataMandat];
            }
        }

        if ($tipus === 'socis') {
            $num = $v('numero');
            [$tipusSoci, $trobat] = tipus_soci_importat($v('tipus_soci'));
            $r['extra'] = ['numero' => $num, 'tipus' => $tipusSoci, 'data_alta' => data_importada($v('data_alta'))];
            if (!$trobat) {
                $r['avisos'][] = t('imp.av_tipus', $v('tipus_soci'), nom_tipus_soci($tipusSoci) ?: '—');
            }
            if ($r['persona_id'] && isset($socis[$r['persona_id']])) {
                $r['avisos'][] = t('imp.av_ja_soci');
            } elseif ($num !== '' && isset($numeros[$num])) {
                $r['avisos'][] = t('imp.av_numero', $num);
            }
            if ($num !== '') {
                $numeros[$num] = true;
            }
        } elseif ($tipus === 'alumnes') {
            $r['extra'] = ['data_alta' => data_importada($v('data_alta'))];
            if ($r['persona_id'] && isset($alumnes[$r['persona_id']])) {
                $r['avisos'][] = t('imp.av_ja_alumne');
            }
            $tn = $v('tutor_nom');
            $tc = $v('tutor_cognoms');
            $tutorComplet = $v('tutor_nom_complet') !== '' && $tn === '' ? $v('tutor_nom_complet')
                : (!in_array('tutor_cognoms', $assignacio, true) && str_contains($tn, ' ') ? $tn : '');
            if ($tutorComplet !== '') {
                [$tn, $tc2] = separa_nom($tutorComplet);
                $tc = $tc ?: $tc2;
            }
            if ($tn !== '') {
                $r['tutor'] = ['nom' => $tn, 'cognoms' => $tc, 'document' => strtoupper(preg_replace('/\s+/', '', $v('tutor_document'))),
                    'telefon' => $v('tutor_telefon'), 'email' => $v('tutor_email'), 'relacio' => relacio_importada($v('tutor_relacio'))];
            } elseif ($naix && !es_major_edat($naix)) {
                $r['avisos'][] = t('imp.av_sense_tutor');
            }
        }
        if ($r['iban']) {
            $titularTutor = $tipus === 'alumnes' && $r['tutor'];
            // Si la persona ja hi és, val la data de naixement que té Teixit
            $naixTitular = $naix ?: ($r['persona_id'] ? valor('SELECT data_naixement FROM persones WHERE id = ?', [$r['persona_id']]) : null);
            if (!$titularTutor && !es_major_edat($naixTitular)) {
                $r['avisos'][] = t($naixTitular ? 'imp.av_iban_menor' : 'imp.av_iban_sense_data');
                $r['iban'] = null;
            } else {
                $r['iban']['tutor'] = $titularTutor;
                $r['avisos'][] = t($titularTutor ? 'imp.info_iban_tutor' : 'imp.info_iban', iban_amagat($r['iban']['iban']));
            }
        }
        $resultat[] = $r;
    }
    return $resultat;
}

/* ---------- Importar ---------- */

/** Crea la persona o completa les dades buides d'una que ja existeix. Retorna l'id */
function importa_persona(array $p, ?int $id): int
{
    $camps = ['nom', 'cognoms', 'data_naixement', 'document', 'telefon', 'email', 'adreca', 'codi_postal', 'poblacio', 'provincia', 'observacions'];
    $p = array_map(fn($v) => $v === '' ? null : $v, array_intersect_key($p, array_flip($camps)) + array_fill_keys($camps, null));
    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "$c = COALESCE(NULLIF($c, ''), ?)", $camps));
        q("UPDATE persones SET $sets, modificat = datetime('now','localtime') WHERE id = ?", array_merge(array_values(array_map(fn($c) => $p[$c], $camps)), [$id]));
        return $id;
    }
    q('INSERT INTO persones(' . implode(', ', $camps) . ') VALUES(' . implode(', ', array_fill(0, count($camps), '?')) . ')', array_map(fn($c) => $p[$c], $camps));
    return (int)db()->lastInsertId();
}

function busca_tutor(array $tu): ?int
{
    if ($tu['document'] !== '') {
        foreach (q("SELECT id, document FROM persones WHERE document IS NOT NULL AND document <> ''")->fetchAll() as $p) {
            if (normalitza_document($p['document']) === normalitza_document($tu['document'])) {
                return (int)$p['id'];
            }
        }
    }
    $k = normalitza_text($tu['nom'] . ' ' . $tu['cognoms']);
    $iguals = array_filter(q('SELECT id, nom, cognoms, telefon FROM persones')->fetchAll(), fn($p) => normalitza_text($p['nom'] . ' ' . $p['cognoms']) === $k);
    if (count($iguals) === 1) {
        return (int)reset($iguals)['id'];
    }
    // Homònims: només si coincideix també el telèfon
    foreach ($iguals as $p) {
        if ($tu['telefon'] !== '' && preg_replace('/\D/', '', (string)$p['telefon']) === preg_replace('/\D/', '', $tu['telefon'])) {
            return (int)$p['id'];
        }
    }
    return null;
}

function executa_importacio(array $imp, array $analisi, array $mateixa): array
{
    $fet = ['noves' => 0, 'actualitzades' => 0, 'rols' => 0, 'tutors' => 0, 'saltades' => 0, 'domiciliacions' => 0, 'domiciliacions_diferents' => 0];
    fes_copia('abans-importar'); // per a poder tornar arrere si la importació no era el que s'esperava
    db()->beginTransaction();
    foreach ($analisi as $i => $r) {
        if (in_array($r['estat'], ['error', 'repetida'], true)) {
            $fet['saltades']++;
            continue;
        }
        $existent = $r['persona_id'] && !empty($mateixa[$i]) ? (int)$r['persona_id'] : null;
        $id = importa_persona($r['persona'], $existent);
        $titular = $id;
        $fet[$existent ? 'actualitzades' : 'noves']++;
        $avui = date('Y-m-d');
        if ($imp['tipus'] === 'socis' && !valor('SELECT id FROM socis WHERE persona_id = ?', [$id])) {
            q("INSERT INTO socis(persona_id, numero, tipus, data_alta, estat) VALUES(?, ?, ?, ?, 'actiu')",
                [$id, $r['extra']['numero'] ?: null, $r['extra']['tipus'], $r['extra']['data_alta'] ?: $avui]);
            $fet['rols']++;
        } elseif ($imp['tipus'] === 'alumnes') {
            $alumne = valor('SELECT id FROM alumnes WHERE persona_id = ?', [$id]);
            if (!$alumne) {
                q("INSERT INTO alumnes(persona_id, data_alta, estat) VALUES(?, ?, 'actiu')", [$id, $r['extra']['data_alta'] ?: $avui]);
                $alumne = (int)db()->lastInsertId();
                $fet['rols']++;
            }
            if ($r['tutor']) {
                $tid = busca_tutor($r['tutor']);
                if (!$tid) {
                    $tid = importa_persona(['nom' => $r['tutor']['nom'], 'cognoms' => $r['tutor']['cognoms'], 'document' => $r['tutor']['document'],
                        'telefon' => $r['tutor']['telefon'], 'email' => $r['tutor']['email']], null);
                    $fet['tutors']++;
                } else {
                    importa_persona(['document' => $r['tutor']['document'], 'telefon' => $r['tutor']['telefon'], 'email' => $r['tutor']['email']], $tid);
                }
                $titular = $tid;
                if ($tid !== $id && !valor('SELECT id FROM tutories WHERE alumne_id = ? AND tutor_id = ?', [$alumne, $tid])) {
                    $principal = valor('SELECT COUNT(*) FROM tutories WHERE alumne_id = ?', [$alumne]) ? 0 : 1;
                    q('INSERT INTO tutories(alumne_id, tutor_id, tipus_relacio, principal, rep_comunicacions) VALUES(?, ?, ?, ?, 1)', [$alumne, $tid, $r['tutor']['relacio'], $principal]);
                }
            }
        }
        // Domiciliació: no es canvia la que ja tinga la persona amb un altre IBAN
        $titularMajor = $r['iban'] && ($r['iban']['tutor'] || es_major_edat(valor('SELECT data_naixement FROM persones WHERE id = ?', [$titular])));
        if ($r['iban'] && $titularMajor && pot('economia.edit') && (!$r['iban']['tutor'] || $titular !== $id)) {
            $res = desa_iban_persona($titular, $r['iban']['iban'], $r['iban']['data'], false);
            if (in_array($res, ['nova', 'canviada'], true)) {
                $fet['domiciliacions']++;
            } elseif ($res === 'diferent') {
                $fet['domiciliacions_diferents']++;
            }
        }
    }
    audita('importacio', $imp['tipus'], null, $imp['nom_fitxer'] . ' · ' . json_encode($fet));
    db()->commit();
    return $fet;
}

/* ---------- Pàgines ---------- */

function plantilla_importacio(string $tipus): never
{
    $cols = array_values(array_diff(camps_importacio($tipus), ['cognom2', 'nom_complet', 'tutor_nom_complet']));
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="plantilla-' . $tipus . '.csv"');
    echo "\xEF\xBB\xBF" . implode(';', array_map(fn($c) => t('imp.camp.' . $c), $cols)) . "\r\n";
    exit;
}

/** Exemple d'una cel·la: les dates d'Excel (números de sèrie) es mostren com a dates */
function mostra_valor_full(string $v, string $camp): string
{
    if (str_starts_with($camp, 'data_') && preg_match('/^\d{4,5}(\.\d+)?$/', $v) && ($d = data_importada($v))) {
        return data_llegible($d);
    }
    return mb_strimwidth($v, 0, 40, '…');
}

function etiqueta_estat(string $estat): string
{
    return '<span class="estat-imp ' . h($estat) . '">' . h(t('imp.estat.' . $estat)) . '</span>';
}

function pagina_importa(): void
{
    requereix('persones.edit');
    $pas = get('pas');
    if (get('plantilla') !== '' && isset(tipus_importacio_disponibles()[get('plantilla')])) {
        plantilla_importacio(get('plantilla'));
    }
    $imp = llig_importacio();
    $torna = '<p><a href="' . h(url('importa', ['pas' => 'nova'])) . '">← ' . h(t('imp.comenca')) . '</a></p>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accio = post('accio');
        if ($accio === 'puja') {
            $tipus = post('tipus');
            $up = $_FILES['fitxer'] ?? null;
            if (!isset(tipus_importacio_disponibles()[$tipus])) {
                avis(t('err.permis'), 'error');
                redirigeix('importa');
            }
            if (!$up || $up['error'] !== UPLOAD_ERR_OK || $up['size'] > 20 * 1024 * 1024) {
                avis(t('imp.err_fitxer'), 'error');
                redirigeix('importa');
            }
            try {
                $files = llig_full($up['tmp_name'], $up['name']);
            } catch (RuntimeException $ex) {
                avis($ex->getMessage(), 'error');
                redirigeix('importa');
            }
            if (count($files) < (post('capcalera') === '1' ? 2 : 1)) {
                avis(t('imp.err_buit'), 'error');
                redirigeix('importa');
            }
            $amplada = max(array_map('count', $files));
            $files = array_map(fn($f) => array_pad($f, $amplada, ''), $files);
            $assignacio = [];
            $usats = [];
            for ($c = 0; $c < $amplada; $c++) {
                $camp = post('capcalera') === '1' ? endevina_camp($files[0][$c], $tipus) : null;
                $assignacio[$c] = $camp && !isset($usats[$camp]) ? $camp : '';
                if ($camp) {
                    $usats[$camp] = true;
                }
            }
            esborra_importacio();
            desa_importacio(['tipus' => $tipus, 'nom_fitxer' => mb_substr($up['name'], 0, 120), 'capcalera' => post('capcalera') === '1', 'files' => $files, 'assignacio' => $assignacio]);
            redirigeix('importa', ['pas' => 'columnes']);
        }
        if (!$imp) {
            redirigeix('importa');
        }
        if ($accio === 'columnes') {
            $assignacio = [];
            $vist = [];
            $error = null;
            foreach (array_keys($imp['files'][0]) as $c) {
                $camp = (string)($_POST['col'][$c] ?? '');
                if ($camp !== '' && !in_array($camp, camps_importacio($imp['tipus']), true)) {
                    $camp = '';
                }
                if ($camp !== '' && isset($vist[$camp])) {
                    $error = t('imp.err_doble', t('imp.camp.' . $camp));
                }
                $vist[$camp] = true;
                $assignacio[$c] = $camp;
            }
            if (!isset($vist['nom']) && !isset($vist['nom_complet'])) {
                $error = t('imp.err_cal_nom');
            }
            $imp['assignacio'] = $assignacio;
            $imp['capcalera'] = post('capcalera') === '1';
            desa_importacio($imp);
            if ($error) {
                avis($error, 'error');
                redirigeix('importa', ['pas' => 'columnes']);
            }
            redirigeix('importa', ['pas' => 'revisa']);
        }
        if ($accio === 'importa') {
            requereix(TIPUS_IMPORTACIO[$imp['tipus']]);
            $fet = executa_importacio($imp, analitza_importacio($imp), (array)($_POST['mateixa'] ?? []));
            $_SESSION['imp_resultat'] = $fet + ['tipus' => $imp['tipus']];
            esborra_importacio();
            redirigeix('importa', ['pas' => 'fet']);
        }
        redirigeix('importa');
    }

    if ($pas === 'fet' && isset($_SESSION['imp_resultat'])) {
        $f = $_SESSION['imp_resultat'];
        $html = '<div class="avis ok">' . h(t('imp.fet')) . '</div><div class="targetes">'
            . targeta((string)$f['noves'], t('imp.res_noves')) . targeta((string)$f['actualitzades'], t('imp.res_actualitzades'))
            . ($f['tipus'] !== 'persones' ? targeta((string)$f['rols'], t('imp.res_rols.' . $f['tipus'])) : '')
            . ($f['tipus'] === 'alumnes' ? targeta((string)$f['tutors'], t('imp.res_tutors')) : '')
            . (!empty($f['domiciliacions']) ? targeta((string)$f['domiciliacions'], t('imp.res_domiciliacions'), url('llista', ['e' => 'mandats'])) : '')
            . targeta((string)$f['saltades'], t('imp.res_saltades')) . '</div>'
            . (!empty($f['domiciliacions']) ? '<div class="avis info">' . h(t('imp.nota_domiciliacions')) . '</div>' : '')
            . (!empty($f['domiciliacions_diferents']) ? '<div class="avis atencio">' . h(t('imp.av_iban_diferents', $f['domiciliacions_diferents'])) . '</div>' : '')
            . '<div class="accions"><a class="boto" href="' . h(url('llista', ['e' => $f['tipus']])) . '">' . h(t('ent.' . $f['tipus'])) . '</a>'
            . '<a class="boto secundari" href="' . h(url('llista', ['e' => 'persones', 'filtre' => 'incomplets'])) . '">' . h(t('imp.veure_incompletes')) . '</a>'
            . '<a class="boto secundari" href="' . h(url('importa', ['pas' => 'nova'])) . '">' . h(t('imp.altra')) . '</a></div>';
        pagina(t('menu.importa'), $html);
        return;
    }

    if ($pas === 'nova') {
        esborra_importacio();
        redirigeix('importa');
    }

    // Pas 2: què és cada columna
    if ($pas === 'columnes' && $imp) {
        $camps = ['' => '— ' . t('imp.no_importar') . ' —'];
        foreach (camps_importacio($imp['tipus']) as $c) {
            $camps[$c] = t('imp.camp.' . $c);
        }
        $mostres = array_slice($imp['files'], $imp['capcalera'] ? 1 : 0, 3);
        $html = $torna . '<p>' . h(t('imp.pas2_text', $imp['nom_fitxer'], count($imp['files']) - ($imp['capcalera'] ? 1 : 0))) . '</p>'
            . '<form method="post" class="formulari">' . csrf_camp() . '<input type="hidden" name="accio" value="columnes">'
            . '<label class="check"><input type="checkbox" name="capcalera" value="1"' . ($imp['capcalera'] ? ' checked' : '') . '> ' . h(t('imp.capcalera')) . '</label>'
            . '<div class="taula-wrap"><table class="taula compacta columnes-imp"><thead><tr><th>' . h(t('imp.columna')) . '</th><th>' . h(t('imp.exemples')) . '</th><th>' . h(t('imp.es')) . '</th></tr></thead><tbody>';
        foreach (array_keys($imp['files'][0]) as $c) {
            $titol = $imp['capcalera'] ? $imp['files'][0][$c] : '';
            $html .= '<tr><td><b>' . h(chr(65 + $c % 26) . ($c >= 26 ? (string)intdiv($c, 26) : '')) . '</b> ' . h($titol) . '</td><td class="mostres">'
                . implode('<br>', array_map(fn($f) => h(mostra_valor_full((string)($f[$c] ?? ''), $imp['assignacio'][$c] ?? '')), $mostres)) . '</td><td>'
                . select_simple('col[' . $c . ']', $camps, $imp['assignacio'][$c] ?? '', false, '', false) . '</td></tr>';
        }
        $html .= '</tbody></table></div><p class="nota">' . h(t('imp.pas2_nota')) . '</p><div class="accions"><button>' . h(t('imp.seguent')) . ' →</button></div></form>';
        pagina(t('imp.pas2'), $html, t('menu.importa'));
        return;
    }

    // Pas 3: revisar
    if ($pas === 'revisa' && $imp) {
        $analisi = analitza_importacio($imp);
        $compte = array_count_values(array_column($analisi, 'estat')) + ['nova' => 0, 'existeix' => 0, 'possible' => 0, 'repetida' => 0, 'error' => 0];
        $html = $torna . '<p>' . h(t('imp.pas3_text')) . '</p><div class="targetes">';
        foreach (['nova', 'existeix', 'possible', 'repetida', 'error'] as $e) {
            if ($compte[$e] || in_array($e, ['nova', 'existeix'], true)) {
                $html .= targeta((string)$compte[$e], t('imp.estat.' . $e));
            }
        }
        $html .= '</div><form method="post">' . csrf_camp() . '<input type="hidden" name="accio" value="importa">'
            . '<div class="taula-wrap"><table class="taula compacta revisio-imp"><thead><tr><th>' . h(t('imp.fila')) . '</th><th>' . h(t('imp.estat')) . '</th><th>'
            . h(t('camp.nom')) . '</th><th>' . h(t('camp.cognoms')) . '</th><th>' . h(t('camp.data_naixement')) . '</th><th>' . h(t('camp.document')) . '</th><th>'
            . h($imp['tipus'] === 'socis' ? t('camp.numero') : ($imp['tipus'] === 'alumnes' ? t('llist.tutors') : t('camp.telefon'))) . '</th><th>' . h(t('imp.avisos')) . '</th></tr></thead><tbody>';
        foreach ($analisi as $i => $r) {
            $p = $r['persona'];
            $col7 = $imp['tipus'] === 'socis' ? ($r['extra']['numero'] ?? '') : ($imp['tipus'] === 'alumnes' ? ($r['tutor'] ? trim($r['tutor']['nom'] . ' ' . $r['tutor']['cognoms']) : '') : $p['telefon']);
            $avisos = $r['error'] ? [$r['error']] : $r['avisos'];
            $estat = etiqueta_estat($r['estat']);
            if (in_array($r['estat'], ['existeix', 'possible'], true)) {
                $existent = fila('SELECT * FROM persones WHERE id = ?', [$r['persona_id']]);
                $estat .= '<label class="mateixa"><input type="checkbox" name="mateixa[' . $i . ']" value="1"' . ($r['estat'] === 'existeix' ? ' checked' : '') . '> '
                    . h(t('imp.mateixa')) . '</label><small><a target="_blank" href="' . h(url('fitxa', ['e' => 'persones', 'id' => $r['persona_id']])) . '">'
                    . h(nom_persona($existent)) . ($existent['data_naixement'] ? ' · ' . h(data_llegible($existent['data_naixement'])) : '') . '</a></small>';
            }
            $html .= '<tr class="imp-' . h($r['estat']) . '"><td class="num">' . $r['n'] . '</td><td>' . $estat . '</td><td>' . h($p['nom']) . '</td><td>' . h($p['cognoms']) . '</td><td>'
                . h(data_llegible($p['data_naixement'])) . '</td><td>' . h($p['document']) . '</td><td>' . h($col7) . '</td><td class="avisos-imp">' . implode('<br>', array_map('h', $avisos)) . '</td></tr>';
        }
        $html .= '</tbody></table></div><p class="nota">' . h(t('imp.pas3_nota')) . '</p>'
            . '<div class="accions"><button onclick="return confirm(\'' . h(t('imp.confirma', $compte['nova'] + $compte['existeix'] + $compte['possible'])) . '\')">⬆ ' . h(t('imp.importa')) . '</button>'
            . '<a class="boto secundari" href="' . h(url('importa', ['pas' => 'columnes'])) . '">← ' . h(t('imp.pas2')) . '</a></div></form>';
        pagina(t('imp.pas3'), $html, t('menu.importa'));
        return;
    }

    // Pas 1: pujar el fitxer
    $tipusOk = tipus_importacio_disponibles();
    $html = '<p>' . h(t('imp.intro')) . '</p>'
        . '<form method="post" enctype="multipart/form-data" class="formulari">' . csrf_camp() . '<input type="hidden" name="accio" value="puja"><div class="camps">'
        . '<fieldset class="camp ample"><legend>' . h(t('imp.que')) . '</legend><div class="opcions vertical">';
    foreach (array_keys($tipusOk) as $i => $tp) {
        $html .= '<label><input type="radio" name="tipus" value="' . $tp . '"' . ($i === 0 ? ' checked' : '') . '> <b>' . h(t('ent.' . $tp)) . '</b> · ' . h(t('imp.que_' . $tp)) . '</label>';
    }
    $html .= '</div></fieldset>'
        . '<div class="camp ample"><label>' . h(t('imp.fitxer')) . '</label><input type="file" name="fitxer" required accept=".xlsx,.csv,.txt"><small>' . h(t('imp.fitxer_ajuda')) . '</small></div>'
        . '<div class="camp camp-check"><label><input type="checkbox" name="capcalera" value="1" checked> ' . h(t('imp.capcalera')) . '</label></div>'
        . '</div><div class="accions"><button>' . h(t('imp.seguent')) . ' →</button></div></form>'
        . '<section class="fill"><header><h2>' . h(t('imp.plantilles')) . '</h2></header><p class="nota">' . h(t('imp.plantilles_text')) . '</p><div class="accions">';
    foreach (array_keys($tipusOk) as $tp) {
        $html .= '<a class="boto secundari petit" href="' . h(url('importa', ['plantilla' => $tp])) . '">⬇ ' . h(t('ent.' . $tp)) . '</a>';
    }
    $html .= '</div></section><div class="avis info">' . h(t('imp.consell_copia')) . '</div>';
    pagina(t('menu.importa'), $html);
}
