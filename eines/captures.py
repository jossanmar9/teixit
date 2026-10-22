#!/usr/bin/env python3
"""
Genera les captures de pantalla dels manuals (docs/imatges/ca i docs/imatges/es).

Arranca Teixit amb PHP i dades d'exemple en una carpeta temporal, fa cada procés del manual
amb un navegador sense interfície i guarda una captura de cada pas. El botó o camp que cal
prémer es ressalta en taronja.

Requisits: PHP 8.1+ (pdo_sqlite, mbstring), Python 3, Pillow i Playwright:
    pip install playwright pillow && playwright install chromium
Ús (des de l'arrel del repositori):
    python3 eines/captures.py               totes les captures, en valencià i castellà
    python3 eines/captures.py 78 50-55      només estes (les altres no es toquen)
    python3 eines/captures.py es web        només les de la web, en castellà

Per a no fer créixer el repositori, en una versió nova només es tornen a fer les captures de les
pantalles que han canviat: totes porten la data del dia i canviarien encara que la pantalla fora igual.

© 2026 José Vicente Sanjuan · SPDX-License-Identifier: AGPL-3.0-or-later
"""
import asyncio
import io
import os
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
from datetime import date
from pathlib import Path
from urllib.parse import quote

from PIL import Image, ImageChops, ImageDraw, ImageFont
from playwright.async_api import async_playwright

ARREL = Path(__file__).resolve().parent.parent
EIXIDA = ARREL / 'docs' / 'imatges'
AMPLE, ALT = 1280, 800
ESCALA = 1.5
QUALITAT = 82  # WebP: ocupa un 40 % menys que el JPG amb la mateixa qualitat
RESSALT = '3px solid #ff6a00'

# Les captures són d'una associació cultural (el tipus d'entitat més general), amb tots els mòduls actius
TEXTOS = {
    'ca': dict(societat="Associació Cultural d'Exemple", carpeta='E:\\Copies Teixit', poble="Poble d'Exemple",
               grups=['Teatre · Grup A', 'Ball tradicional · Grup A'], activitat='Ioga', grup_nou='Grup B', funcio='Vestuari',
               material='Equip de so', provincia='Castelló', adreca='Carrer Major, 7', bo='Bo', signant='Secretària Exemple',
               carrec='secretària', president='President Exemple', samarreta="Samarreta de l'associació",
               observacio='Ha millorat molt la dicció i la presència a l\'escenari.'),
    'es': dict(societat='Asociación Cultural de Ejemplo', carpeta='E:\\Copias Teixit', poble='Pueblo de Ejemplo',
               grups=['Teatro · Grupo A', 'Baile tradicional · Grupo A'], activitat='Yoga', grup_nou='Grupo B', funcio='Vestuario',
               material='Equipo de sonido', provincia='Castellón', adreca='Calle Mayor, 7', bo='Bueno', signant='Secretaria Ejemplo',
               carrec='secretaria', president='Presidente Ejemplo', samarreta='Camiseta de la asociación',
               observacio='Ha mejorado mucho la dicción y la presencia en el escenario.'),
}


def port_lliure() -> int:
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def retalla_buit(im: Image.Image, marge: int = 36, menu: int = 0, alt_minim: int = 0) -> Image.Image:
    """Lleva el fons buit de baix (i de la dreta si n'hi ha molt) perquè la captura s'ajuste al contingut.
    menu: amplada del menú lateral, que no es té en compte en buscar on acaba el contingut."""
    fons = im.getpixel((im.width - 2, im.height - 2))
    zona = im.crop((menu, 0, im.width, im.height))
    dif = ImageChops.difference(zona, Image.new('RGB', zona.size, fons)).convert('L').point(lambda v: 255 if v > 12 else 0)
    caixa = dif.getbbox()
    if not caixa:
        return im
    caixa = (caixa[0] + menu, caixa[1], caixa[2] + menu, caixa[3])
    baix = min(im.height, max(caixa[3] + marge, alt_minim))
    dreta = im.width if caixa[2] > im.width * 0.75 else min(im.width, caixa[2] + marge)
    return im.crop((0, 0, dreta, baix)) if (baix < im.height - 10 or dreta < im.width - 10) else im


