# Avís de responsabilitat: s'ha d'acceptar en la configuració inicial i s'avisa una vegada en les instal·lacions antigues
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py sense instal·lar Teixit (instal·la=False).

txt = {'ca': dict(titol='Avís de responsabilitat', cal='cal acceptar', peu='no es fa responsable', acceptat='Acceptat el'),
       'es': dict(titol='Aviso de responsabilidad', cal='hay que aceptar', peu='no se hace responsable', acceptat='Aceptado el')}[L]
h = G('r=idioma&l=' + L + '&tornar=/')
ok(txt['titol'] in h and 'name="accepta_avis"' in h, 'avís a la configuració inicial')
dades = dict(tipus_entitat='club', societat_nom='Prova', idioma=L, usuari='admin', contrasenya='contrasenya-llarga1', contrasenya2='contrasenya-llarga1',
             usuari2='', contrasenya_b='', contrasenya_b2='')
h = P('', dades)
ok(n('select count(*) from usuaris') == 0 and txt['cal'] in h, 'sense acceptar no s instal·la')
ok('name="tipus_entitat"' in h and 'value="club" required checked' in h, 'el tipus triat es conserva si hi ha un error')
h = P('', dict(dades, tipus_entitat='', accepta_avis='1'))
ok(n('select count(*) from usuaris') == 0 and ("Tria el tipus d'entitat" in h or 'Tria el tipus d&#039;entitat' in h or 'Elige el tipo de entidad' in h), 'sense tipus d entitat no s instal·la')
P('', dict(dades, accepta_avis='1'))
ok(n('select count(*) from usuaris') == 1 and n("select valor from config where clau='avis_responsabilitat'").endswith('|admin'), 'acceptat i apuntat')
ok(n("select valor from config where clau='tipus_entitat'") == 'club' and n('select count(*) from tipus_socis') == 4, 'instal·lat com a club, amb els seus tipus de soci')
h = G('r=inici')
ok('avis_accepta' not in h and txt['peu'] in h, 'inici sense caixa, amb el peu')
db.execute("delete from config where clau='avis_responsabilitat'")
db.commit()
ok('avis_accepta' in G('r=inici'), 'instal·lació antiga: avís a l inici')
P('r=avis_accepta', {})
ok(n("select valor from config where clau='avis_responsabilitat'") is not None and 'avis_accepta' not in G('r=inici'), 'Entesos l amaga')
ok(txt['acceptat'] in G('r=ajuda') and txt['titol'] in G('r=configuracio') and txt['peu'] in G('r=copia'), 'ajuda, configuració i còpies')
