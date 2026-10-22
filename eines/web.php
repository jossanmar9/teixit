<?php
// Genera la web de presentació de Teixit (GitHub Pages) en valencià i castellà.
// Ús: php eines/web.php carpeta-eixida
// Copia el logotip, unes quantes captures (docs/imatges) i els PDF (docs/pdf, si s'han generat amb eines/pdf.sh)
// Copyright (C) 2026 José Vicente Sanjuan
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$arrel = dirname(__DIR__);
$eixida = rtrim($argv[1] ?? $arrel . '/_site', '/');
// Si es canvia el compte de GitHub, només cal canviar estes línies
$repo = 'https://github.com/jossanmar9/teixit';
$versio = getenv('TEIXIT_VERSIO') ?: (preg_match("/TEIXIT_VERSIO = '([^']+)'/", file_get_contents($arrel . '/src/bootstrap.php'), $m) ? $m[1] : '');

$captures = ['web-inici', 'web-socis', 'web-junta', 'web-acta', 'web-rebuts', 'web-economia', 'web-caixa', 'web-loteria', 'web-seccio', 'web-horari', 'web-copies'];

$T = [
    'ca' => [
        'lang' => 'ca-valencia', 'altre' => 'es', 'altre_nom' => 'Castellano', 'altre_url' => 'es/',
        'titol' => 'Teixit: gestió lliure per a associacions',
        'descripcio' => "Programa lliure i gratuït per a la gestió interna d'associacions culturals, falles, clubs esportius, AMPA i penyes des d'un ordinador amb Windows o Linux: socis, junta i actes, quotes, rebuts i remeses, loteria, caixa, documents, seccions i activitats.",
        'lema' => 'Gestió lliure per a associacions',
        'hero' => 'Tota l\'associació, en un sol programa.',
        'hero_text' => "Socis, junta i actes, quotes i rebuts, caixa, documents i activitats, en un ordinador de l'entitat. Per a associacions culturals, falles, clubs esportius, AMPA i penyes. Sense núvol, sense Internet i sense recollir cap dada. Gratuït i en valencià i castellà.",
        'nav_priv' => 'Privacitat',
        'priv_titol' => 'La privacitat, al centre',
        'priv_intro' => 'Teixit està fet al revés que les plataformes en línia: les dades de les persones de l\'entitat (també les dels menors) no ixen mai de l\'ordinador de l\'entitat.',
        'priv_punts' => [
            ['Sense núvol', 'No hi ha cap servidor. El programa i les dades estan en un ordinador de l\'entitat, i ningú no hi pot entrar des d\'Internet.'],
            ['No recull cap dada', 'Sense comptes en cap plataforma, sense analítica, sense telemetria i sense publicitat. Ni el programa ni esta web envien res enlloc.'],
            ['Funciona sense Internet', 'No cal connexió per a res. Les remeses del banc i els correus es preparen ací i els envieu vosaltres.'],
            ['Les dades són vostres', 'Programari lliure i gratuït, sense quotes ni límit de socis. Si un dia el deixeu de fer servir, les dades continuen a la vostra carpeta.'],
        ],
        'comp_titol' => 'Teixit i les plataformes en línia',
        'comp_cols' => ['', 'Teixit', 'Plataformes en línia'],
        'comp' => [
            ['On estan les dades', 'A l\'ordinador de l\'entitat', 'Als servidors de l\'empresa'],
            ['Recollida de dades', 'Cap', 'Comptes d\'usuari i dades d\'ús'],
            ['Internet', 'No cal', 'Imprescindible'],
            ['Preu', 'Gratuït, sense límit de socis', 'Normalment, segons el nombre de socis'],
            ['Si el servei tanca o canvia de preu', 'El programa i les dades continuen sent vostres', 'Depeneu de l\'empresa'],
            ['Aplicació per al mòbil dels socis', 'No: ho porta la secretaria, que comparteix PDF i llistes', 'Sí, cada soci amb el seu compte'],
        ],
        'descarrega' => 'Descarrega (Windows i Linux)',
        'versio' => 'Versió %s. Gratuït i lliure.',
        'manual' => 'Llig el manual',
        'nav_que' => 'Què fa', 'nav_veure' => 'Captures', 'nav_com' => 'Com començar', 'nav_qui' => 'Per a qui', 'nav_codi' => 'Codi',
        'tipus_titol' => 'Un programa, cinc tipus d\'entitat',
        'tipus_intro' => 'En instal·lar-lo, tries quin tipus d\'entitat és la teua. Teixit adapta el vocabulari, els apartats que s\'activen i els tipus de soci.',
        'tipus' => [
            ['Associació cultural', 'Culturals, veïnals, juvenils, de gent gran, colles… Socis, seccions (teatre, ball…) i tallers i cursos.'],
            ['Falla, gaiata o foguera', 'El cens per categories, seccions, brusons i mocadors, la loteria i les despeses del monument i del casal.'],
            ['Club esportiu', 'Equips per categories, esportistes i tècnics, llicències federatives amb avís de caducitat, equipacions i escola esportiva.'],
            ['AMPA o AFA', 'Famílies associades, comissions (menjador, festes…) i activitats extraescolars amb rebuts per família.'],
            ['Penya d\'un club', 'Penyes d\'aficionats de futbol o d\'un altre esport: socis, comissions, loteria, samarretes i bufandes, i els desplaçaments.'],
        ],
        'qui_titol' => 'D\'on ve Teixit',
        'qui' => [
            'Teixit naix de Compàs, el programa lliure per a societats musicals que vaig fer després de sis anys com a president de la Societat Musical de Massalfassar.',
            'Molta de la feina d\'una societat musical és la mateixa que la de qualsevol associació: els socis i les quotes, la junta i les actes, els rebuts i el banc, la loteria, els documents i les còpies de seguretat. Teixit agafa eixe nucli comú i l\'adapta a altres entitats que també viuen amb els comptes justos i amb gent voluntària al capdavant.',
            'No és perfecte, però crec que és útil. Proveu-lo i, si vos agrada, gasteu-lo i parleu-ne amb altres associacions, falles, clubs, AMPA o penyes.',
        ],
        'qui_signa' => 'José Vicente Sanjuan',
        'qui_contacte' => 'Teniu dubtes o propostes? Obriu una incidència a GitHub: hi ha un formulari senzill que vos guia pas a pas.',
        'qui_lema' => 'Del mateix autor que Compàs, el programa per a societats musicals.',
        'qui_llig' => 'D\'on ve',
        'germa' => 'Sou una societat musical, una banda o una escola de música? Per a vosaltres hi ha',
        'germa_nom' => 'Compàs', 'germa_url' => 'https://jossanmar9.github.io/compas/',
        'que_titol' => 'Què pots fer amb Teixit',
        'que_intro' => 'Pensat per a qui porta la secretaria o la tresoreria, encara que no siga informàtic.',
        'moduls' => [
            ['Persones', 'Cada persona s\'introdueix una sola vegada. Pot ser sòcia, membre d\'una secció, monitora i mare d\'un alumne alhora, sense repetir dades.'],
            ['Socis o cens', 'Número, els tipus de soci de cada entitat, altes i baixes, full d\'alta. Etiquetes per a les circulars i la llista de qui fa 25 o 50 anys.'],
            ['Junta i actes', 'Càrrecs amb historial i avís de renovació. Convocatòries, actes i certificats d\'acords a punt per a signar.'],
            ['Quotes de soci', 'Tarifes per tipus de soci i periodicitat. Els rebuts de tot un trimestre, fets amb un clic i sense duplicar-ne cap.'],
            ['Remeses bancàries', 'Domiciliacions amb l\'ordre per a signar i el fitxer SEPA per a pujar al banc. Control dels rebuts retornats.'],
            ['Llibre de caixa', 'Ingressos i despeses amb saldo. Els rebuts, les remeses i la loteria hi entren sols.'],
            ['Loteria', 'Talonaris repartits, participacions per a imprimir i qui ha liquidat i qui no.'],
            ['Documents', 'Estatuts, reglament, assegurances i contractes guardats a Teixit, amb avís abans que caduquen. Registre RGPD.'],
            ['Comunicats', 'Llistes de correu de socis, junta, famílies o una secció per a enviar en còpia oculta, i registre del que s\'ha enviat.'],
            ['Seccions o equips', 'Els grups de l\'entitat i els seus membres. En els clubs, equips per categoria i llicències federatives.'],
            ['Activitats i cursos', 'Tallers, extraescolars o escola esportiva: grups amb horari, monitors, matrícules i un rebut per família.'],
            ['Inventari i roba', 'El material i els préstecs, i la roba per talla: el que s\'ha comprat, venut o deixat i el que queda.'],
            ['Drets d\'imatge', 'Qui autoritza fotos i vídeos, amb els fulls de consentiment preparats per a signar.'],
            ['Llistats i certificats', 'Socis per número, dades per a la memòria anual i les subvencions, i certificats amb el logotip.'],
            ['Importació des d\'Excel', 'Porta els socis i l\'alumnat que ja tens en Excel, sense duplicar res.'],
            ['Còpies de seguretat', 'Còpia automàtica cada dia i en tancar, també en un USB o en el núvol, xifrada amb contrasenya si vols.'],
        ],
        'veure_titol' => 'Així es veu',
        'veure_intro' => 'Captures reals del programa amb les dades d\'exemple d\'una associació cultural.',
        'captures' => [
            'web-inici' => 'La pantalla d\'inici: el resum de l\'entitat, els avisos i les activitats de hui.',
            'web-socis' => 'El llistat de socis, amb el número i el tipus de cadascú.',
            'web-junta' => 'La junta directiva actual i les anteriors, amb l\'avís de renovació.',
            'web-acta' => 'L\'acta d\'una assemblea, preparada per a imprimir i signar.',
            'web-rebuts' => 'Els rebuts de les quotes d\'un trimestre, abans de crear-los.',
            'web-economia' => 'El resum econòmic: el que hi ha per cobrar, el cobrat i qui deu diners.',
            'web-caixa' => 'El llibre de caixa, amb el saldo i el resum per categories per a l\'assemblea.',
            'web-loteria' => 'La loteria de Nadal: talonaris repartits, venudes, tornades i diners lliurats.',
            'web-seccio' => 'Una secció amb els seus membres i la funció de cadascú.',
            'web-horari' => 'L\'horari dels tallers i cursos, que es pot filtrar per monitor o per aula.',
            'web-copies' => 'Les còpies de seguretat: automàtiques, manuals i en una carpeta externa.',
        ],
        'com_titol' => 'Com començar',
        'passos' => [
            ['Descarrega l\'instal·lador', 'Des de la pàgina de descàrregues, baixa <code>Teixit-…-installador.exe</code>. També hi ha una versió portable que no cal instal·lar i paquets per a Linux (<code>.deb</code> per a Debian i Ubuntu, i <code>.tar.gz</code>).'],
            ['Instal·la\'l', 'No demana permisos d\'administrador. Com que encara no està signat, Windows pot avisar: llig el quadre de baix.'],
            ['Comença', 'Tria el tipus d\'entitat, escriu-ne el nom i crea els comptes d\'administrador/a i d\'usuari/ària. Si vols, carrega dades d\'exemple per a provar-lo.'],
        ],
        'dades_titol' => 'Les dades es queden a l\'entitat',
        'dades' => [
            'Teixit funciona en un ordinador de l\'entitat i no envia res a Internet.',
            'Les dades de socis, famílies i menors no passen per cap servidor de fora.',
            'Té dos comptes: administrador/a i usuari/ària, i un registre de qui ha fet cada canvi.',
            'Esta web tampoc no té galetes ni analítica, i les tipografies (lliures) se serveixen des d\'ací mateix.',
        ],
        'avis_titol' => 'Si Windows no et deixa obrir-lo',
        'avis' => [
            ['«Windows ha protegit l\'ordinador»', 'És només un avís perquè el programa encara no està signat digitalment. Prem «Més informació» i «Executa igualment».'],
            ['«Una directiva de Control d\'aplicacions ha bloquejat aquest fitxer»', 'És el Control intel·ligent d\'aplicacions d\'alguns ordinadors nous amb Windows 11, que bloqueja tots els programes no signats, també la versió portable. Cal desactivar-lo a Seguretat de Windows o utilitzar un altre ordinador o la versió per a Linux.'],
        ],
        'avis_mes' => 'Explicació pas a pas a la guia d\'instal·lació',
        'req_titol' => 'Què cal',
        'req' => ['Un ordinador amb Windows 10 o 11 de 64 bits, o amb Linux i PHP 8.1 o posterior', 'Uns 100 MB de disc', 'Un llapis USB o una carpeta al núvol per a les còpies (recomanat)'],
        'nav_faq' => 'Preguntes',
        'faq_titol' => 'Preguntes freqüents',
        'faq_intro' => 'El que més es pregunta abans de provar-lo. Si teniu un altre dubte, obriu una incidència a GitHub.',
        'comptes_titol' => 'Qui pot fer què',
        'comptes_intro' => 'Teixit té dos comptes: el de l\'administrador/a (normalment, la presidència o qui porta l\'informàtica) i el d\'usuari/ària (la secretaria o la tresoreria). Els dos fan tota la feina del dia a dia; només l\'administrador/a pot tocar la configuració, els comptes i la restauració de còpies.',
        'comptes_cols' => ['Què es pot fer', 'Administrador/a', 'Usuari/ària'],
        'comptes' => [
            ['Persones, socis i tipus de soci', true],
            ['Seccions o equips, activitats i cursos, monitors i matrícules', true],
            ['Inventari, préstecs i roba', true],
            ['Quotes, rebuts, domiciliacions, remeses i loteria', true],
            ['Llibre de caixa', true],
            ['Junta, actes, documents i comunicats', true],
            ['Llistats, certificats i importació des d\'Excel', true],
            ['Fer i baixar còpies de seguretat', true],
            ['Canviar la pròpia contrasenya', true],
            ['Configuració de l\'entitat, tipus d\'entitat, logotip, mòduls i avisos', false],
            ['Dades bancàries i descomptes de les activitats', false],
            ['Crear i gestionar els comptes', false],
            ['Registre d\'activitat', false],
            ['Restaurar i eliminar còpies, carpeta externa i xifrat', false],
            ['Recuperar l\'accés amb el codi de recuperació', false],
        ],
        'faq' => [
            ['És gratis de veres?', 'Sí. És programari lliure: sense quotes, sense límit de socis i sense versió «de pagament». No hi ha res a contractar.'],
            ['Cal Internet?', 'No. Teixit funciona sense connexió. Els fitxers de remeses per al banc i els correus es preparen al programa i els envieu vosaltres des de la banca electrònica o el vostre correu.'],
            ['Teixit envia les nostres dades a algun lloc?', 'No. Les dades es queden a l\'ordinador de l\'entitat i, si la configureu, a la carpeta externa de còpies (un USB o una carpeta al núvol que trieu vosaltres). Ni el programa ni esta web recullen res.'],
            ['Serveix per a la nostra entitat?', 'Si sou una associació cultural, una falla (o gaiata o foguera), un club esportiu, una AMPA o una penya, sí: en instal·lar-lo trieu el tipus i Teixit s\'hi adapta. Altres entitats semblants poden triar «associació cultural». Si sou una societat musical, mireu Compàs.'],
            ['Funciona en Windows i en Linux?', 'Sí. Per a Windows 10 o 11 de 64 bits hi ha un instal·lador i una versió portable; per a Linux, un paquet .deb i un .tar.gz. No hi ha versió per a Mac ni per al mòbil.'],
            ['Es pot gastar des de diversos ordinadors alhora?', 'No: Teixit s\'instal·la en un ordinador de l\'entitat i s\'usa en eixe ordinador. Per a canviar d\'ordinador, n\'hi ha prou de restaurar l\'última còpia al nou.'],
            ['Puc provar-lo sense posar les dades de l\'entitat?', 'Sí. En instal·lar-lo, podeu demanar que es carreguen dades d\'exemple del vostre tipus d\'entitat: socis, seccions, activitats, rebuts… inventats. Quan el conegueu, comenceu de zero amb les dades de veres.'],
            ['Tenim els socis en un Excel. Cal escriure\'ls tots?', 'No. Importa dades llig fitxers .xlsx i .csv: trieu quina columna és cada dada, reviseu la vista prèvia i s\'importen. Les persones que ja hi ha no es dupliquen.'],
            ['Teixit fa factures?', 'No, fa rebuts. Les factures s\'han de fer amb un programa de facturació que complisca la normativa (Verifactu).'],
            ['I si s\'espatlla l\'ordinador?', 'Per això hi ha les còpies de seguretat. Teixit en fa una cada vegada que es tanca, i convé configurar una carpeta externa perquè també es guarden fora de l\'ordinador. Amb l\'última còpia, ho recupereu tot en un altre ordinador.'],
            ['Com s\'actualitza?', 'A Ajuda, el botó «Hi ha versió nova?» obri la pàgina de l\'última versió. Si n\'hi ha una de nova, s\'instal·la damunt de l\'anterior i les dades es conserven; abans d\'actualitzar, Teixit en fa una còpia.'],
        ],
        'docs_titol' => 'Manuals',
        'docs' => [['manual-usuari.pdf', 'Manual d\'usuari, pas a pas amb captures'], ['manual-administracio.pdf', 'Manual d\'administració: configuració, comptes, còpies i actualitzacions'], ['guia-installacio.pdf', 'Guia d\'instal·lació']],
        'lliure_titol' => 'Programari lliure',
        'lliure' => 'Teixit es distribueix sota la llicència GNU AGPL v3 o posterior: el pots utilitzar, estudiar, modificar i compartir. Les versions modificades que s’oferisquen a través d’una xarxa també han de posar el seu codi font a disposició dels usuaris. El codi és públic a GitHub.',
        'autor' => 'Un projecte de José Vicente Sanjuan.',
        'ia' => 'S\'ha desenvolupat amb l\'ajuda d\'una intel·ligència artificial (Claude, d\'Anthropic).',
        'codi' => 'Codi a GitHub',
        'novetats' => 'Què canvia en cada versió',
        'privacitat' => 'Privacitat',
        'llicencia' => 'Resum de la llicència',
        'errades' => 'Has trobat una errada o tens una proposta? Obri una incidència a GitHub.',
        'contacte' => 'Contacte',
        'fi' => 'Fet per a les associacions.',
        'resp_titol' => 'Avís de responsabilitat',
        'resp' => 'Teixit és programari lliure i es distribueix tal com és, sense cap garantia (llicència AGPL-3.0). L\'autor no es fa responsable de la pèrdua de dades ni de cap altre dany, directe o indirecte, que puga derivar-se de l\'ús del programa, d\'una errada, d\'una actualització o d\'una configuració incorrecta. Cada entitat és responsable de les seues dades i de fer-ne còpies de seguretat periòdiques en un lloc extern (USB o núvol).',
        'anterior' => 'Anterior', 'seguent' => 'Següent',
    ],
    'es' => [
        'lang' => 'es', 'altre' => 'ca', 'altre_nom' => 'Valencià', 'altre_url' => '../',
        'titol' => 'Teixit: gestión libre para asociaciones',
        'descripcio' => 'Programa libre y gratuito para la gestión interna de asociaciones culturales, fallas, clubes deportivos, AMPA y peñas desde un ordenador con Windows o Linux: socios, junta y actas, cuotas, recibos y remesas, lotería, caja, documentos, secciones y actividades.',
        'lema' => 'Gestión libre para asociaciones',
        'hero' => 'Toda la asociación, en un solo programa.',
        'hero_text' => 'Socios, junta y actas, cuotas y recibos, caja, documentos y actividades, en un ordenador de la entidad. Para asociaciones culturales, fallas, clubes deportivos, AMPA y peñas. Sin nube, sin Internet y sin recopilar ningún dato. Gratuito y en valenciano y castellano.',
        'nav_priv' => 'Privacidad',
        'priv_titol' => 'La privacidad, en el centro',
        'priv_intro' => 'Teixit está hecho al revés que las plataformas en línea: los datos de las personas de la entidad (también los de los menores) no salen nunca del ordenador de la entidad.',
        'priv_punts' => [
            ['Sin nube', 'No hay ningún servidor. El programa y los datos están en un ordenador de la entidad, y nadie puede entrar desde Internet.'],
            ['No recopila ningún dato', 'Sin cuentas en ninguna plataforma, sin analítica, sin telemetría y sin publicidad. Ni el programa ni esta web envían nada a ningún sitio.'],
            ['Funciona sin Internet', 'No hace falta conexión para nada. Las remesas del banco y los correos se preparan aquí y los enviáis vosotros.'],
            ['Los datos son vuestros', 'Software libre y gratuito, sin cuotas ni límite de socios. Si un día lo dejáis de usar, los datos siguen en vuestra carpeta.'],
        ],
        'comp_titol' => 'Teixit y las plataformas en línea',
        'comp_cols' => ['', 'Teixit', 'Plataformas en línea'],
        'comp' => [
            ['Dónde están los datos', 'En el ordenador de la entidad', 'En los servidores de la empresa'],
            ['Recopilación de datos', 'Ninguna', 'Cuentas de usuario y datos de uso'],
            ['Internet', 'No hace falta', 'Imprescindible'],
            ['Precio', 'Gratuito, sin límite de socios', 'Normalmente, según el número de socios'],
            ['Si el servicio cierra o cambia de precio', 'El programa y los datos siguen siendo vuestros', 'Dependéis de la empresa'],
            ['Aplicación para el móvil de los socios', 'No: lo lleva la secretaría, que comparte PDF y listas', 'Sí, cada socio con su cuenta'],
        ],
        'descarrega' => 'Descargar (Windows y Linux)',
        'versio' => 'Versión %s. Gratuito y libre.',
        'manual' => 'Leer el manual',
        'nav_que' => 'Qué hace', 'nav_veure' => 'Capturas', 'nav_com' => 'Cómo empezar', 'nav_qui' => 'Para quién', 'nav_codi' => 'Código',
        'tipus_titol' => 'Un programa, cinco tipos de entidad',
        'tipus_intro' => 'Al instalarlo, eliges qué tipo de entidad es la tuya. Teixit adapta el vocabulario, los apartados que se activan y los tipos de socio.',
        'tipus' => [
            ['Asociación cultural', 'Culturales, vecinales, juveniles, de mayores, peñas… Socios, secciones (teatro, baile…) y talleres y cursos.'],
            ['Falla, gaiata u hoguera', 'El censo por categorías, secciones, blusones y pañuelos, la lotería y los gastos del monumento y del casal.'],
            ['Club deportivo', 'Equipos por categorías, deportistas y técnicos, licencias federativas con aviso de caducidad, equipaciones y escuela deportiva.'],
            ['AMPA o AFA', 'Familias asociadas, comisiones (comedor, fiestas…) y actividades extraescolares con recibos por familia.'],
            ['Peña de un club', 'Peñas de aficionados de fútbol o de otro deporte: socios, comisiones, lotería, camisetas y bufandas, y los desplazamientos.'],
        ],
        'qui_titol' => 'De dónde viene Teixit',
        'qui' => [
            'Teixit nace de Compàs, el programa libre para sociedades musicales que hice después de seis años como presidente de la Societat Musical de Massalfassar.',
            'Mucho del trabajo de una sociedad musical es el mismo que el de cualquier asociación: los socios y las cuotas, la junta y las actas, los recibos y el banco, la lotería, los documentos y las copias de seguridad. Teixit toma ese núcleo común y lo adapta a otras entidades que también viven con las cuentas justas y con gente voluntaria al frente.',
            'No es perfecto, pero creo que es útil. Probadlo y, si os gusta, usadlo y habladlo con otras asociaciones, fallas, clubes, AMPA o peñas.',
        ],
        'qui_signa' => 'José Vicente Sanjuan',
        'qui_contacte' => '¿Tenéis dudas o propuestas? Abrid una incidencia en GitHub: hay un formulario sencillo que os guía paso a paso.',
        'qui_lema' => 'Del mismo autor que Compàs, el programa para sociedades musicales.',
        'qui_llig' => 'De dónde viene',
        'germa' => '¿Sois una sociedad musical, una banda o una escuela de música? Para vosotros está',
        'germa_nom' => 'Compàs', 'germa_url' => 'https://jossanmar9.github.io/compas/es/',
        'que_titol' => 'Qué puedes hacer con Teixit',
        'que_intro' => 'Pensado para quien lleva la secretaría o la tesorería, aunque no sea informático.',
        'moduls' => [
            ['Personas', 'Cada persona se introduce una sola vez. Puede ser socia, miembro de una sección, monitora y madre de un alumno a la vez, sin repetir datos.'],
            ['Socios o censo', 'Número, los tipos de socio de cada entidad, altas y bajas, hoja de alta. Etiquetas para las circulares y la lista de quién cumple 25 o 50 años.'],
            ['Junta y actas', 'Cargos con historial y aviso de renovación. Convocatorias, actas y certificados de acuerdos listos para firmar.'],
            ['Cuotas de socio', 'Tarifas por tipo de socio y periodicidad. Los recibos de todo un trimestre, hechos con un clic y sin duplicar ninguno.'],
            ['Remesas bancarias', 'Domiciliaciones con la orden para firmar y el archivo SEPA para subir al banco. Control de los recibos devueltos.'],
            ['Libro de caja', 'Ingresos y gastos con saldo. Los recibos, las remesas y la lotería entran solos.'],
            ['Lotería', 'Talonarios repartidos, participaciones para imprimir y quién ha liquidado y quién no.'],
            ['Documentos', 'Estatutos, reglamento, seguros y contratos guardados en Teixit, con aviso antes de que caduquen. Registro RGPD.'],
            ['Comunicados', 'Listas de correo de socios, junta, familias o una sección para enviar en copia oculta, y registro de lo que se ha enviado.'],
            ['Secciones o equipos', 'Los grupos de la entidad y sus miembros. En los clubes, equipos por categoría y licencias federativas.'],
            ['Actividades y cursos', 'Talleres, extraescolares o escuela deportiva: grupos con horario, monitores, matrículas y un recibo por familia.'],
            ['Inventario y ropa', 'El material y los préstamos, y la ropa por talla: lo que se ha comprado, vendido o prestado y lo que queda.'],
            ['Derechos de imagen', 'Quién autoriza fotos y vídeos, con las hojas de consentimiento preparadas para firmar.'],
            ['Listados y certificados', 'Socios por número, datos para la memoria anual y las subvenciones, y certificados con el logotipo.'],
            ['Importación desde Excel', 'Trae los socios y el alumnado que ya tienes en Excel, sin duplicar a nadie.'],
            ['Copias de seguridad', 'Copia automática cada día y al cerrar, también en un USB o en la nube, cifrada con contraseña si quieres.'],
        ],
        'veure_titol' => 'Así se ve',
        'veure_intro' => 'Capturas reales del programa con los datos de ejemplo de una asociación cultural.',
        'captures' => [
            'web-inici' => 'La pantalla de inicio: el resumen de la entidad, los avisos y las actividades de hoy.',
            'web-socis' => 'El listado de socios, con el número y el tipo de cada uno.',
            'web-junta' => 'La junta directiva actual y las anteriores, con el aviso de renovación.',
            'web-acta' => 'El acta de una asamblea, preparada para imprimir y firmar.',
            'web-rebuts' => 'Los recibos de las cuotas de un trimestre, antes de crearlos.',
            'web-economia' => 'El resumen económico: lo que hay por cobrar, lo cobrado y quién debe dinero.',
            'web-caixa' => 'El libro de caja, con el saldo y el resumen por categorías para la asamblea.',
            'web-loteria' => 'La lotería de Navidad: talonarios repartidos, vendidas, devueltas y dinero entregado.',
            'web-seccio' => 'Una sección con sus miembros y la función de cada uno.',
            'web-horari' => 'El horario de los talleres y cursos, que se puede filtrar por monitor o por aula.',
            'web-copies' => 'Las copias de seguridad: automáticas, manuales y en una carpeta externa.',
        ],
        'com_titol' => 'Cómo empezar',
        'passos' => [
            ['Descarga el instalador', 'Desde la página de descargas, baja <code>Teixit-…-installador.exe</code>. También hay una versión portable que no hace falta instalar y paquetes para Linux (<code>.deb</code> para Debian y Ubuntu, y <code>.tar.gz</code>).'],
            ['Instálalo', 'No pide permisos de administrador. Como todavía no está firmado, Windows puede avisar: lee el cuadro de abajo.'],
            ['Empieza', 'Elige el tipo de entidad, escribe su nombre y crea las cuentas de administrador/a y de usuario/a. Si quieres, carga datos de ejemplo para probarlo.'],
        ],
        'dades_titol' => 'Los datos se quedan en la entidad',
        'dades' => [
            'Teixit funciona en un ordenador de la entidad y no envía nada a Internet.',
            'Los datos de socios, familias y menores no pasan por ningún servidor de fuera.',
            'Tiene dos cuentas: administrador/a y usuario/a, y un registro de quién ha hecho cada cambio.',
            'Esta web tampoco tiene cookies ni analítica, y las tipografías (libres) se sirven desde aquí mismo.',
        ],
        'avis_titol' => 'Si Windows no te deja abrirlo',
        'avis' => [
            ['«Windows protegió su PC»', 'Es solo un aviso porque el programa todavía no está firmado digitalmente. Pulsa «Más información» y «Ejecutar de todas formas».'],
            ['«Una directiva de Control de aplicaciones bloqueó este archivo»', 'Es el Control inteligente de aplicaciones de algunos ordenadores nuevos con Windows 11, que bloquea todos los programas sin firmar, también la versión portable. Hay que desactivarlo en Seguridad de Windows o usar otro ordenador o la versión para Linux.'],
        ],
        'avis_mes' => 'Explicación paso a paso en la guía de instalación',
        'req_titol' => 'Qué hace falta',
        'req' => ['Un ordenador con Windows 10 u 11 de 64 bits, o con Linux y PHP 8.1 o posterior', 'Unos 100 MB de disco', 'Un lápiz USB o una carpeta en la nube para las copias (recomendado)'],
        'nav_faq' => 'Preguntas',
        'faq_titol' => 'Preguntas frecuentes',
        'faq_intro' => 'Lo que más se pregunta antes de probarlo. Si tenéis otra duda, abrid una incidencia en GitHub.',
        'comptes_titol' => 'Quién puede hacer qué',
        'comptes_intro' => 'Teixit tiene dos cuentas: la del administrador/a (normalmente, la presidencia o quien lleva la informática) y la de usuario/a (la secretaría o la tesorería). Las dos hacen todo el trabajo del día a día; solo el administrador/a puede tocar la configuración, las cuentas y la restauración de copias.',
        'comptes_cols' => ['Qué se puede hacer', 'Administrador/a', 'Usuario/a'],
        'comptes' => [
            ['Personas, socios y tipos de socio', true],
            ['Secciones o equipos, actividades y cursos, monitores y matrículas', true],
            ['Inventario, préstamos y ropa', true],
            ['Cuotas, recibos, domiciliaciones, remesas y lotería', true],
            ['Libro de caja', true],
            ['Junta, actas, documentos y comunicados', true],
            ['Listados, certificados e importación desde Excel', true],
            ['Hacer y descargar copias de seguridad', true],
            ['Cambiar la propia contraseña', true],
            ['Configuración de la entidad, tipo de entidad, logotipo, módulos y avisos', false],
            ['Datos bancarios y descuentos de las actividades', false],
            ['Crear y gestionar las cuentas', false],
            ['Registro de actividad', false],
            ['Restaurar y eliminar copias, carpeta externa y cifrado', false],
            ['Recuperar el acceso con el código de recuperación', false],
        ],
        'faq' => [
            ['¿Es gratis de verdad?', 'Sí. Es software libre: sin cuotas, sin límite de socios y sin versión «de pago». No hay nada que contratar.'],
            ['¿Hace falta Internet?', 'No. Teixit funciona sin conexión. Los ficheros de remesas para el banco y los correos se preparan en el programa y los enviáis vosotros desde la banca electrónica o vuestro correo.'],
            ['¿Teixit envía nuestros datos a algún sitio?', 'No. Los datos se quedan en el ordenador de la entidad y, si la configuráis, en la carpeta externa de copias (un USB o una carpeta en la nube que elegís vosotros). Ni el programa ni esta web recogen nada.'],
            ['¿Sirve para nuestra entidad?', 'Si sois una asociación cultural, una falla (o gaiata u hoguera), un club deportivo, un AMPA o una peña, sí: al instalarlo elegís el tipo y Teixit se adapta. Otras entidades parecidas pueden elegir «asociación cultural». Si sois una sociedad musical, mirad Compàs.'],
            ['¿Funciona en Windows y en Linux?', 'Sí. Para Windows 10 u 11 de 64 bits hay un instalador y una versión portable; para Linux, un paquete .deb y un .tar.gz. No hay versión para Mac ni para el móvil.'],
            ['¿Se puede usar desde varios ordenadores a la vez?', 'No: Teixit se instala en un ordenador de la entidad y se usa en ese ordenador. Para cambiar de ordenador, basta con restaurar la última copia en el nuevo.'],
            ['¿Puedo probarlo sin poner los datos de la entidad?', 'Sí. Al instalarlo, podéis pedir que se carguen datos de ejemplo de vuestro tipo de entidad: socios, secciones, actividades, recibos… inventados. Cuando lo conozcáis, empezad de cero con los datos reales.'],
            ['Tenemos los socios en un Excel. ¿Hay que escribirlos todos?', 'No. Importar datos lee ficheros .xlsx y .csv: elegís qué columna es cada dato, revisáis la vista previa y se importan. Las personas que ya están no se duplican.'],
            ['¿Teixit hace facturas?', 'No, hace recibos. Las facturas se tienen que hacer con un programa de facturación que cumpla la normativa (Verifactu).'],
            ['¿Y si se estropea el ordenador?', 'Para eso están las copias de seguridad. Teixit hace una cada vez que se cierra, y conviene configurar una carpeta externa para que también se guarden fuera del ordenador. Con la última copia, lo recuperáis todo en otro ordenador.'],
            ['¿Cómo se actualiza?', 'En Ayuda, el botón «¿Hay versión nueva?» abre la página de la última versión. Si hay una nueva, se instala encima de la anterior y los datos se conservan; antes de actualizar, Teixit hace una copia.'],
        ],
        'docs_titol' => 'Manuales',
        'docs' => [['manual-usuario.pdf', 'Manual de usuario, paso a paso con capturas'], ['manual-administracion.pdf', 'Manual de administración: configuración, cuentas, copias y actualizaciones'], ['guia-instalacion.pdf', 'Guía de instalación']],
        'lliure_titol' => 'Software libre',
        'lliure' => 'Teixit se distribuye bajo la licencia GNU AGPL v3 o posterior: puedes utilizarlo, estudiarlo, modificarlo y compartirlo. Las versiones modificadas que se ofrezcan a través de una red también deben poner su código fuente a disposición de los usuarios. El código es público en GitHub.',
        'autor' => 'Un proyecto de José Vicente Sanjuan.',
        'ia' => 'Se ha desarrollado con la ayuda de una inteligencia artificial (Claude, de Anthropic).',
        'codi' => 'Código en GitHub',
        'novetats' => 'Qué cambia en cada versión',
        'privacitat' => 'Privacidad',
        'llicencia' => 'Resumen de la licencia',
        'errades' => '¿Has encontrado un error o tienes una propuesta? Abre una incidencia en GitHub.',
        'contacte' => 'Contacto',
        'fi' => 'Hecho para las asociaciones.',
        'resp_titol' => 'Aviso de responsabilidad',
        'resp' => 'Teixit es software libre y se distribuye tal cual, sin ninguna garantía (licencia AGPL-3.0). El autor no se hace responsable de la pérdida de datos ni de ningún otro daño, directo o indirecto, que pueda derivarse del uso del programa, de un error, de una actualización o de una configuración incorrecta. Cada entidad es responsable de sus datos y de hacer copias de seguridad periódicas en un lugar externo (USB o nube).',
        'anterior' => 'Anterior', 'seguent' => 'Siguiente',
    ],
];

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function pagina(array $t, string $l, string $base, string $repo, string $versio, array $captures): string
{
    $descarrega = $repo . '/releases/latest';
    $qui = implode('', array_map(fn($p) => '<p>' . e($p) . '</p>', $t['qui']));
    $contacte = '<a href="' . e($repo) . '/issues/new/choose">' . e($t['qui_contacte']) . '</a>';
    $tipus = implode('', array_map(fn($p) => '<div class="punt-priv tipus-entitat"><h3>' . e($p[0]) . '</h3><p>' . e($p[1]) . '</p></div>', $t['tipus']));
    $privPunts = implode('', array_map(fn($p) => '<div class="punt-priv"><h3>' . e($p[0]) . '</h3><p>' . e($p[1]) . '</p></div>', $t['priv_punts']));
    $compCap = implode('', array_map(fn($c) => '<th>' . e($c) . '</th>', $t['comp_cols']));
    $compFiles = implode('', array_map(fn($f) => '<tr><th>' . e($f[0]) . '</th><td class="si">' . e($f[1]) . '</td><td>' . e($f[2]) . '</td></tr>', $t['comp']));
    $moduls = '';
    foreach ($t['moduls'] as [$nom, $text]) {
        $moduls .= '<div class="modul"><dt>' . e($nom) . '</dt><dd>' . e($text) . '</dd></div>';
    }
    $passos = '';
    foreach ($t['passos'] as [$nom, $text]) {
        $passos .= '<li><h3>' . e($nom) . '</h3><p>' . $text . '</p></li>';
    }
    $dades = implode('', array_map(fn($d) => '<li>' . e($d) . '</li>', $t['dades']));
    $req = implode('', array_map(fn($d) => '<li>' . e($d) . '</li>', $t['req']));
    $comptesCap = implode('', array_map(fn($c) => '<th>' . e($c) . '</th>', $t['comptes_cols']));
    $comptes = implode('', array_map(fn($f) => '<tr><th>' . e($f[0]) . '</th><td class="permis">✔</td><td class="permis' . ($f[1] ? '">✔' : ' no">—') . '</td></tr>', $t['comptes']));
    $faq = implode('', array_map(fn($f) => '<details><summary>' . e($f[0]) . '</summary><p>' . e($f[1]) . '</p></details>', $t['faq']));
    $avisos = implode('', array_map(fn($a) => '<dt>' . e($a[0]) . '</dt><dd>' . e($a[1]) . '</dd>', $t['avis']));
    $guia = $repo . '/blob/main/docs/' . ($l === 'es' ? 'guia-instalacion.md#si-windows-no-te-deja-abrirlo' : 'guia-installacio.md#si-windows-no-et-deixa-obrir-lo');
    // Els PDF es generen en publicar la web (workflow web.yml); si no hi són, s'enllacen els de l'última versió de GitHub
    $pdf = fn(string $f) => is_file(dirname(__DIR__) . '/docs/pdf/' . $f) ? $base . 'pdf/' . $f : $repo . '/releases/latest/download/' . $f;
    $docs = implode('', array_map(fn($d) => '<li><a href="' . e($pdf($d[0])) . '">' . e($d[1]) . '</a> <span class="pes">PDF</span></li>', $t['docs']));
    $manualPdf = e($pdf($t['docs'][0][0]));

    $miniatures = '';
    $primera = $captures[0];
    foreach ($captures as $i => $c) {
        $src = $base . 'img/' . $l . '/' . $c . '.webp';
        $miniatures .= '<li><button type="button" data-src="' . e($src) . '" data-peu="' . e($t['captures'][$c]) . '"' . ($i === 0 ? ' aria-current="true"' : '')
            . ' aria-label="' . e($t['captures'][$c]) . '"><img src="' . e($src) . '" alt="" loading="lazy"></button></li>';
    }
    $versioText = $versio ? sprintf($t['versio'], $versio) : '';
    $any = date('Y');

    return <<<HTML
<!doctype html>
<html lang="{$t['lang']}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$t['titol']}</title>
<meta name="description" content="{$t['descripcio']}">
<meta property="og:title" content="{$t['titol']}">
<meta property="og:description" content="{$t['descripcio']}">
<meta property="og:image" content="{$base}img/logo.png">
<link rel="icon" href="{$base}img/icona.png">
<link rel="alternate" hreflang="{$t['altre']}" href="{$t['altre_url']}">
<link rel="preload" href="{$base}fonts/atkinson-hyperlegible-regular.woff" as="font" type="font/woff" crossorigin>
<link rel="stylesheet" href="{$base}estil.css">
</head>
<body>
<header class="barra">
  <a class="marca" href="./"><img src="{$base}img/icona.png" alt="" width="36" height="36"><span>Teixit</span></a>
  <nav aria-label="Seccions">
    <a href="#privacitat">{$t['nav_priv']}</a>
    <a href="#que">{$t['nav_que']}</a>
    <a href="#captures">{$t['nav_veure']}</a>
    <a href="#tipus">{$t['nav_qui']}</a>
    <a href="#comencar">{$t['nav_com']}</a>
    <a href="#preguntes">{$t['nav_faq']}</a>
    <a href="{$repo}">{$t['nav_codi']}</a>
    <a class="idioma" href="{$t['altre_url']}" hreflang="{$t['altre']}" lang="{$t['altre']}">{$t['altre_nom']}</a>
  </nav>
</header>

<main>
<section class="hero">
  <div class="fils" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
  <div class="hero-text">
    <p class="lema">{$t['lema']}</p>
    <h1>{$t['hero']}</h1>
    <p class="entrada">{$t['hero_text']}</p>
    <div class="accions">
      <a class="boto" href="{$descarrega}">{$t['descarrega']}</a>
      <a class="boto secundari" href="{$manualPdf}">{$t['manual']}</a>
    </div>
    <p class="versio">{$versioText}</p>
    <p class="qui-lema">{$t['qui_lema']} <a href="#qui">{$t['qui_llig']} →</a></p>
    <p class="germa">{$t['germa']} <a href="{$t['germa_url']}">{$t['germa_nom']} →</a></p>
  </div>
  <figure class="hero-captura">
    <img src="{$base}img/{$l}/web-inici.webp" alt="{$t['captures']['web-inici']}" width="1920" height="1200">
  </figure>
</section>

<section id="privacitat" class="seccio privacitat">
  <div class="cap">
    <h2>{$t['priv_titol']}</h2>
    <p>{$t['priv_intro']}</p>
  </div>
  <div class="punts-priv">{$privPunts}</div>
  <h3 class="comp-titol">{$t['comp_titol']}</h3>
  <div class="taula-comp-wrap"><table class="taula-comp"><thead><tr>{$compCap}</tr></thead><tbody>{$compFiles}</tbody></table></div>
</section>

<section id="tipus" class="seccio">
  <div class="cap">
    <h2>{$t['tipus_titol']}</h2>
    <p>{$t['tipus_intro']}</p>
  </div>
  <div class="punts-priv punts-tipus">{$tipus}</div>
</section>

<section id="que" class="seccio">
  <div class="cap">
    <h2>{$t['que_titol']}</h2>
    <p>{$t['que_intro']}</p>
  </div>
  <dl class="moduls">{$moduls}</dl>
</section>

<section id="captures" class="seccio galeria">
  <div class="cap">
    <h2>{$t['veure_titol']}</h2>
    <p>{$t['veure_intro']}</p>
  </div>
  <figure class="principal">
    <div class="visor">
      <img id="captura" src="{$base}img/{$l}/{$primera}.webp" alt="{$t['captures'][$primera]}">
      <button type="button" class="fletxa ant" aria-label="{$t['anterior']}">‹</button>
      <button type="button" class="fletxa seg" aria-label="{$t['seguent']}">›</button>
    </div>
    <figcaption id="peu" aria-live="polite">{$t['captures'][$primera]}</figcaption>
  </figure>
  <ul class="miniatures" aria-label="{$t['veure_titol']}">{$miniatures}</ul>
</section>

<section id="qui" class="seccio qui">
  <h2>{$t['qui_titol']}</h2>
  <blockquote>{$qui}
    <footer>— {$t['qui_signa']}</footer>
  </blockquote>
  <p class="contacte">{$contacte}</p>
</section>

<section id="comencar" class="seccio">
  <div class="cap"><h2>{$t['com_titol']}</h2></div>
  <ol class="passos">{$passos}</ol>
  <aside class="avis-windows">
    <h3>{$t['avis_titol']}</h3>
    <dl>{$avisos}</dl>
    <p><a href="{$guia}">{$t['avis_mes']}</a></p>
  </aside>
  <aside class="avis-windows avis-resp" id="avis"><h3>{$t['resp_titol']}</h3><p>{$t['resp']}</p></aside>
</section>

<section class="seccio dues">
  <div>
    <h2>{$t['dades_titol']}</h2>
    <ul class="punts">{$dades}</ul>
  </div>
  <div>
    <h2>{$t['req_titol']}</h2>
    <ul class="punts">{$req}</ul>
    <h2 class="h2-segon">{$t['docs_titol']}</h2>
    <ul class="docs">{$docs}</ul>
  </div>
</section>

<section id="preguntes" class="seccio preguntes">
  <div class="cap">
    <h2>{$t['faq_titol']}</h2>
    <p>{$t['faq_intro']}</p>
  </div>
  <div class="faq">{$faq}</div>
  <h3 class="comp-titol">{$t['comptes_titol']}</h3>
  <p class="comptes-intro">{$t['comptes_intro']}</p>
  <div class="taula-comp-wrap"><table class="taula-comp taula-comptes"><thead><tr>{$comptesCap}</tr></thead><tbody>{$comptes}</tbody></table></div>
</section>

<section class="seccio lliure">
  <h2>{$t['lliure_titol']}</h2>
  <p>{$t['lliure']}</p>
  <p class="nota">{$t['ia']}</p>
  <p><a class="boto secundari" href="{$repo}">{$t['codi']}</a></p>
  <p class="nota"><a href="{$repo}/blob/main/CHANGELOG.md">{$t['novetats']}</a> · <a href="{$repo}/issues/new/choose">{$t['errades']}</a></p>
</section>
</main>

<footer class="peu">
  <div class="teixit-franja" aria-hidden="true"></div>
  <p><img src="{$base}img/icona.png" alt="" width="28" height="28"> <span>Teixit · © {$any} José Vicente Sanjuan. {$t['fi']}</span> <a href="{$repo}/blob/main/LICENSE">AGPL-3.0-or-later</a> <a href="{$repo}/blob/main/LLICENCIA.md">{$t['llicencia']}</a> <a href="{$repo}/blob/main/PRIVACITAT.md">{$t['privacitat']}</a> <a href="{$repo}/issues/new/choose">{$t['contacte']}</a></p>
  <p class="peu-resp">{$t['resp']}</p>
</footer>

<script>
(() => {
  const botons = [...document.querySelectorAll('.miniatures button')];
  const img = document.getElementById('captura');
  let actual = 0;
  const mostra = i => {
    actual = (i + botons.length) % botons.length;
    const b = botons[actual];
    img.src = b.dataset.src; img.alt = b.dataset.peu;
    document.getElementById('peu').textContent = b.dataset.peu;
    botons.forEach(x => x.removeAttribute('aria-current'));
    b.setAttribute('aria-current', 'true');
    b.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
  };
  botons.forEach((b, i) => b.addEventListener('click', () => mostra(i)));
  document.querySelector('.fletxa.ant').addEventListener('click', () => mostra(actual - 1));
  document.querySelector('.fletxa.seg').addEventListener('click', () => mostra(actual + 1));
  // Tecles ← → quan la galeria està a la vista
  const galeria = document.getElementById('captures');
  document.addEventListener('keydown', e => {
    if (e.target.closest('input, textarea')) return;
    const r = galeria.getBoundingClientRect();
    if (r.bottom < 0 || r.top > innerHeight) return;
    if (e.key === 'ArrowLeft') mostra(actual - 1);
    if (e.key === 'ArrowRight') mostra(actual + 1);
  });
  // Lliscar amb el dit (mòbils i tauletes)
  let x0 = null;
  img.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
  img.addEventListener('touchend', e => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 40) mostra(actual + (dx < 0 ? 1 : -1));
    x0 = null;
  });
})();
</script>
</body>
</html>
HTML;
}