def logo_exemple(desti: Path) -> None:
    """Un logotip inventat per a l'associació d'exemple"""
    im = Image.new('RGBA', (400, 400), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.ellipse((10, 10, 390, 390), fill='#2f4f6f')
    d.ellipse((40, 40, 360, 360), outline='#f2c14e', width=10)
    try:
        f = ImageFont.truetype('DejaVuSans-Bold.ttf', 120)
    except OSError:
        f = ImageFont.load_default()
    d.text((200, 200), 'ACE', fill='#f2c14e', font=f, anchor='mm')
    im.save(desti)


class Captures:
    def __init__(self, pagina, url, idioma):
        self.p = pagina
        self.url = url
        self.l = idioma
        self.dir = EIXIDA / idioma
        self.dir.mkdir(parents=True, exist_ok=True)

    async def ves(self, ruta: str):
        await self.p.goto(self.url + ruta)
        await self.p.wait_for_load_state('networkidle')

    async def ressalta(self, *selectors):
        for sel in selectors:
            await self.p.locator(sel).first.evaluate(
                "(e, o) => { e.dataset.ressalt = 1; e.style.outline = o; e.style.outlineOffset = '3px'; e.scrollIntoViewIfNeeded && e.scrollIntoViewIfNeeded(); }", RESSALT)

    async def lleva_ressalt(self):
        await self.p.evaluate("document.querySelectorAll('[data-ressalt]').forEach(e => { e.style.outline = ''; delete e.dataset.ressalt; })")

    async def foto(self, nom: str, *ressalts, zona: str | None = 'main', alt_max: int = 1000):
        """Captura. zona=None: tota la finestra; zona='main': sense el menú lateral."""
        if ressalts:
            await self.ressalta(*ressalts)
        await self.p.evaluate("window.scrollTo(0, 0)")
        if zona:
            caixa = await self.p.locator(zona).first.bounding_box()
            alt = min(caixa['height'], alt_max)
            # Si la zona no cap, s'allarga la finestra en lloc de fer una captura de pàgina sencera
            necessari = int(caixa['y'] + alt) + 1
            if necessari > ALT:
                await self.p.set_viewport_size({'width': AMPLE, 'height': necessari})
                caixa = await self.p.locator(zona).first.bounding_box()
            png = await self.p.screenshot(clip=dict(x=caixa['x'], y=caixa['y'], width=caixa['width'], height=alt))
            await self.p.set_viewport_size({'width': AMPLE, 'height': ALT})
        else:
            png = await self.p.screenshot()
        im = Image.open(io.BytesIO(png)).convert('RGB')
        # Amb el menú lateral (finestra sencera) es mira només el contingut de la dreta
        im = retalla_buit(im) if zona else retalla_buit(im, menu=int(250 * ESCALA), alt_minim=int(420 * ESCALA))
        if not cal_desar(nom):
            await self.lleva_ressalt()
            return
        im.save(self.dir / f'{nom}.webp', 'WEBP', quality=QUALITAT, method=6)
        await self.lleva_ressalt()
        print(f'  {self.l}/{nom}.webp  {im.size[0]}x{im.size[1]}')

    async def omple(self, camps: dict):
        for nom, valor in camps.items():
            el = self.p.locator(f'[name="{nom}"]').last
            tipus = await el.evaluate('e => e.tagName + ":" + (e.type || "")')
            if tipus.startswith('SELECT'):
                valors = await el.evaluate('e => [...e.options].map(o => o.value)')
                if str(valor) in valors:
                    await el.select_option(value=str(valor))
                else:
                    await el.select_option(label=str(valor))
            elif tipus.endswith('checkbox'):
                await el.set_checked(bool(valor))
            else:
                await el.fill(str(valor))

    async def desa(self):
        await self.p.locator('.contingut form .accions button:not(.perill):not(.secundari), .caixa form button').first.click()
        await self.p.wait_for_load_state('networkidle')
        errors = await self.p.locator('.avis.error').all_inner_texts()
        if errors:
            raise RuntimeError(f'Error en desar ({self.p.url}): {errors}')

    def id_actual(self) -> str:
        return re.search(r'[?&]id=(\d+)', self.p.url).group(1)

    async def boto_afig(self, entitat: str):
        """Selector del botó «+ Afig» d'un apartat de la fitxa"""
        return f'section.fill a.boto[href*="e={entitat}"]'


async def fes_captures(l: str):
    t = TEXTOS[l]
    dades = Path(tempfile.mkdtemp(prefix='teixit-captures-'))
    port = port_lliure()
    entorn = dict(os.environ, TEIXIT_DADES=str(dades), TEIXIT_TOKEN='captures')
    srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'app', 'app/index.php'], cwd=ARREL, env=entorn,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(1.2)
    url = f'http://127.0.0.1:{port}/'
    logo = dades / 'logo-exemple.png'
    logo_exemple(logo)
    avui = date.today().isoformat()
    try:
        async with async_playwright() as pw:
            regio = 'es_ES.UTF-8' if l == 'es' else 'ca_ES.UTF-8'
            nav = await pw.chromium.launch(args=[f'--lang={"es-ES" if l == "es" else "ca-ES"}'],
                                           env=dict(os.environ, LANG=regio, LC_ALL=regio, LANGUAGE=l))
            ctx = await nav.new_context(viewport={'width': AMPLE, 'height': ALT}, device_scale_factor=ESCALA,
                                        locale='es-ES' if l == 'es' else 'ca-ES')
            p = await ctx.new_page()
            c = Captures(p, url, l)

            # --- Primera execució ---
            await c.ves(f'?r=idioma&l={l}&tornar=/')
            await p.locator('input[name="tipus_entitat"][value="cultural"]').check()
            await c.omple({'societat_nom': t['societat'], 'usuari': 'admin', 'contrasenya': 'contrasenya1',
                           'contrasenya2': 'contrasenya1', 'usuari2': 'secretaria', 'contrasenya_b': 'contrasenya2',
                           'contrasenya_b2': 'contrasenya2', 'exemple': True, 'accepta_avis': True})
            await c.foto('01-configuracio-inicial', '.tipus-opcio:has(input:checked)', 'input[name="accepta_avis"]', 'button', zona='.caixa', alt_max=2300)
            await c.desa()

            # Codi de recuperació (es mostra una sola vegada, just després de la configuració inicial)
            # El codi és aleatori: a la imatge se'n posa un de fix perquè no canvie en cada versió
            await p.evaluate("document.querySelector('.codi-recuperacio').textContent = 'K7QM-4XRP-2HDN-8WTB'")
            await c.foto('01b-codi-recuperacio', '.codi-recuperacio', zona='.caixa')
            await p.locator('.caixa a.boto').click()
            await p.wait_for_load_state('networkidle')
            # Tots els mòduls actius, perquè el manual els explica tots
            await c.ves('?r=moduls')
            for casella in await p.locator('input[name="moduls[]"]').all():
                await casella.check()
            await c.desa()
            await c.ves('?r=inici')

            # Inici i configuració
            await c.foto('02-inici', zona=None)
            await c.ves('?r=configuracio')
            await p.set_input_files('input[name="logo"]', str(logo))
            await c.omple({'societat_adreca': 'Plaça Major, 1' if l == 'ca' else 'Plaza Mayor, 1', 'societat_telefon': '964 000 000', 'societat_email': 'escola@exemple.org'})
            await c.foto('03-configuracio', 'input[name="logo"]')
            await c.desa()

            # --- Persones ---
            await c.ves('?r=llista&e=persones')
            await c.foto('04-persones-llista', 'a.boto[href*="r=edita"]', '.avis.atencio', zona=None)
            await c.ves('?r=llista&e=persones&filtre=incomplets')
            await c.foto('04b-persones-avis', zona=None)
            await c.ves('?r=edita&e=persones')
            await c.omple({'nom': 'Alumne7', 'cognoms': 'Exemple Prova', 'data_naixement': '2016-03-14', 'document': '',
                           'telefon': '600 000 007', 'email': 'familia7@exemple.org', 'adreca': t['adreca'],
                           'codi_postal': '12000', 'poblacio': t['poble'], 'provincia': t['provincia']})
            await c.foto('05-persona-nova', 'form .accions button')
            await c.desa()
            persona = c.id_actual()
            await c.foto('06-persona-fitxa', await c.boto_afig('alumnes'), alt_max=1300)

            # --- Alta d'alumne ---
            await p.locator(await c.boto_afig('alumnes')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.foto('07-alumne-nou', 'form .accions button')
            await c.desa()

            # Tutor legal
            await p.locator(await c.boto_afig('tutories')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.omple({'tutor_id': 'Exemple, Tutor1', 'tipus_relacio': 'mare', 'principal': True})
            await c.foto('09-tutor-nou', 'select[name="tutor_id"]')
            await c.desa()

            # Matrícula
            await p.locator(await c.boto_afig('matricules')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.foto('10-matricula-nova', 'select[name="curs_id"]')
            await c.desa()
            matricula = c.id_actual()
            for i, grup in enumerate(t['grups']):
                await c.ves(f'?r=edita&e=matricula_grups&f[matricula_id]={matricula}&tornar=' + quote(f'?r=fitxa&e=matricules&id={matricula}', safe=''))
                opcio = await p.locator('select[name="grup_id"] option').filter(has_text=grup).first.get_attribute('value')
                await c.omple({'grup_id': opcio})
                if i == 0:
                    await c.foto('12-inscripcio-grup', 'select[name="grup_id"]')
                await c.desa()

            # Resum de l'alumne
            alumne = await p.locator('a[href*="e=alumnes&id="]').first.get_attribute('href')
            await c.ves(alumne.lstrip('/'))
            await c.foto('14-alumne-resum', 'section.fill header a.boto[target="_blank"]', alt_max=1100)

            # --- Soci ---
            await c.ves(f'?r=fitxa&e=persones&id={persona}')
            await p.locator(await c.boto_afig('socis')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.omple({'numero': '120', 'tipus': 'Juvenil'})
            await c.foto('15-soci-nou', 'select[name="tipus"]')
            await c.desa()
            enllac = await p.locator('section.fill a[href*="e=socis&id="]').first.get_attribute('href')
            soci = re.search(r'id=(\d+)', enllac).group(1)
            await c.ves('?r=llista&e=socis')
            await c.foto('16-socis-llista', 'input[type="search"]', zona=None)
            await c.ves(f'?r=edita&e=socis&id={soci}')
            await c.omple({'estat': 'baixa', 'data_baixa': avui})
            await c.foto('17-soci-baixa', 'select[name="estat"]', 'input[name="data_baixa"]')

            # --- Membre d'una secció ---
            await c.ves(f'?r=fitxa&e=persones&id={persona}')
            await p.locator(await c.boto_afig('membres')).first.click()
            await p.wait_for_load_state('networkidle')
            agr = await p.locator('select[name="agrupacio_id"] option').nth(2).get_attribute('value')
            await c.omple({'agrupacio_id': agr, 'instrument_veu': t['funcio'], 'funcio': 'membre'})
            await c.foto('18-membre-nou', 'select[name="agrupacio_id"]', 'input[name="instrument_veu"]')
            await c.desa()
            await c.ves(f'?r=fitxa&e=agrupacions&id={agr}')
            await c.foto('19-agrupacio-fitxa', await c.boto_afig('membres'))

            # --- Monitors ---
            await c.ves('?r=edita&e=persones')
            await c.omple({'nom': 'Monitor3', 'cognoms': 'Exemple', 'data_naixement': '1982-02-02', 'document': '00000103X', 'telefon': '600 000 103'})
            await c.desa()
            await p.locator(await c.boto_afig('professors')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.foto('20-professor-nou', 'form .accions button')
            await c.desa()

            # --- Cursos, activitats i grups ---
            await c.ves('?r=fitxa&e=cursos&id=1')
            await c.foto('21-curs-fitxa', await c.boto_afig('periodes'), alt_max=1000)
            await c.ves('?r=edita&e=grups')
            assig = await p.locator('select[name="assignatura_id"] option').filter(has_text=t['activitat']).first.get_attribute('value')
            prof = await p.locator('select[name="professor_id"] option').filter(has_text='Monitor3').first.get_attribute('value')
            await c.omple({'assignatura_id': assig, 'nom': t['grup_nou'], 'professor_id': prof, 'aula_id': '2'})
            await c.foto('22-grup-nou', 'form .accions button')
            await c.desa()
            await p.locator(await c.boto_afig('horaris')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.omple({'dia': '2', 'hora_inici': '17:00', 'hora_fi': '18:00'})
            await c.foto('23-horari-nou', 'select[name="dia"]')
            await c.desa()
            await c.foto('24-grup-fitxa', await c.boto_afig('horaris'))
            await c.ves('?r=horari')
            await c.foto('25-horari', zona=None)

            # --- Notes i butlletins ---
            await c.ves('?r=notes&grup=1')
            notes = p.locator('input.nota')
            for i in range(await notes.count()):
                await notes.nth(i).fill(['7', '8,5', 'Notable', '6', '9', '7,5', '8'][i % 7])
            await p.locator('textarea[name^="obs["]').first.fill(t['observacio'])
            await c.foto('27-qualificacions', 'select[name="periode"]', 'form .accions button')
            await c.desa()
            await c.ves('?r=butlleti&imprimir=1&curs=1&periode=1&ambit=alumne&alumne=1')
            await c.foto('29-butlleti-impres', zona='.full', alt_max=1150)

            # --- Inventari ---
            await c.ves('?r=edita&e=instruments')
            await c.omple({'inventari': 'INV-005', 'tipus': t['material'], 'marca': 'Yamaha', 'model': 'MG10XU',
                           'num_serie': '123456', 'estat': 'bo', 'ubicacio': 'Magatzem' if l == 'ca' else 'Almacén', 'data_adquisicio': '2024-09-01'})
            await c.foto('30-instrument-nou', 'form .accions button')
            await c.desa()
            await p.locator(await c.boto_afig('cessions')).first.click()
            await p.wait_for_load_state('networkidle')
            per = await p.locator('select[name="persona_id"] option').filter(has_text='Alumne7').first.get_attribute('value')
            await c.omple({'persona_id': per, 'data_prevista': f'{date.today().year + 1}-06-30', 'estat_entrega': t['bo'], 'fianca': '50'})
            await c.foto('31-cessio-nova', 'select[name="persona_id"]', 'input[name="data_prevista"]')
            await c.desa()
            await c.ves('?r=llista&e=cessions&filtre=actives')
            await c.foto('33-cessions-llista', 'select[name="filtre"]', zona=None)

            # --- Còpies ---
            await c.ves('?r=copia')
            await c.p.locator('form button').filter(has_text=re.compile('ara|ahora')).first.click()
            await p.wait_for_load_state('networkidle')
            await c.foto('34-copies', 'form:has(input[value="fes"]) button', alt_max=900)
            await c.p.locator('input[name="copia_externa"]').fill(t['carpeta'])
            await c.ressalta('input[name="copia_externa"]')
            await c.p.locator('input[name="copia_externa"]').scroll_into_view_if_needed()
            await p.set_viewport_size({'width': AMPLE, 'height': 2400})
            caixa = await p.locator('section.fill:has(input[name="copia_externa"])').bounding_box()
            png = await p.screenshot(clip=caixa)
            if cal_desar('35-copies-carpeta'):
                retalla_buit(Image.open(io.BytesIO(png)).convert('RGB')).save(c.dir / '35-copies-carpeta.webp', 'WEBP', quality=QUALITAT, method=6)
                print(f'  {l}/35-copies-carpeta.webp')
            await c.lleva_ressalt()
            caixa = await p.locator('table.taula').first.bounding_box()
            png = await p.screenshot(clip=caixa)
            await p.set_viewport_size({'width': AMPLE, 'height': ALT})
            if cal_desar('36-copies-llista'):
                Image.open(io.BytesIO(png)).convert('RGB').save(c.dir / '36-copies-llista.webp', 'WEBP', quality=QUALITAT, method=6)
                print(f'  {l}/36-copies-llista.webp')

            # --- Usuaris i compte ---
            await c.ves('?r=usuaris&id=2')
            await c.foto('37-usuari', 'select[name="rol"]', 'input[name="contrasenya"]')
            await c.ves('?r=perfil')
            await c.foto('38-meu-compte', '.usuari > a', 'input[name="actual"]')
            await c.ves('?r=ajuda')
            await c.foto('39-ajuda', '.accions a.boto', zona=None)

            # --- Documents i dades (v1.4) ---
            await c.ves('?r=configuracio')
            await c.omple({'societat_poblacio': t['poble'], 'certificat_signant': t['signant'], 'certificat_carrec': t['carrec'], 'certificat_president': t['president']})
            await c.foto('40-config-documents', 'input[name="certificat_signant"]', zona='fieldset:has(input[name="certificat_signant"])')
            await c.desa()
            await c.ves('?r=edita&e=persones&id=11')
            await c.foto('41-persona-consentiment', 'select[name="consentiment_imatge"]', 'input[name="consentiment_data"]', alt_max=1100)
            await c.ves('?r=consentiment&persona=11')
            await c.foto('42-consentiment-full', zona='.full', alt_max=1200)
            await c.ves('?r=llistats')
            await c.foto('43-llistats', zona=None)
            await c.ves('?r=llistats&informe=alumnes_grup&curs=1&grup=1')
            await c.foto('44-llistat-grup', 'select[name="grup"]', '.accions a.boto[target]')
            await c.ves('?r=llistats&informe=consentiments')
            await c.foto('45-drets-imatge', '.accions a.boto.secundari[target]')
            await c.ves('?r=llistats&informe=etiquetes')
            await c.foto('46-etiquetes', 'select[name="format"]', 'input[name="salta"]')
            await c.ves('?r=llistats&informe=antiguitat&fites=25,%2050')
            await c.foto('47-antiguitat', 'input[name="fites"]')
            await c.ves('?r=llistats&informe=memoria&curs=1')
            await c.foto('48-memoria', '.accions a.boto[target]', alt_max=1100)
            await c.ves('?r=certificat&tipus=soci&id=1')
            await c.foto('49-certificat', zona='.full', alt_max=1200)
            await c.ves('?r=passa_curs&curs=1')
            await c.foto('50-passa-curs', 'input[name="nom"]', 'input[name="matricules"]')

            # Importació d'un Excel inventat
            import openpyxl
            from datetime import date as d
            wb = openpyxl.Workbook()
            ws = wb.active
            ws.append(['Núm. soci', 'Nom i cognoms', 'DNI', 'Data naixement', 'Telèfon', 'Adreça', 'Població'] if l == 'ca'
                      else ['Nº socio', 'Nombre y apellidos', 'DNI', 'Fecha nacimiento', 'Teléfono', 'Dirección', 'Población'])
            ws.append(['301', 'Exemple, Soci7', '00000301X', d(1975, 4, 2), '600 000 301', t['adreca'], t['poble']])
            ws.append(['302', 'Exemple, Soci8', '00000302X', d(1982, 11, 20), '600 000 302', t['adreca'], t['poble']])
            ws.append(['102', 'Membre2 Exemple', '00000013X', d(1989, 12, 2), None, None, None])
            ws.append(['303', 'Tutor2 Exemple', None, None, '600 000 004', None, None])
            ws.append(['304', 'Exemple, Soci9', None, '31/02/1990', None, None, t['poble']])
            fitxer = Path(tempfile.gettempdir()) / f'socis-{l}.xlsx'
            wb.save(fitxer)
            await c.ves('?r=importa')
            await p.locator('input[name="tipus"][value="socis"]').check()
            await p.set_input_files('input[name="fitxer"]', str(fitxer))
            await c.foto('51-importa', 'input[name="tipus"][value="socis"]', 'input[name="fitxer"]')
            await c.desa()
            await c.foto('52-importa-columnes', 'select[name="col[1]"]', alt_max=1100)
            await c.desa()
            await c.foto('53-importa-revisa', 'label.mateixa', alt_max=1100)

            # Còpies xifrades
            await c.ves('?r=copia')
            await p.locator('input[name="xifra"]').check()
            await c.foto('54-copies-xifrat', 'input[name="xifra"]', zona='fieldset:has(input[name="xifra"])')

            # --- Economia ---
            await c.ves('?r=economia')
            await c.foto('55-economia', 'a.boto[href*="genera_rebuts"]', zona=None)
            await c.ves('?r=llista&e=tarifes')
            await c.foto('56-tarifes', 'a.boto[href*="r=edita"]')
            await c.ves('?r=economia_config')
            await c.omple({'sepa_nif': 'G00000000', 'sepa_iban': 'ES91 2100 0418 4502 0005 1332'})
            await c.foto('57-economia-config', 'input[name="sepa_iban"]', 'input[name="descompte_soci"]', alt_max=1300)
            await c.desa()
            mes = date.today().strftime('M%Y-%m')
            await c.ves(f'?r=genera_rebuts&tipus=escola&curs=1&periode=MAT')
            await c.foto('58-genera-rebuts', 'select[name="periode"] >> nth=1', alt_max=1100)
            await c.ves('?r=llista&e=mandats')
            await c.foto('59-domiciliacions', 'a.boto[href*="r=edita"]')
            await c.ves('?r=mandat_doc&id=1')
            await c.foto('60-ordre-domiciliacio', zona='.full', alt_max=1300)
            await c.ves('?r=remeses')
            await c.foto('61-remeses', 'input[name="data_cobrament"]', '.formulari .accions button', alt_max=1200)
            await c.desa()
            await c.foto('62-remesa', 'a.boto[href*="remesa_xml"]', 'form[action*="cobrada"] button')
            await c.ves('?r=llista&e=rebuts&filtre=efectiu')
            await p.locator('table.taula tbody tr a').first.click()
            await p.wait_for_load_state('networkidle')
            await c.foto('63-rebut', 'form[action*="accio=pagat"] button', 'a.boto[href*="rebut_doc"]')
            await c.ves('?r=rebut_doc')
            await c.foto('64-rebut-imprés', zona='.full', alt_max=1300)
            await c.ves('?r=loteria&id=1')
            await c.foto('65-loteria', 'select[name="persona_id"]', 'form.formulari .accions button', alt_max=1200)
            await c.ves('?r=edita&e=talonaris&id=2')
            await c.foto('66-talonari', 'input[name="venudes"]', 'input[name="tornades"]', 'input[name="import_liquidat"]')
            await c.ves('?r=participacions&talonari=1')
            await c.foto('67-participacions', zona='.full', alt_max=700)

            # --- Junta, actes, documents i llibre de caixa ---
            await c.ves('?r=junta')
            await c.foto('68-junta', 'a.boto[href*="e=carrecs"]', alt_max=1300)
            await c.ves('?r=fitxa&e=reunions&id=1')
            await c.foto('69-reunio', 'a.boto[href*="doc=acta"]', 'form.en-linia button', alt_max=1300)
            await c.ves('?r=reunio_doc&id=1&doc=acta')
            await c.foto('70-acta', zona='.full', alt_max=1400)
            await c.ves('?r=llista&e=documents')
            await c.foto('71-documents', 'a.boto[href*="r=edita"]')
            await c.ves('?r=edita&e=documents')
            await c.omple({'titol': 'Conveni amb l\'ajuntament' if l == 'ca' else 'Convenio con el ayuntamiento', 'tipus': 'conveni'})
            await c.foto('72-document-nou', 'input[name="fitxer"]', 'input[name="data_caducitat"]', alt_max=1100)
            await c.ves('?r=full_alta')
            await c.foto('73-full-alta', zona='.full', alt_max=1700)
            await c.ves('?r=comunicats&a=socis')
            await c.foto('74-comunicats', 'select[name="a"]', '.accions button', alt_max=1100)
            await c.ves('?r=rgpd')
            await c.foto('75-rgpd', zona='.full', alt_max=1300)
            await c.ves('?r=caixa')
            await c.foto('76-caixa', 'a.boto[href*="e=moviments"]', alt_max=1300)

            # --- Tipus de soci, roba i mòduls ---
            await c.ves('?r=llista&e=tipus_socis')
            await c.foto('77-tipus-socis', 'a.boto[href*="r=edita"]')
            await c.ves('?r=uniformes')
            await c.foto('78-uniformes', 'a.boto[href*="e=uniforme_lliuraments"]', alt_max=1500)
            await c.ves('?r=edita&e=uniforme_lliuraments')
            await c.omple({'uniforme_id': t['samarreta'], 'talla': 'M', 'tipus': 'venda'})
            await c.foto('79-uniforme-lliurament', 'input[name="talla"]', 'select[name="tipus"]', alt_max=1000)
            await c.ves('?r=moduls')
            await c.foto('80-moduls', 'input[value="loteria"]', alt_max=1400)

            # --- Captures per a la web: totes de finestra sencera i del mateix format (16:10) ---
            trimestre = f'T{(date.today().month - 1) // 3 + 1}'
            for nom, ruta in [('web-inici', '?r=inici'), ('web-socis', '?r=llista&e=socis'), ('web-junta', '?r=junta'),
                              ('web-acta', '?r=reunio_doc&id=1&doc=acta'), ('web-rebuts', f'?r=genera_rebuts&tipus=socis&any={date.today().year}&periode={trimestre}'),
                              ('web-economia', '?r=economia'), ('web-caixa', '?r=caixa'), ('web-loteria', '?r=loteria&id=1'),
                              ('web-seccio', '?r=fitxa&e=agrupacions&id=1'), ('web-horari', '?r=horari'), ('web-copies', '?r=copia')]:
                await c.ves(ruta)
                if not cal_desar(nom):
                    continue
                png = await p.screenshot()
                Image.open(io.BytesIO(png)).convert('RGB').save(c.dir / f'{nom}.webp', 'WEBP', quality=QUALITAT, method=6)
                print(f'  {l}/{nom}.webp')

            # --- Entrada ---
            ctx2 = await nav.new_context(viewport={'width': AMPLE, 'height': ALT}, device_scale_factor=ESCALA)
            p2 = await ctx2.new_page()
            await p2.goto(url + f'?r=idioma&l={l}&tornar=/')
            await p2.locator('input[name="usuari"]').fill('secretaria')
            c2 = Captures(p2, url, l)
            await c2.foto('00-entrada', zona='.caixa')

            # He oblidat la contrasenya (amb una ruta de Windows d'exemple en lloc de la temporal)
            await p2.goto(url + '?r=recupera')
            await p2.locator('details.sense-codi summary').click()
            await p2.evaluate(r"""document.querySelector('code.ruta').textContent = 'C:\\Users\\Secretaria\\AppData\\Local\\Programs\\Teixit\\data'""")
            await c2.foto('00b-recuperacio', 'input[name="codi"]', 'details.sense-codi', zona='.caixa', alt_max=1600)
            await nav.close()
    finally:
        srv.terminate()
        srv.wait()
        shutil.rmtree(dades, ignore_errors=True)


FILTRE: list[str] = []


def cal_desar(nom: str) -> bool:
    """Si s'han demanat només unes captures (per número, interval o «web»), si esta n'és una"""
    if not FILTRE:
        return True
    num = re.match(r'(\d+)', nom)
    for f in FILTRE:
        if f == 'web' and nom.startswith('web-'):
            return True
        if num and re.fullmatch(r'\d+-\d+', f):
            a, b = map(int, f.split('-'))
            if a <= int(num.group(1)) <= b:
                return True
        elif nom == f or nom.startswith(f + '-') or (num and f.isdigit() and int(f) == int(num.group(1)) and not nom[len(num.group(1)):][:1].isalpha()):
            return True
    return False


async def main():
    idiomes = [a for a in sys.argv[1:] if a in ('ca', 'es')] or ['ca', 'es']
    FILTRE.extend(a for a in sys.argv[1:] if a not in ('ca', 'es'))
    for l in idiomes:
        print(f'Captures en «{l}»' + (f' (només {", ".join(FILTRE)})' if FILTRE else '') + ':')
        if not FILTRE:
            shutil.rmtree(EIXIDA / l, ignore_errors=True)
        await fes_captures(l)


if __name__ == '__main__':
    asyncio.run(main())
