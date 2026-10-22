# Tipus d'entitat: vocabulari, mòduls i tipus de soci inicials, dades d'exemple i llicències federatives.
# Es repeteix amb cada tipus (cultural, falla, club, ampa, penya) i en els dos idiomes.
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py, que hi posa les funcions comunes (G, P, ok, n, db, TIPUS…).

ca = L == 'ca'
ok(n("select valor from config where clau='tipus_entitat'") == TIPUS, f'tipus desat: {TIPUS}')

# Mòduls que es desactiven en instal·lar
esperats = {'cultural': 'uniformes,loteria', 'falla': 'escola,qualificacions', 'club': 'escola,qualificacions', 'ampa': 'qualificacions,instruments,uniformes', 'penya': 'escola,qualificacions'}[TIPUS]
ok(n("select valor from config where clau='moduls_desactivats'") == esperats, f'mòduls desactivats per defecte ({TIPUS})')

# Tipus de soci inicials
noms = [r[0] for r in db.execute('select nom from tipus_socis order by ordre')]
primer = {'cultural': ('Ordinari', 'Ordinario'), 'falla': ('Adult', 'Adulto'), 'club': ('Ordinari', 'Ordinario'), 'ampa': ('Família', 'Familia'), 'penya': ('Ordinari', 'Ordinario')}[TIPUS]
ok(noms and noms[0] == primer[0 if ca else 1], f'tipus de soci inicials: {noms}')

# Vocabulari del menú
h = G('r=inici')
vocab = {
    'cultural': ('Seccions', 'Secciones', 'Associació', 'Asociación'),
    'falla': ('Cens', 'Censo', 'Comissió', 'Comisión'),
    'club': ('Equips', 'Equipos', 'Club', 'Club'),
    'ampa': ('Comissions', 'Comisiones', 'AMPA', 'AMPA'),
    'penya': ('Comissions', 'Comisiones', 'Penya', 'Peña'),
}[TIPUS]
ok(not err(h) and vocab[0 if ca else 1] in h and vocab[2 if ca else 3] in h, f'vocabulari del menú ({TIPUS})')
ok('Escola de música' not in h and 'Escuela de música' not in h and 'Músics' not in h and 'Músicos' not in h, 'sense vocabulari musical a l inici')

# Totes les pantalles i entitats, sense errors de PHP (les dels mòduls desactivats responen 404)
entitats = ['tipus_socis', 'uniformes', 'uniforme_compres', 'uniforme_lliuraments', 'carrecs', 'reunions', 'documents', 'comunicats', 'moviments', 'persones',
            'socis', 'alumnes', 'professors', 'tutories', 'agrupacions', 'membres', 'cursos', 'periodes', 'assignatures', 'aules', 'grups', 'horaris', 'matricules',
            'matricula_grups', 'instruments', 'manteniments', 'cessions', 'tarifes', 'mandats', 'rebuts', 'rebut_linies', 'remeses', 'loteries', 'talonaris']
for e in entitats:
    for q in [f'r=llista&e={e}', f'r=fitxa&e={e}&id=1', f'r=edita&e={e}']:
        h = G(q)
        ok(not err(h) and 'class="detall"' not in h, f'{q} ({TIPUS})')
        ok('Músic' not in h and 'música' not in h and 'Agrupaci' not in h, f'sense vocabulari musical: {q} ({TIPUS})')
for q in ['r=inici', 'r=horari', 'r=notes', 'r=butlleti', 'r=uniformes', 'r=economia', 'r=caixa', 'r=junta', 'r=comunicats', 'r=llistats',
          'r=llistats&informe=memoria', 'r=llistats&informe=musics&agrupacio=1', 'r=llistats&informe=antiguitat', 'r=llistats&informe=etiquetes',
          'r=certificat&tipus=soci&id=1', 'r=certificat&tipus=membre&id=1', 'r=certificat&tipus=matricula&id=1', 'r=full_alta', 'r=rgpd',
          'r=configuracio', 'r=moduls', 'r=importa', 'r=genera_rebuts', 'r=ajuda']:
    h = G(q)
    ok(not err(h) and 'class="detall"' not in h, f'{q} ({TIPUS})')
    ok('Músic' not in h and 'Escola de música' not in h and 'Escuela de música' not in h, f'sense vocabulari musical: {q} ({TIPUS})')

# Dades d'exemple pròpies del tipus
ok(n('select count(*) from agrupacions') == 3 and n('select count(*) from assignatures') >= 3, 'seccions i activitats d exemple')
if TIPUS == 'falla':
    ok(n('select count(*) from socis') == 11, 'cens amb els infantils')
    h = G('r=certificat&tipus=soci&id=1'); ok(('cens' in h if ca else 'censo' in h), 'certificat del cens')
if TIPUS == 'club':
    h = G('r=edita&e=membres&id=1'); ok('name="llicencia"' in h and 'name="llicencia_caducitat"' in h, 'llicència federativa al formulari')
    h = G('r=inici'); ok(('Llicències federatives' if ca else 'Licencias federativas') in h, 'avís de llicències a l inici')
    h = G('r=llista&e=membres&filtre=llicencia_caduca'); ok(not err(h) and 'CV-104202' in h, 'filtre de llicències que caduquen')
    h = G('r=fitxa&e=agrupacions&id=1'); ok(('Equip' if ca else 'Equipo') in h, 'fitxa d equip')
else:
    h = G('r=edita&e=membres&id=1'); ok('name="llicencia"' not in h, 'sense llicència fora dels clubs')
if TIPUS == 'penya':
    h = G('r=caixa'); ok(('Desplaçaments i entrades' if ca else 'Desplazamientos y entradas') in h, 'categoria de caixa de la penya')
    h = G('r=uniformes'); ok(('Roba i marxandatge' if ca else 'Ropa y merchandising') in h and 'Bufanda' in h, 'roba i marxandatge de la penya')

# Canviar el tipus a la configuració: canvia el vocabulari, però no els mòduls
h, f = form('r=configuracio')
moduls = n("select valor from config where clau='moduls_desactivats'")
nou = 'club' if TIPUS != 'club' else 'ampa'
f['tipus_entitat'] = nou
P('r=configuracio', {k: v for k, v in f.items() if k not in ('_csrf', 'logo', 'esborra_logo')})
ok(n("select valor from config where clau='tipus_entitat'") == nou, 'tipus canviat a la configuració')
ok(n("select valor from config where clau='moduls_desactivats'") == moduls, 'canviar el tipus no toca els mòduls')
h = G('r=llista&e=agrupacions')
ok((('Equips' if ca else 'Equipos') if nou == 'club' else ('Comissions' if ca else 'Comisiones')) in h, 'vocabulari nou després del canvi')
ok(n("select count(*) from auditoria where accio='tipus_entitat'") == 1, 'el canvi de tipus queda al registre')
