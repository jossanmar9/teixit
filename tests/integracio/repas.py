# Repàs de totes les pantalles: llistats, CSV, fitxes, formularis i documents sense errors de PHP
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py, que hi posa les funcions comunes (G, P, ok, n, db…).

entitats = ['tipus_socis','uniformes','uniforme_compres','uniforme_lliuraments','carrecs','reunions','documents','comunicats','moviments','persones','socis','alumnes','professors','tutories','agrupacions','membres','cursos','periodes','assignatures','aules','grups','horaris','matricules','matricula_grups','instruments','manteniments','cessions','tarifes','mandats','rebuts','rebut_linies','remeses','loteries','talonaris']
for e in entitats:
    for q in [f'r=llista&e={e}', f'r=llista&e={e}&csv=1', f'r=fitxa&e={e}&id=1', f'r=edita&e={e}', f'r=edita&e={e}&id=1']:
        h = G(q)
        ok(not h.startswith('HTTP') and 'class="detall"' not in h and not err(h), q)
pagines = ['r=ajuda&doc=llicencia','r=ajuda','r=fitxa&e=agrupacions&id=1','r=importa&plantilla=alumnes','r=uniformes','r=moduls','r=llistats&informe=memoria','r=llistats&informe=socis','r=junta','r=junta_doc','r=reunio_doc&id=1&doc=acta','r=reunio_doc&id=1&doc=convocatoria','r=reunio_doc&id=1&doc=certificat&acord=2','r=full_alta','r=comunicats','r=comunicats&a=families','r=comunicats&a=agrupacio:1','r=rgpd','r=caixa','r=caixa&compte=caixa','r=document_fitxer&id=1','r=inici','r=llistats','r=horari','r=notes','r=butlleti','r=importa','r=usuaris','r=configuracio','r=auditoria','r=copia','r=ajuda','r=perfil','r=llistats&informe=memoria','r=llistats&informe=etiquetes']
for q in pagines:
    h = G(q)
    ok(not h.startswith('HTTP') and 'class="detall"' not in h and not err(h), q)
