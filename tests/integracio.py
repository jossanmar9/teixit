#!/usr/bin/env python3
"""
Proves d'integració de Teixit: arranca el programa de veres (servidor de PHP i una base de dades
d'exemple en una carpeta temporal) i recorre les pantalles com ho faria una persona.

Ús (des de l'arrel del repositori):
    python3 tests/integracio.py              totes les proves, en valencià i castellà
    python3 tests/integracio.py ca           només en valencià
    python3 tests/integracio.py tipus        només una bateria (en els dos idiomes)

Només cal PHP 8.1+ (pdo_sqlite, mbstring, dom, sodium) i Python 3, sense paquets addicionals.
No toca la carpeta data/ del programa.

© 2026 José Vicente Sanjuan · SPDX-License-Identifier: AGPL-3.0-or-later
"""
import csv
import http.cookiejar
import io
import os
import re
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ARREL = Path(__file__).resolve().parent.parent
DIR = ARREL / 'tests' / 'integracio'

# Bateries: nom → (fitxers que s'executen en ordre sobre la mateixa instal·lació, instal·lar?)
# La instal·lació d'exemple és d'una associació cultural, excepte en la bateria «tipus», que es repeteix amb cada tipus d'entitat.
BATERIES = {
    'economia': (['economia.py', 'iban.py', 'societat.py', 'repas.py'], True),
    'uniformes': (['uniformes_moduls.py'], True),
    'tipus': (['tipus.py'], True),
    'avis': (['avis.py'], False),
    'importacio': (['importacio.php'], True),
}
TIPUS_BATERIA = {'tipus': ['cultural', 'falla', 'club', 'ampa']}

ERR_PHP = ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught']


def port_lliure() -> int:
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


