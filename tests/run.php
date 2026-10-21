<?php
declare(strict_types=1);

/**
 * Proves automatitzades mínimes de Teixit.
 *
 * No usa PHPUnit ni dependències externes: només cal PHP CLI amb les
 * extensions que ja necessita Teixit.
 */

$fallades = 0;
$proves = 0;

function prova(string $nom, callable $fn): void
{
    global $fallades, $proves;
    $proves++;
    try {
        $fn();
        echo "✓ $nom\n";
    } catch (Throwable $e) {
        $fallades++;
        fwrite(STDERR, "✗ $nom\n  {$e->getMessage()}\n");
    }
}

function igual(mixed $esperat, mixed $real, string $missatge = ''): void
{
    if ($esperat !== $real) {
        $prefix = $missatge !== '' ? "$missatge: " : '';
        throw new RuntimeException(
            $prefix . 'esperat ' . var_export($esperat, true) .
            ', obtingut ' . var_export($real, true)
        );
    }
}

function cert(bool $valor, string $missatge = 'la condició no és certa'): void
{
    if (!$valor) {
        throw new RuntimeException($missatge);
    }
}

function fals(bool $valor, string $missatge = 'la condició no és falsa'): void
{
    if ($valor) {
        throw new RuntimeException($missatge);
    }
}

/* ---------- SEPA / IBAN ---------- */

require_once __DIR__ . '/../src/sepa.php';

prova('Normalitza IBAN', function (): void {
    igual('ES9121000418450200051332', iban_net('es91 2100-0418 4502 0005 1332'));
});

prova('Valida un IBAN espanyol correcte', function (): void {
    cert(iban_valid('ES91 2100 0418 4502 0005 1332'));
});

prova('Rebutja un IBAN amb control incorrecte', function (): void {
    fals(iban_valid('ES90 2100 0418 4502 0005 1332'));
});

prova('Rebutja un IBAN espanyol amb longitud incorrecta', function (): void {
    fals(iban_valid('ES91 2100 0418'));
});

prova('Formata IBAN en grups de quatre', function (): void {
    igual('ES91 2100 0418 4502 0005 1332', iban_format('ES9121000418450200051332'));
});

prova('Amaga IBAN en llistats', function (): void {
    igual('ES91 •••• 1332', iban_amagat('ES9121000418450200051332'));
});

prova('Valida BIC', function (): void {
    cert(bic_valid('CAIXESBBXXX'));
    cert(bic_valid('CAIXESBB'));
    fals(bic_valid('NO-ES-UN-BIC'));
});

prova('Converteix CCC espanyol a IBAN vàlid', function (): void {
    $iban = iban_entrada('21000418450200051332');
    igual('ES9121000418450200051332', $iban);
    cert(iban_valid($iban));
});

prova('Neteja identificadors SEPA', function (): void {
    igual('R2026-0001', id_sepa('R2026-0001 !!'));
});

if (function_exists('mb_substr')) {
    prova('Normalitza text al joc de caràcters SEPA', function (): void {
        igual("Associacio Musica n.c + EUR", text_sepa('Associació Música ñ.ç & €', 70));
    });
} else {
    echo "↷ text_sepa: omesa (mbstring no disponible)\n";
}

/* ---------- Xifrat de còpies ---------- */

$configProva = [];

function config(string $clau): mixed
{
    global $configProva;
    return $configProva[$clau] ?? null;
}

function desa_config(string $clau, mixed $valor): void
{
    global $configProva;
    $configProva[$clau] = $valor;
}

require_once __DIR__ . '/../src/xifrat.php';

