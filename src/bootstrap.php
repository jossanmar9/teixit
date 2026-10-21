<?php
// Teixit: Gestió lliure per a associacions
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

const TEIXIT_VERSIO = '1.0.0';
const SRC_DIR = __DIR__;

define('ARREL_DIR', dirname(__DIR__));
define('DADES_DIR', rtrim(getenv('TEIXIT_DADES') ?: ARREL_DIR . DIRECTORY_SEPARATOR . 'data', '/\\'));
define('DOCS_DIR', ARREL_DIR . DIRECTORY_SEPARATOR . 'docs');

date_default_timezone_set(getenv('TEIXIT_ZONA') ?: 'Europe/Madrid');
mb_internal_encoding('UTF-8');

require SRC_DIR . '/i18n.php';
require SRC_DIR . '/tipus.php';
require SRC_DIR . '/capacitats.php';
require SRC_DIR . '/markdown.php';
require SRC_DIR . '/xifrat.php';
require SRC_DIR . '/sepa.php';

/* ---------------- Base de dades ---------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    if (!is_dir(DADES_DIR)) {
        mkdir(DADES_DIR, 0770, true);
    }
    $fitxer = DADES_DIR . DIRECTORY_SEPARATOR . 'teixit.db';
    // Restauració pendent: es fa ací, abans d'obrir la base de dades.
    // Abans de deixar-la preparada ja s'ha fet una còpia «abans-restaurar» de les dades actuals.
    $restaura = DADES_DIR . DIRECTORY_SEPARATOR . 'restaura.db';
    if (is_file($restaura)) {
        foreach (['', '-wal', '-shm'] as $s) {
            if (is_file($fitxer . $s)) {
                unlink($fitxer . $s);
            }
        }
        rename($restaura, $fitxer);
    }
    $pdo = new PDO('sqlite:' . $fitxer, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec(file_get_contents(SRC_DIR . '/schema.sql'));
    migra_esquema($pdo);
    sembra_rols($pdo);
    // Després de restaurar es manté la configuració de còpies que hi havia
    $conservada = DADES_DIR . DIRECTORY_SEPARATOR . 'restaura-config.json';
    if (is_file($conservada)) {
        foreach ((array)json_decode((string)file_get_contents($conservada), true) as $clau => $valor) {
            $pdo->prepare('INSERT INTO config(clau, valor) VALUES(?, ?) ON CONFLICT(clau) DO UPDATE SET valor = excluded.valor')->execute([$clau, $valor]);
        }
        unlink($conservada);
    }
    return $pdo;
}

/**
 * Afig a les bases de dades d'una versió anterior les columnes noves.
 * (CREATE TABLE IF NOT EXISTS no modifica les taules que ja existeixen.)
 */
