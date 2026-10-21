<?php
// Xifrat de les còpies de seguretat externes amb contrasenya (libsodium)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Format del fitxer .xifrat:
//   "TEIXITX1" | sal (16) | opslimit (4, LE) | memlimit en KiB (4, LE) | capçalera secretstream (24) | blocs
//   cada bloc: longitud (4, LE) + bloc xifrat (XChaCha20-Poly1305); l'últim porta l'etiqueta FINAL.
// La clau es deriva de la contrasenya amb Argon2id. A l'ordinador només es guarda la clau derivada
// (per a poder fer les còpies automàtiques); la contrasenya fa falta per a restaurar.

const XIFRAT_MAGIC = 'TEIXITX1';
const XIFRAT_EXTENSIO = '.xifrat';
const XIFRAT_BLOC = 1048576;

function xifrat_disponible(): bool
{
    return function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push') && function_exists('sodium_crypto_pwhash');
}

function deriva_clau(string $contrasenya, string $sal, int $ops = 0, int $mem = 0): string
{
    return sodium_crypto_pwhash(
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
        $contrasenya,
        $sal,
        $ops ?: SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        $mem ?: SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );
}

/** Configura el xifrat amb una contrasenya nova: guarda la sal i la clau derivada */
function configura_xifrat(string $contrasenya): void
{
    $sal = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    desa_config('copia_xifra_sal', base64_encode($sal));
    desa_config('copia_xifra_clau', base64_encode(deriva_clau($contrasenya, $sal)));
    desa_config('copia_xifra_data', date('Y-m-d H:i:s'));
}

function xifrat_actiu(): bool
{
    return xifrat_disponible() && config('copia_xifra_clau') && config('copia_xifra_sal');
}

function es_xifrat(string $fitxer): bool
{
    return is_file($fitxer) && file_get_contents($fitxer, false, null, 0, strlen(XIFRAT_MAGIC)) === XIFRAT_MAGIC;
}

/** Xifra un fitxer amb la clau configurada */
function xifra_fitxer(string $origen, string $desti): bool
{
    $clau = base64_decode((string)config('copia_xifra_clau'));
    $sal = base64_decode((string)config('copia_xifra_sal'));
    $in = @fopen($origen, 'rb');
    $out = @fopen($desti, 'wb');
    if (!$in || !$out) {
        return false;
    }
    [$estat, $capcalera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clau);
    fwrite($out, XIFRAT_MAGIC . $sal . pack('V', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE) . pack('V', intdiv(SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, 1024)) . $capcalera);
    $mida = filesize($origen);
    $llegit = 0;
    do {
        $bloc = (string)fread($in, XIFRAT_BLOC);
        $llegit += strlen($bloc);
        $final = $llegit >= $mida || feof($in);
        $xifrat = sodium_crypto_secretstream_xchacha20poly1305_push($estat, $bloc, '',
            $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
        $ok = fwrite($out, pack('V', strlen($xifrat)) . $xifrat) !== false;
    } while (!$final && $ok);
    fclose($in);
    $ok = fclose($out) && $ok;
    sodium_memzero($clau);
    return $ok;
}

/** Desxifra una còpia amb la contrasenya. Retorna true si la contrasenya és correcta i el fitxer està sencer */
function desxifra_fitxer(string $origen, string $desti, string $contrasenya): bool
{
    $in = @fopen($origen, 'rb');
    if (!$in) {
        return false;
    }
    $cap = fread($in, strlen(XIFRAT_MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
    if (strlen($cap) < 56 || !str_starts_with($cap, XIFRAT_MAGIC)) {
        fclose($in);
        return false;
    }
    $p = strlen(XIFRAT_MAGIC);
    $sal = substr($cap, $p, SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $p += SODIUM_CRYPTO_PWHASH_SALTBYTES;
    $ops = unpack('V', substr($cap, $p, 4))[1];
    $mem = unpack('V', substr($cap, $p + 4, 4))[1] * 1024;
    $capcalera = substr($cap, $p + 8);
    // Límits raonables perquè un fitxer manipulat no bloquege l'ordinador
    if ($ops < 1 || $ops > 10 || $mem < 8192 || $mem > 1073741824) {
        fclose($in);
        return false;
    }
    $clau = deriva_clau($contrasenya, $sal, $ops, $mem);
    $estat = sodium_crypto_secretstream_xchacha20poly1305_init_pull($capcalera, $clau);
    sodium_memzero($clau);
    $out = fopen($desti, 'wb');
    $ok = false;
    while (!feof($in)) {
        $l = fread($in, 4);
        if ($l === '' || $l === false) {
            break;
        }
        if (strlen($l) < 4) {
            break;
        }
        $n = unpack('V', $l)[1];
        if ($n < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $n > XIFRAT_BLOC + 64) {
            break;
        }
        $r = sodium_crypto_secretstream_xchacha20poly1305_pull($estat, (string)fread($in, $n));
        if ($r === false) {
            break;
        }
        [$clar, $etiqueta] = $r;
        fwrite($out, $clar);
        if ($etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
            $ok = fread($in, 1) === '' && feof($in);
            break;
        }
    }
    fclose($in);
    fclose($out);
    if (!$ok) {
        @unlink($desti);
    }
    return $ok;
}