/** Els enllaços que porten fora de la pàgina (GitHub, PDF, formulari…) s'obrin en una pestanya nova */
function pestanya_nova(string $html): string
{
    return preg_replace_callback('/<a\s([^>]*?)href="([^"]+)"([^>]*)>/', function ($m) {
        $href = $m[2];
        $fora = preg_match('#^https?://#', $href) || str_ends_with($href, '.pdf');
        if (!$fora || str_contains($m[0], 'target=')) {
            return $m[0];
        }
        return '<a ' . $m[1] . 'href="' . $href . '"' . $m[3] . ' target="_blank" rel="noopener">';
    }, $html);
}

// Carpetes i recursos
foreach (['', '/es', '/img/ca', '/img/es', '/pdf'] as $d) {
    if (!is_dir($eixida . $d)) {
        mkdir($eixida . $d, 0775, true);
    }
}
copy($arrel . '/app/assets/logo.png', $eixida . '/img/logo.png');
copy($arrel . '/app/assets/icona.png', $eixida . '/img/icona.png');
copy(__DIR__ . '/web.css', $eixida . '/estil.css');
// Tipografies lliures (SIL OFL) servides des de la mateixa web: cap petició a servidors de tercers
if (!is_dir($eixida . '/fonts')) {
    mkdir($eixida . '/fonts', 0775, true);
}
foreach (glob(__DIR__ . '/fonts/*') as $f) {
    copy($f, $eixida . '/fonts/' . basename($f));
}
foreach (['ca', 'es'] as $l) {
    foreach ($captures as $c) {
        copy("$arrel/docs/imatges/$l/$c.webp", "$eixida/img/$l/$c.webp");
    }
}
foreach (glob($arrel . '/docs/pdf/*.pdf') as $pdf) {
    copy($pdf, $eixida . '/pdf/' . basename($pdf));
}
file_put_contents($eixida . '/index.html', pestanya_nova(pagina($T['ca'], 'ca', '', $repo, $versio, $captures)));
file_put_contents($eixida . '/es/index.html', pestanya_nova(pagina($T['es'], 'es', '../', $repo, $versio, $captures)));
file_put_contents($eixida . '/.nojekyll', '');
echo "Web generada a $eixida\n";