class Teixit:
    """Una instal·lació temporal de Teixit amb el servidor de PHP"""

    def __init__(self, idioma: str, installa: bool, tipus: str = 'cultural', bateria_tipus: bool = False):
        self.idioma = idioma
        self.tipus = tipus
        self.dades = tempfile.mkdtemp(prefix='teixit-proves-')
        self.port = port_lliure()
        self.url = f'http://127.0.0.1:{self.port}/'
        self.registre = open(Path(self.dades) / 'servidor.log', 'w')
        self.srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{self.port}', '-t', str(ARREL / 'app'), str(ARREL / 'app' / 'index.php')],
                                    env=dict(os.environ, TEIXIT_DADES=self.dades), stdout=subprocess.DEVNULL, stderr=self.registre)
        for _ in range(100):
            try:
                urllib.request.urlopen(self.url, timeout=1).read()
                break
            except Exception:
                time.sleep(0.1)
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.fallades: list[str] = []
        self.correctes = 0
        self.csrf = self.token(self.obri(''))
        if installa:
            self.envia('', dict(tipus_entitat=tipus, societat_nom='Entitat Prova', idioma=idioma, usuari='admin', contrasenya='contrasenya-llarga1',
                                contrasenya2='contrasenya-llarga1', usuari2='secre', contrasenya_b='contrasenya-llarga2',
                                contrasenya_b2='contrasenya-llarga2', exemple='1', accepta_avis='1'))
            if bateria_tipus is False:
                # Fora de la bateria «tipus», les proves fan servir tots els mòduls
                with sqlite3.connect(Path(self.dades) / 'teixit.db') as b:
                    b.execute("DELETE FROM config WHERE clau = 'moduls_desactivats'")
            self.csrf = self.token(self.obri('r=inici'))

    @staticmethod
    def token(h: str) -> str:
        m = re.search(r'name="_csrf" value="([^"]+)"', h)
        return m.group(1) if m else ''

    def obri(self, q: str = '', raw: bool = False):
        try:
            d = self.op.open(self.url + ('?' + q if q else '')).read()
            return d if raw else d.decode('utf-8', 'replace')
        except urllib.error.HTTPError as e:
            return f'HTTP{e.code} ' + e.read().decode('utf-8', 'replace')

    def envia(self, q: str, dades: dict) -> str:
        d = dict(dades)
        d['_csrf'] = self.csrf
        try:
            return self.op.open(self.url + '?' + q, urllib.parse.urlencode(d, doseq=True).encode()).read().decode('utf-8', 'replace')
        except urllib.error.HTTPError as e:
            return f'HTTP{e.code} ' + e.read().decode('utf-8', 'replace')

    def db(self) -> sqlite3.Connection:
        c = sqlite3.connect(Path(self.dades) / 'teixit.db')
        c.row_factory = sqlite3.Row
        return c

    def tanca(self):
        self.srv.terminate()
        self.srv.wait()
        self.registre.close()
        shutil.rmtree(self.dades, ignore_errors=True)

    def espai(self) -> dict:
        """Les funcions i variables que fan servir els fitxers de proves"""
        c = self
        db = c.db()

        def ok(cond, missatge):
            if cond:
                c.correctes += 1
            else:
                c.fallades.append(missatge)
                print(f'    FALLA: {missatge}')

        def get(q='', raw=False):
            r = c.op.open(c.url + ('?' + q if q else ''))
            d = r.read()
            return (d, r.headers) if raw else d.decode('utf-8', 'replace')

        def multipart(q, camps, fitxer=None, tipus='application/octet-stream'):
            b = '----' + uuid.uuid4().hex
            parts = [f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in dict(camps, _csrf=c.csrf).items()]
            if fitxer:
                nom, contingut = fitxer
                parts.append(f'--{b}\r\nContent-Disposition: form-data; name="fitxer"; filename="{nom}"\r\nContent-Type: {tipus}\r\n\r\n'.encode() + contingut + b'\r\n')
            req = urllib.request.Request(c.url + '?' + q, data=b''.join(parts) + f'--{b}--\r\n'.encode(),
                                         headers={'Content-Type': 'multipart/form-data; boundary=' + b})
            return c.op.open(req).read().decode('utf-8', 'replace')

        def form(q):
            h = c.obri(q)
            f = dict(re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', h))
            for nm, cos in re.findall(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', h, re.S):
                m = re.search(r'<option value="([^"]*)" selected', cos)
                f[nm] = m.group(1) if m else re.search(r'<option value="([^"]*)"', cos).group(1)
            for nm, cos in re.findall(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', h, re.S):
                f[nm] = cos
            return h, f

        return dict(
            B=c.url, op=c.op, TIPUS=c.tipus, C=c.csrf, CSRF=c.csrf, D=c.dades, DADES=c.dades, S=c.dades, L=c.idioma, LANG=c.idioma, PORT=c.port,
            db=db, n=lambda q, *a: db.execute(q, a).fetchone()[0], ok=ok, fails=c.fallades,
            G=c.obri, P=c.envia, get=get, post=c.envia, envia=c.envia, form=form, multipart=multipart,
            err=lambda h: any(x in h for x in ERR_PHP),
            re=re, csv=csv, io=io, http=http, os=os, time=time, uuid=uuid, sqlite3=sqlite3, urllib=urllib, subprocess=subprocess,
        )


def executa(bateria: str, idioma: str) -> tuple[int, list[str]]:
    correctes, fallades = 0, []
    for tipus in TIPUS_BATERIA.get(bateria, ['cultural']):
        c_, f_ = executa_tipus(bateria, idioma, tipus)
        correctes += c_
        fallades += f_
    return correctes, fallades


def executa_tipus(bateria: str, idioma: str, tipus: str) -> tuple[int, list[str]]:
    fitxers, installa = BATERIES[bateria]
    c = Teixit(idioma, installa, tipus, bateria in TIPUS_BATERIA)
    try:
        for f in fitxers:
            ruta = DIR / f
            print(f'  {bateria}/{f} ({idioma}' + (f', {tipus}' if bateria in TIPUS_BATERIA else '') + ')')
            if f.endswith('.php'):
                # Proves que criden les funcions de PHP directament, sobre la mateixa base de dades
                r = subprocess.run(['php', str(ruta)], env=dict(os.environ, TEIXIT_DADES=c.dades), capture_output=True, text=True)
                for linia in (r.stdout + r.stderr).splitlines():
                    if linia.startswith('ok:'):
                        c.correctes += 1
                    elif linia.startswith('FALLA') or (any(x in linia for x in ERR_PHP) and 'in Unknown on line 0' not in linia):
                        c.fallades.append(f'{f}: {linia}')
                        print(f'    {linia}')
                continue
            try:
                exec(compile(ruta.read_text(encoding='utf-8'), str(ruta), 'exec'), c.espai())
            except Exception as e:
                c.fallades.append(f'{f}: excepció {type(e).__name__}: {e}')
                print(f'    EXCEPCIÓ en {f}: {type(e).__name__}: {e}')
        # Errors de PHP que hagen eixit al registre del servidor
        c.registre.flush()
        # (els avisos d'arrancada de PHP, «in Unknown on line 0», són de la instal·lació de PHP, no de Teixit)
        for linia in Path(c.dades, 'servidor.log').read_text(errors='replace').splitlines():
            if any(x in linia for x in ERR_PHP) and 'in Unknown on line 0' not in linia:
                c.fallades.append(f'servidor: {linia.strip()}')
                print(f'    PHP: {linia.strip()}')
        return c.correctes, c.fallades
    finally:
        c.tanca()


def main() -> int:
    args = sys.argv[1:]
    idiomes = [a for a in args if a in ('ca', 'es')] or ['ca', 'es']
    bateries = [a for a in args if a in BATERIES] or list(BATERIES)
    desconeguts = [a for a in args if a not in ('ca', 'es') and a not in BATERIES]
    if desconeguts:
        print(f'No conec: {", ".join(desconeguts)}. Bateries: {", ".join(BATERIES)}')
        return 2
    total, fallades = 0, []
    inici = time.time()
    for idioma in idiomes:
        for b in bateries:
            n, f = executa(b, idioma)
            total += n
            fallades += [f'[{idioma}] {b}: {x}' for x in f]
    print(f'\n{total} comprovacions correctes, {len(fallades)} fallades ({time.time() - inici:.0f} s).')
    for f in fallades:
        print('  ✗', f)
    return 1 if fallades else 0


if __name__ == '__main__':
    sys.exit(main())