function migra_esquema(PDO $pdo): void
{
    // Columnes afegides en cada versió: 'taula' => ['columna' => 'tipus SQL'].
    // Encara no n'hi ha cap: la 1.0.0 és la primera versió de Teixit.
    $columnes = [];
    foreach ($columnes as $taula => $cols) {
        $existents = array_column($pdo->query("PRAGMA table_info($taula)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($cols as $col => $tipus) {
            if (!in_array($col, $existents, true)) {
                $pdo->exec("ALTER TABLE $taula ADD COLUMN $col $tipus");
            }
        }
    }
}

/** Crea els tipus de soci inicials segons el tipus d'entitat (una sola vegada; després cada entitat els canvia) */
function sembra_tipus_socis(PDO $pdo, ?string $idioma = null): void
{
    if ($pdo->query("SELECT valor FROM config WHERE clau = 'tipus_socis_inicials'")->fetchColumn()) {
        return;
    }
    $idioma ??= (string)($pdo->query("SELECT valor FROM config WHERE clau = 'idioma'")->fetchColumn() ?: 'ca');
    if ((int)$pdo->query('SELECT COUNT(*) FROM tipus_socis')->fetchColumn() === 0) {
        foreach (TIPUS_SOCI_TIPUS[tipus_entitat()] as $i => [$ca, $es, $fullAlta]) {
            $pdo->prepare('INSERT INTO tipus_socis(nom, ordre, full_alta) VALUES(?, ?, ?)')->execute([$idioma === 'es' ? $es : $ca, ($i + 1) * 10, $fullAlta]);
        }
    }
    $pdo->exec("INSERT INTO config(clau, valor) VALUES('tipus_socis_inicials', '1') ON CONFLICT(clau) DO UPDATE SET valor = excluded.valor");
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fila(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function valor(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function config(string $clau, ?string $defecte = null): ?string
{
    $v = valor('SELECT valor FROM config WHERE clau = ?', [$clau]);
    return $v === null ? $defecte : (string)$v;
}

function desa_config(string $clau, ?string $valor): void
{
    q('INSERT INTO config(clau, valor) VALUES(?, ?) ON CONFLICT(clau) DO UPDATE SET valor = excluded.valor', [$clau, $valor]);
}

/* ---------------- Sessió i seguretat ---------------- */

function inicia_sessio(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = DADES_DIR . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    session_save_path($dir);
    session_name('teixit');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function csrf_camp(): string
{
    return '<input type="hidden" name="_csrf" value="' . h($_SESSION['csrf']) . '">';
}

function comprova_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
            http_response_code(400);
            exit(t('err.csrf'));
        }
    }
}

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta, array $params = []): string
{
    $params = array_merge(['r' => $ruta], $params);
    return '?' . http_build_query($params);
}

function redirigeix(string $ruta, array $params = []): never
{
    header('Location: ' . url($ruta, $params));
    exit;
}

function avis(string $text, string $tipus = 'ok'): void
{
    $_SESSION['avisos'][] = [$tipus, $text];
}

function post(string $clau, $defecte = '')
{
    $v = $_POST[$clau] ?? $defecte;
    return is_string($v) ? trim($v) : $v;
}

function get(string $clau, $defecte = '')
{
    $v = $_GET[$clau] ?? $defecte;
    return is_string($v) ? trim($v) : $v;
}

/* ---------------- Usuari actual ---------------- */

function usuari(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $u = null;
    if (!empty($_SESSION['usuari_id'])) {
        $u = fila('SELECT * FROM usuaris WHERE id = ? AND actiu = 1', [$_SESSION['usuari_id']]);
        if ($u) {
            $u['capacitats'] = array_column(q(
                'SELECT DISTINCT rc.capacitat FROM usuari_rols ur JOIN rol_capacitats rc ON rc.rol_id = ur.rol_id WHERE ur.usuari_id = ?',
                [$u['id']]
            )->fetchAll(), 'capacitat');
        }
    }
    return $u;
}

function pot(string $capacitat): bool
{
    $u = usuari();
    return $u !== null && in_array($capacitat, $u['capacitats'], true);
}

function requereix(string $capacitat): void
{
    if (!pot($capacitat)) {
        http_response_code(403);
        pagina(t('err.permis'), '<div class="buit">' . h(t('err.permis_text')) . '</div>');
        exit;
    }
}

/* ---------------- Auditoria ---------------- */

function audita(string $accio, ?string $entitat = null, $id = null, ?string $detall = null): void
{
    $u = usuari();
    q('INSERT INTO auditoria(usuari_id, usuari, accio, entitat, element_id, detall) VALUES(?,?,?,?,?,?)', [
        $u['id'] ?? null, $u['usuari'] ?? null, $accio, $entitat, $id, $detall,
    ]);
}

/* ---------------- Utilitats ---------------- */

function data_llegible(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date(strlen($d) > 10 ? 'd/m/Y H:i' : 'd/m/Y', $t) : $d;
}

function nom_persona(?array $p): string
{
    if (!$p) {
        return '';
    }
    return trim(($p['cognoms'] ? $p['cognoms'] . ', ' : '') . $p['nom']);
}

function curs_actual(): ?array
{
    return fila('SELECT * FROM cursos WHERE actual = 1 LIMIT 1')
        ?? fila("SELECT * FROM cursos WHERE estat = 'actiu' ORDER BY data_inici DESC LIMIT 1");
}

require SRC_DIR . '/disseny.php';
