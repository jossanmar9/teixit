# Actualització de la base de dades: només es prepara una vegada per versió, amb una còpia abans
# Copyright (C) 2026 José Vicente Sanjuan
# SPDX-License-Identifier: AGPL-3.0-or-later
# L'executa tests/integracio.py, que hi posa les funcions comunes (G, P, ok, n, db…).

import glob as _glob
versio = n('pragma user_version')
ok(versio > 0, 'la base de dades apunta la versió de l esquema')
copies = lambda: _glob.glob(D + '/copies/*-abans-actualitzar.db')
abans = len(copies())
G('r=inici')
G('r=llista&e=persones')
ok(len(copies()) == abans and n('pragma user_version') == versio, 'sense canvis de versió no es repeteix ni es copia res')
db.execute('pragma user_version = 1')
db.commit()
G('r=inici')
ok(n('pragma user_version') == versio, 'una base de dades d una versió anterior s actualitza')
ok(len(copies()) == abans + 1, 'i abans es fa una còpia «abans d actualitzar»')
h = G('r=copia')
ok(('Abans d' in h and 'actualitzar' in h) or 'Antes de actualizar' in h, 'la còpia apareix a la llista')
