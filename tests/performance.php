<?php
// Prova de càrrega i d'índexs de Teixit.
// No toca data/teixit.db: treballa exclusivament amb una BD temporal.

declare(strict_types=1);

$schema = dirname(__DIR__) . '/src/schema.sql';
if (!is_file($schema)) {
    fwrite(STDERR, "No s'ha trobat src/schema.sql\n");
    exit(1);
}

$tmp = tempnam(sys_get_temp_dir(), 'teixit-perf-');
if ($tmp === false) {
    fwrite(STDERR, "No s'ha pogut crear la BD temporal\n");
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $tmp, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = OFF');
    $db->exec(file_get_contents($schema));

    $esperats = [
        'idx_tutories_tutor',
        'idx_membres_persona_estat',
        'idx_periodes_curs_ordre',
        'idx_grups_assignatura',
        'idx_grups_professor',
        'idx_grups_aula',
        'idx_horaris_grup_dia',
        'idx_matricules_curs_estat',
        'idx_qualificacions_alumne',
        'idx_qualificacions_periode',
        'idx_qualificacions_professor',
        'idx_manteniments_instrument_data',
        'idx_cessions_persona',
        'idx_tarifes_tipus_estat_periodicitat',
        'idx_tarifes_assignatura',
        'idx_mandats_persona_estat_data',
        'idx_rebuts_remesa',
        'idx_rebuts_mandat',
        'idx_rebuts_data',
        'idx_linies_alumne',
        'idx_talonaris_persona',
        'idx_documents_persona',
        'idx_auditoria_usuari',
    ];

    $indexos = array_column(
        $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(),
        'name'
    );
    $falten = array_values(array_diff($esperats, $indexos));
    if ($falten) {
        throw new RuntimeException('Falten índexs: ' . implode(', ', $falten));
    }
    echo "✓ Índexs addicionals presents: " . count($esperats) . PHP_EOL;

    // Dades mínimes necessàries per a generar volum realista en economia.
    $db->beginTransaction();
    $persona = $db->prepare(
        'INSERT INTO persones(id, nom, cognoms, actiu) VALUES(?, ?, ?, 1)'
    );
    for ($i = 1; $i <= 2000; $i++) {
        $persona->execute([$i, 'Nom ' . $i, 'Cognoms ' . $i]);
    }

    $rebut = $db->prepare(
        'INSERT INTO rebuts(id, numero, persona_id, concepte, tipus, periode, data_emissio, import, forma_pagament, estat, clau)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $linia = $db->prepare(
        'INSERT INTO rebut_linies(rebut_id, concepte, import, ordre) VALUES(?, ?, ?, ?)'
    );

    $nRebuts = 100000;
    for ($i = 1; $i <= $nRebuts; $i++) {
        $personaId = (($i - 1) % 2000) + 1;
        $any = 2020 + (($i - 1) % 7);
        $mes = (($i - 1) % 12) + 1;
        $data = sprintf('%04d-%02d-15', $any, $mes);
        $estat = ($i % 5 === 0) ? 'pendent' : 'pagat';
        $forma = ($i % 3 === 0) ? 'domiciliacio' : 'efectiu';
        $rebut->execute([
            $i,
            sprintf('%04d/%06d', $any, $i),
            $personaId,
            'Quota de prova',
            'quota_soci',
            sprintf('%04d-%02d', $any, $mes),
            $data,
            25.00,
            $forma,
            $estat,
            'perf:' . $i,
        ]);
        for ($j = 1; $j <= 3; $j++) {
            $linia->execute([$i, 'Línia ' . $j, 25 / 3, $j]);
        }
    }
    $db->commit();

    $nLinies = (int)$db->query('SELECT COUNT(*) FROM rebut_linies')->fetchColumn();
    echo "✓ BD temporal creada: {$nRebuts} rebuts i {$nLinies} línies" . PHP_EOL;

    $proves = [
        [
            'nom' => 'Rebuts d’una persona',
            'sql' => 'SELECT * FROM rebuts WHERE persona_id = ? ORDER BY data_emissio DESC LIMIT 50',
            'params' => [123],
            'index' => 'idx_rebuts_persona',
        ],
        [
            'nom' => 'Línies d’un rebut',
            'sql' => 'SELECT SUM(import) FROM rebut_linies WHERE rebut_id = ?',
            'params' => [50000],
            'index' => 'idx_linies_rebut',
        ],
        [
            'nom' => 'Rebuts per data',
            'sql' => 'SELECT * FROM rebuts WHERE data_emissio BETWEEN ? AND ? ORDER BY data_emissio LIMIT 50',
            'params' => ['2025-01-01', '2025-12-31'],
            'index' => 'idx_rebuts_data',
        ],
        [
            'nom' => 'Rebuts pendents domiciliats',
            'sql' => "SELECT * FROM rebuts WHERE estat = ? AND forma_pagament = ? LIMIT 50",
            'params' => ['pendent', 'domiciliacio'],
            'index' => 'idx_rebuts_estat',
        ],
    ];

    foreach ($proves as $p) {
        $st = $db->prepare('EXPLAIN QUERY PLAN ' . $p['sql']);
        $st->execute($p['params']);
        $pla = implode(' | ', array_column($st->fetchAll(), 'detail'));
        if (stripos($pla, $p['index']) === false) {
            throw new RuntimeException(
                "{$p['nom']}: SQLite no usa {$p['index']}. Pla: {$pla}"
            );
        }

        $t0 = hrtime(true);
        for ($i = 0; $i < 100; $i++) {
            $st = $db->prepare($p['sql']);
            $st->execute($p['params']);
            $st->fetchAll();
        }
        $ms = (hrtime(true) - $t0) / 1e6;
        printf("✓ %-30s %8.2f ms / 100 consultes · %s\n",
            $p['nom'], $ms, $p['index']);
    }

    echo "✓ Prova de rendiment completada correctament" . PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "✗ " . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    unset($db);
    @unlink($tmp);
    @unlink($tmp . '-wal');
    @unlink($tmp . '-shm');
}
