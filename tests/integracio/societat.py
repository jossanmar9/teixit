# Junta, actes, documents, full d'alta, comunicats, RGPD i caixa
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py, que hi posa les funcions comunes (G, P, ok, n, db…).

# Junta i signants
h=G('r=junta'); ok('Junta actual' in h and 'renovar' in h, 'pàgina de la junta amb avís de renovació')
h=G('r=certificat&tipus=soci&id=1'); ok('Tutor1 Exemple' in h and 'Membre1 Exemple' in h, 'certificat signat per la secretaria i amb el vistiplau de la presidència de la junta')
h=G('r=junta_doc'); ok('Vocal d' in h and 'Membre3' in h, 'certificat de composició de la junta')
# Configuració per damunt de la junta
db.execute("insert into config(clau,valor) values('certificat_signant','Secretari Manual') on conflict(clau) do update set valor=excluded.valor"); db.commit()
h=G('r=certificat&tipus=soci&id=1'); ok('Secretari Manual' in h, 'la configuració mana sobre la junta')
db.execute("delete from config where clau='certificat_signant'"); db.commit()
# Reunions
rid=n("select id from reunions where tipus='assemblea_ordinaria'")
h=G(f'r=reunio_doc&id={rid}&doc=acta'); ok(('pressupost' in h.lower() or 'presupuesto' in h.lower()) and '21:15' in h, 'acta amb ordre del dia, acords i hora de fi')
h=G(f'r=reunio_doc&id={rid}&doc=convocatoria'); ok('primera convocat' in h, 'convocatòria amb primera i segona convocatòria')
h=G(f'r=reunio_doc&id={rid}&doc=certificat&acord=3'); ok('60 €' in h, 'certificat del tercer acord')
h=G(f'r=fitxa&e=reunions&id={rid}'); ok('name="acord"' in h, 'selector d acords a la fitxa')
jid=n("select id from reunions where tipus='junta'")
h=G(f'r=reunio_doc&id={jid}&doc=acta'); ok('(Secretari/ària)' in h or 'Secretari' in h, 'acta de junta sense assistents posa la junta actual')
# Documents: pujar, obrir, tipus prohibit, massa gran
h=multipart('r=edita&e=documents', dict(titol='Prova PDF', tipus='conveni', persona_id='', data_document='2026-01-01', data_caducitat='', observacions=''), ('conveni.pdf', b'%PDF-1.4 prova'))
did=n("select max(id) from documents")
ok(n('select fitxer_nom from documents where id=?',did)=='conveni.pdf' and n('select length(contingut) from document_fitxers where document_id=?',did)==14, 'document pujat dins de la base de dades')
r=op.open(B+f'?r=document_fitxer&id={did}'); ok(r.headers['Content-Type']=='application/pdf' and r.read()==b'%PDF-1.4 prova', 'document servit amb el tipus correcte')
h=multipart('r=edita&e=documents', dict(titol='Perillós', tipus='altre_document', persona_id='', data_document='', data_caducitat='', observacions=''), ('virus.html', b'<script>alert(1)</script>'))
ok('class="avis error"' in h and n("select count(*) from documents where titol='Perillós'")==0, 'fitxer .html rebutjat')
# Editar sense fitxer manté el que hi havia
h=multipart(f'r=edita&e=documents&id={did}', dict(titol='Prova PDF 2', tipus='conveni', persona_id='', data_document='2026-01-01', data_caducitat='', observacions=''))
ok(n('select fitxer_nom from documents where id=?',did)=='conveni.pdf' and n('select titol from documents where id=?',did)=='Prova PDF 2', 'editar sense fitxer no l esborra')
P(f'r=elimina&e=documents&id={did}', {}); ok(n('select count(*) from document_fitxers where document_id=?',did)==0, 'eliminar el document esborra el fitxer')
h=G('r=inici'); ok(('caduquen' in h and 'certificat de delictes' in h) or ('caducan' in h and 'certificado de delitos' in h), 'avisos a l inici: documents i certificat')
h=G('r=llista&e=professors&filtre=sense_certificat'); ok('Monitora2' in h and 'Monitora1' not in h, 'filtre de monitors sense certificat')
# Còpia de seguretat inclou documents
# Full d'alta
h=G('r=full_alta'); ok('15,00 €' in h and 'caixeta' in h, 'full d alta amb quotes i IBAN')
# Comunicats
h=G('r=comunicats&a=socis'); m=re.search(r'<textarea id="adreces"[^>]*>([^<]*)</textarea>', h); ok(m and 'membre1@exemple.org' in m.group(1), 'llista de correus dels socis')
ok('Membre5' in h, 'mostra qui no té correu')
nab=n('select count(*) from comunicats'); P('r=comunicats&a=socis', dict(assumpte='Prova', data='2026-10-21', cos='Hola')); ok(n('select count(*) from comunicats')==nab+1, 'comunicat registrat')
h=G('r=comunicats&a=families'); ok('tutor1@exemple.org' in h, 'famílies de l escola')
# RGPD
h=G('r=rgpd'); ok((('Gestió de socis' in h and 'Mesures de seguretat' in h) or ('Gestión de socios' in h and 'Medidas de seguridad' in h)) and 'IBAN' in h, 'registre d activitats de tractament')
# Caixa
h=G('r=caixa'); ok('Remesa' in h and 'Subvenció' in h and 'Saldo inicial' in h, 'llibre de caixa amb remesa, moviments i saldo inicial')
csv=op.open(B+'?r=caixa&csv=1').read().decode('utf-8-sig'); ok(csv.count('\n')>3, 'CSV del llibre de caixa')
h=P('r=edita&e=moviments', dict(data='2026-10-21', tipus='despesa', compte='caixa', categoria='material', concepte='Cordes', import_='', justificant='', observacions='') | {'import':'-5'})
ok('class="avis error"' in h, 'import negatiu rebutjat')
# Tancar junta
P('r=junta&accio=tanca', dict(data='2026-10-21'))
ok(n("select count(*) from carrecs where data_fi is null")==0, 'tancar la junta')
