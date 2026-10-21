<?php
// Conversor senzill de Markdown a HTML (manual dins del programa i PDF)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * @param callable|null $imatge Rep la ruta relativa d'una imatge (p. ex. «imatges/ca/01.jpg») i retorna l'URL que s'ha d'usar,
 *                              o null si no existeix.
 */
function markdown_simple(string $md, ?callable $imatge = null): string
{
    $html = '';
    $llista = null;
    $para = [];
    $codi = false;
    $taula = false;
    $tanca = function () use (&$html, &$llista, &$para) {
        if ($para) {
            $html .= '<p>' . implode(' ', $para) . '</p>';
            $para = [];
        }
        if ($llista) {
            $html .= "</$llista>";
            $llista = null;
        }
    };
    $inline = function (string $s): string {
        $s = h($s);
        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*([^*]+)\*(?!\w)/', '<em>$1</em>', $s);
        return preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+|#[\w-]+)\)/', '<a href="$2">$1</a>', $s);
    };
    foreach (preg_split('/\R/', $md) as $linia) {
        if (str_starts_with($linia, '```')) {
            $tanca();
            $html .= $codi ? '</pre>' : '<pre>';
            $codi = !$codi;
            continue;
        }
        if ($codi) {
            $html .= h($linia) . "\n";
            continue;
        }
        if (str_starts_with(trim($linia), '|')) {
            $cells = array_map('trim', explode('|', trim(trim($linia), '|')));
            if (preg_match('/^[\s|:-]+$/', $linia)) {
                continue; // separador de capçalera
            }
            if (!$taula) {
                $tanca();
                $html .= '<div class="taula-wrap"><table class="taula"><thead><tr><th>' . implode('</th><th>', array_map($inline, $cells)) . '</th></tr></thead><tbody>';
                $taula = true;
            } else {
                $html .= '<tr><td>' . implode('</td><td>', array_map($inline, $cells)) . '</td></tr>';
            }
            continue;
        }
        if ($taula) {
            $html .= '</tbody></table></div>';
            $taula = false;
        }
        if (preg_match('/^!\[([^\]]*)\]\(([\w\/.-]+)\)\s*$/', trim($linia), $m)) {
            $tanca();
            $src = $imatge ? $imatge($m[2]) : $m[2];
            if ($src) {
                $html .= '<figure class="captura"><img src="' . h($src) . '" alt="' . h($m[1]) . '" loading="lazy">'
                    . ($m[1] !== '' ? '<figcaption>' . $inline($m[1]) . '</figcaption>' : '') . '</figure>';
            }
            continue;
        }
        if (trim($linia) === '') {
            $tanca();
        } elseif (preg_match('/^(#{1,4})\s+(.*)$/', $linia, $m)) {
            $tanca();
            $n = strlen($m[1]) + 1;
            $id = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($m[2])), '-');
            $html .= "<h$n id=\"" . h($id) . "\">" . $inline($m[2]) . "</h$n>";
        } elseif (preg_match('/^\s*([-*]|\d+\.)\s+(.*)$/', $linia, $m)) {
            if ($para) {
                $html .= '<p>' . implode(' ', $para) . '</p>';
                $para = [];
            }
            $tipus = ctype_digit($m[1][0]) ? 'ol' : 'ul';
            if ($llista !== $tipus) {
                if ($llista) {
                    $html .= "</$llista>";
                }
                $inici = (int)$m[1];
                $html .= $tipus === 'ol' && $inici > 1 ? "<ol start=\"$inici\">" : "<$tipus>";
                $llista = $tipus;
            }
            $html .= '<li>' . $inline($m[2]) . '</li>';
        } elseif (str_starts_with($linia, '> ')) {
            $tanca();
            $html .= '<blockquote>' . $inline(substr($linia, 2)) . '</blockquote>';
        } else {
            if ($llista) {
                $html .= "</$llista>";
                $llista = null;
            }
            $para[] = $inline(trim($linia));
        }
    }
    $tanca();
    return $html . ($taula ? '</tbody></table></div>' : '');
}

