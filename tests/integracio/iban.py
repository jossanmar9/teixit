# IBAN a les fitxes de persona i soci, i importació de comptes
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py, que hi posa les funcions comunes (G, P, ok, n, db…).

# Persona adulta sense domiciliació: Membre4
pid=n("select id from persones where nom='Membre4'")
h,f=form(f'r=edita&e=persones&id={pid}'); ok('name="iban"' in h, 'camp IBAN a la fitxa de persona')
f['iban']='2100 0418 4502 0005 1332'   # CCC de 20 xifres
h=envia(f'r=edita&e=persones&id={pid}', f)
ok(n("select count(*) from mandats where persona_id=? and estat='actiu'",pid)==1, 'CCC convertit i domiciliació creada')
ok(n("select iban from mandats where persona_id=? and estat='actiu'",pid)=='ES9121000418450200051332', 'IBAN calculat del CCC')
h=op.open(B+f'?r=fitxa&e=persones&id={pid}').read().decode(); ok('ES91 2100 0418 4502 0005 1332' in h, 'IBAN a la fitxa')
h,f=form(f'r=edita&e=persones&id={pid}'); ok('ES9121000418450200051332' in h, 'el formulari mostra l IBAN')
f['iban']='DE89 3704 0044 0532 0130 00'; envia(f'r=edita&e=persones&id={pid}', f)
ok(n("select count(*) from mandats where persona_id=?",pid)==2 and n("select iban from mandats where persona_id=? and estat='actiu'",pid).startswith('DE89'), 'canvi d IBAN: nova domiciliació i l anterior de baixa')
h,f=form(f'r=edita&e=persones&id={pid}'); envia(f'r=edita&e=persones&id={pid}', f)
ok(n("select count(*) from mandats where persona_id=?",pid)==2, 'desar sense canviar no crea res')
f['iban']=''; envia(f'r=edita&e=persones&id={pid}', f)
ok(n("select count(*) from mandats where persona_id=? and estat='actiu'",pid)==0, 'esborrar l IBAN dona de baixa')
# Menor
mid=n("select id from persones where nom='Alumne1'")
h,f=form(f'r=edita&e=persones&id={mid}'); f['iban']='ES9121000418450200051332'; h=envia(f'r=edita&e=persones&id={mid}', f)
ok('class="avis error"' in h and n("select count(*) from mandats where persona_id=?",mid)==0, 'menor: error i sense domiciliació')
# IBAN incorrecte
h,f=form(f'r=edita&e=persones&id={pid}'); f['iban']='ES00 1111'; h=envia(f'r=edita&e=persones&id={pid}', f)
ok('class="avis error"' in h, 'IBAN incorrecte rebutjat')
# Fitxa de soci
sid=n("select id from socis where persona_id=?",pid)
h,f=form(f'r=edita&e=socis&id={sid}'); ok('name="iban"' in h, 'camp IBAN a la fitxa de soci')
f['iban']='ES9121000418450200051332'; envia(f'r=edita&e=socis&id={sid}', f)
ok(n("select count(*) from mandats where persona_id=? and estat='actiu'",pid)==1, 'IBAN des de la fitxa de soci')
# Nova persona amb IBAN
h,f=form('r=edita&e=persones'); f.update(nom='Nova',cognoms='Persona',data_naixement='1980-01-01',document='99999999R',telefon='600',iban='ES9121000418450200051332')
envia('r=edita&e=persones', f)
np=n("select id from persones where nom='Nova'"); ok(np and n("select count(*) from mandats where persona_id=?",np)==1, 'persona nova amb IBAN')
# CSV de persones
h=op.open(B+'?r=llista&e=persones&csv=1').read().decode('utf-8-sig'); ok('IBAN' not in h.split('\n')[0], 'el CSV de persones no porta columna virtual')