if (xifrat_disponible()) {
    prova('Xifra i desxifra una còpia sense perdre dades', function (): void {
        $dir = sys_get_temp_dir() . '/teixit-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        $origen = "$dir/origen.db";
        $xifrat = "$dir/copia.xifrat";
        $restaurat = "$dir/restaurat.db";
        $contingut = random_bytes(8192) . "TEIXIT-PROVA" . random_bytes(4096);
        file_put_contents($origen, $contingut);

        configura_xifrat('contrasenya-de-prova');
        cert(xifra_fitxer($origen, $xifrat), 'no s\'ha pogut xifrar');
        cert(es_xifrat($xifrat), 'no detecta el format xifrat');
        cert(desxifra_fitxer($xifrat, $restaurat, 'contrasenya-de-prova'), 'no s\'ha pogut desxifrar');
        igual(hash('sha256', $contingut), hash_file('sha256', $restaurat));

        @unlink($origen);
        @unlink($xifrat);
        @unlink($restaurat);
        @rmdir($dir);
    });

    prova('Rebutja una contrasenya de còpia incorrecta', function (): void {
        $dir = sys_get_temp_dir() . '/teixit-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        $origen = "$dir/origen.db";
        $xifrat = "$dir/copia.xifrat";
        $restaurat = "$dir/restaurat.db";
        file_put_contents($origen, 'dades de prova');

        configura_xifrat('correcta');
        cert(xifra_fitxer($origen, $xifrat));
        fals(desxifra_fitxer($xifrat, $restaurat, 'incorrecta'));
        fals(is_file($restaurat), 'ha deixat un fitxer parcial després de fallar');

        @unlink($origen);
        @unlink($xifrat);
        @rmdir($dir);
    });

    prova('Detecta una còpia xifrada manipulada', function (): void {
        $dir = sys_get_temp_dir() . '/teixit-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        $origen = "$dir/origen.db";
        $xifrat = "$dir/copia.xifrat";
        $restaurat = "$dir/restaurat.db";
        file_put_contents($origen, random_bytes(4096));

        configura_xifrat('correcta');
        cert(xifra_fitxer($origen, $xifrat));

        $dades = file_get_contents($xifrat);
        $pos = max(60, intdiv(strlen($dades), 2));
        $dades[$pos] = chr(ord($dades[$pos]) ^ 1);
        file_put_contents($xifrat, $dades);

        fals(desxifra_fitxer($xifrat, $restaurat, 'correcta'));
        fals(is_file($restaurat), 'ha deixat un fitxer parcial manipulat');

        @unlink($origen);
        @unlink($xifrat);
        @rmdir($dir);
    });
} else {
    echo "↷ xifrat: omés (libsodium no disponible)\n";
}

/* ---------- Rotació de les còpies automàtiques ---------- */

require_once __DIR__ . '/../src/rotacio.php';

/** Una còpia automàtica cada dia durant $dies dies fins a $ara */
function copies_diaries(int $ara, int $dies): array
{
    $c = [];
    for ($i = 0; $i < $dies; $i++) {
        $c[] = 'teixit-' . date('Ymd', $ara - $i * 86400) . '-090000-auto.db';
    }
    return $c;
}

prova('Llig la data del nom de la còpia', function (): void {
    igual(mktime(15, 30, 0, 10, 21, 2026), data_nom_copia('/carpeta/teixit-20261021-153000-auto.db.xifrat'));
    igual(null, data_nom_copia('altra-cosa.db'));
});

prova('Rotació: amb poques còpies no s\'esborra res', function (): void {
    $ara = mktime(12, 0, 0, 10, 21, 2026);
    $c = copies_diaries($ara, 5);
    igual($c, copies_a_mantindre($c, 12, $ara));
});

prova('Rotació: un any de còpies diàries queda en unes vint', function (): void {
    $ara = mktime(12, 0, 0, 10, 21, 2026);
    $c = copies_diaries($ara, 400);
    $m = copies_a_mantindre($c, 12, $ara);
    cert(count($m) >= 18 && count($m) <= 26, 'se\'n conserven ' . count($m));
    // Totes les dels últims 7 dies
    foreach (array_slice($c, 0, 7) as $x) {
        cert(in_array($x, $m, true), "falta $x");
    }
    // Cap de fa més d'un any i un mes
    foreach ($m as $x) {
        cert(data_nom_copia($x) >= strtotime('-13 months', $ara), "massa antiga: $x");
    }
});

prova('Rotació: hi ha una còpia de cada un dels últims dotze mesos', function (): void {
    $ara = mktime(12, 0, 0, 10, 21, 2026);
    $m = copies_a_mantindre(copies_diaries($ara, 400), 12, $ara);
    $mesos = array_unique(array_map(fn($x) => date('Y-m', data_nom_copia($x)), $m));
    cert(count($mesos) >= 12, 'mesos: ' . count($mesos));
});

prova('Rotació: amb 1 mes es guarden menys còpies', function (): void {
    $ara = mktime(12, 0, 0, 10, 21, 2026);
    $c = copies_diaries($ara, 400);
    cert(count(copies_a_mantindre($c, 1, $ara)) < count(copies_a_mantindre($c, 12, $ara)));
});

echo "\n$proves proves executades; $fallades fallades.\n";
exit($fallades === 0 ? 0 : 1);
