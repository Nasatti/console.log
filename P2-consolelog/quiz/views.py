"""
Views del progetto consolelog.

Ogni funzione qui corrisponde a una pagina (o endpoint AJAX) del progetto PHP originale.

Miglioramenti rispetto alla versione precedente:
  - Views non-AJAX migrate da SQL raw a Django ORM (get_object_or_404,
    select_related, prefetch_related, annotate, Count, Subquery)
  - QuizForm e PartecipaForm (da forms.py) per la validazione dei dati
  - Logica di business spostata nei @property dei modelli (stato, puo_essere_modificato)
  - Helper privati _applica_ordinamento(), _pagina(), _utenti_header()
    riutilizzati in tutte le views (come nel progetto Gruppo 70)
  - URL REST-like: quiz_id e part_id arrivano come argomenti di path
  - Gli endpoint AJAX mantengono SQL raw per le query complesse con
    JOIN multipli, subquery e calcolo punteggi (troppo verbosi in ORM puro)
"""

import re
from datetime import date
from django.shortcuts      import render, redirect, get_object_or_404
from django.http           import JsonResponse
from django.db             import connection, transaction
from django.core.paginator import Paginator
from django.contrib        import messages

from .models import Utente, Quiz, Domanda, Risposta, Partecipazione, RispostaUtenteQuiz
from .forms  import QuizForm, PartecipaForm


RIGHE_PER_PAGINA_QUIZ   = 20
RIGHE_PER_PAGINA_UTENTI = 25
RIGHE_PER_PAGINA_PART   = 25


# ─────────────────────────────────────────────────────────────────────────────
# HELPER PRIVATI (analogo al pattern del Gruppo 70)
# ─────────────────────────────────────────────────────────────────────────────

def _utenti_header():
    """Restituisce il queryset degli utenti per il selettore nell'header.
    Riutilizzato da ogni view che renderizza un template con base.html."""
    return Utente.objects.all().order_by('cognome', 'nome')


def _pagina(request, lista, per_pagina):
    """Paginazione su una lista Python o QuerySet."""
    paginator = Paginator(lista, per_pagina)
    num = request.GET.get('page', 1)
    return paginator.get_page(num)


def _parse_sort(request, colonne_ammesse, default_col, default_dir='asc'):
    """Legge e valida i parametri ?sort= e ?dir= dalla richiesta GET.
    La whitelist colonne_ammesse previene SQL injection per gli endpoint AJAX."""
    sort = request.GET.get('sort', default_col)
    dire = request.GET.get('dir', default_dir)
    if sort not in colonne_ammesse:
        sort = default_col
    if dire not in ('asc', 'desc'):
        dire = default_dir
    return sort, dire


def esegui_query(sql, params=None):
    """Esegue una query SQL raw e restituisce tutti i risultati come lista di dizionari.
    Usato solo dagli endpoint AJAX con query complesse."""
    with connection.cursor() as cur:
        cur.cursor.execute(sql, tuple(params) if params else ())
        if cur.cursor.description:
            colonne = [col[0] for col in cur.cursor.description]
            return [dict(zip(colonne, riga)) for riga in cur.cursor.fetchall()]
        return []

def esegui_query_singola(sql, params=None):
    """Come esegui_query, ma restituisce solo la prima riga (o None).
    Usato solo dagli endpoint AJAX."""
    risultati = esegui_query(sql, params)
    return risultati[0] if risultati else None


# ─────────────────────────────────────────────────────────────────────────────
# HOME — index.php
# ─────────────────────────────────────────────────────────────────────────────

def home(request):
    from django.db.models import Count

    oggi = date.today()

    # Conteggi con ORM
    n_quiz           = Quiz.objects.count()
    n_utenti         = Utente.objects.count()
    n_partecipazioni = Partecipazione.objects.count()
    n_domande        = Domanda.objects.count()

    # Ultimi 5 quiz con conteggi annotati
    ultimi_qs = (
        Quiz.objects
        .select_related('creatore')
        .annotate(
            nDomande=Count('domanda', distinct=True),
            nPartecipanti=Count('partecipazione', distinct=True),
        )
        .order_by('-codice')[:5]
    )
    # Convertiamo in lista di dict per compatibilità con il template esistente
    ultimi = []
    for q in ultimi_qs:
        ultimi.append({
            'codice':          q.codice,
            'titolo':          q.titolo,
            'dataInizio':      q.dataInizio,
            'dataFine':        q.dataFine,
            'creatore':        q.creatore_id,
            'nome':            q.creatore.nome,
            'cognome':         q.creatore.cognome,
            'nDomande':        q.nDomande,
            'nPartecipanti':   q.nPartecipanti,
            'stato':           q.stato,
        })

    # Quiz più popolare
    top_qs = (
        Quiz.objects
        .annotate(tot=Count('partecipazione'))
        .order_by('-tot')
        .first()
    )
    top = {'codice': top_qs.codice, 'titolo': top_qs.titolo, 'tot': top_qs.tot} if top_qs else None

    # Creatore più prolifico
    top_creatore_qs = (
        Utente.objects
        .annotate(tot=Count('quiz'))
        .order_by('-tot')
        .first()
    )
    top_creatore = {
        'nome': top_creatore_qs.nome,
        'cognome': top_creatore_qs.cognome,
        'nomeUtente': top_creatore_qs.nomeUtente,
        'tot': top_creatore_qs.tot,
    } if top_creatore_qs else None

    return render(request, 'quiz/home.html', {
        'page_title':      'Home',
        'active_nav':      'home',
        'n_quiz':          n_quiz,
        'n_utenti':        n_utenti,
        'n_partecipazioni': n_partecipazioni,
        'n_domande':       n_domande,
        'ultimi':          ultimi,
        'top':             top,
        'top_creatore':    top_creatore,
        'utenti_header':   _utenti_header(),
        'oggi':            oggi.isoformat(),
    })


# ─────────────────────────────────────────────────────────────────────────────
# LISTA QUIZ — quiz.php
# ─────────────────────────────────────────────────────────────────────────────

def quiz_lista(request):
    creatore_prefilter = request.GET.get('creatore', '')

    return render(request, 'quiz/quiz_lista.html', {
        'page_title':        'Quiz',
        'active_nav':        'quiz',
        'utenti':            _utenti_header(),
        'utenti_header':     _utenti_header(),
        'creatore_prefilter': creatore_prefilter,
    })


# ─────────────────────────────────────────────────────────────────────────────
# DETTAGLIO QUIZ — quiz_detail.php
# ─────────────────────────────────────────────────────────────────────────────

def quiz_dettaglio(request, quiz_id):
    """Dettaglio di un quiz. quiz_id arriva dalla path URL (/quiz/<id>/)."""
    quiz = get_object_or_404(Quiz.objects.select_related('creatore'), pk=quiz_id)

    # Domande con le risposte — usa ORM
    domande_qs = (
        Domanda.objects
        .filter(codiceQuiz=quiz)
        .order_by('numero')
    )
    
    # Pre-fetch all responses for this quiz
    tutte_risposte = Risposta.objects.filter(codiceQuiz=quiz_id).order_by('numero')
    
    # Convertiamo per compatibilità con il template (accede a dom.risposte)
    domande = []
    for dom in domande_qs:
        domande.append({
            'numero':   dom.numero,
            'testo':    dom.testo,
            'risposte': [
                {
                    'numero':    r.numero,
                    'testo':     r.testo,
                    'punteggio': r.punteggio,
                    'tipo':      r.tipo,
                }
                for r in tutte_risposte if r.numeroDomanda == dom.numero
            ],
        })

    # Ultime 10 partecipazioni con punteggio (query raw per il calcolo punteggi)
    partecipazioni = esegui_query("""
        SELECT p.codice, p.data, u.nome, u.cognome, u.nomeUtente,
               COALESCE(punteggi.tot, 0) AS punteggio_tot
        FROM Partecipazione p
        JOIN Utente u ON p.nomeUtente = u.nomeUtente
        LEFT JOIN (
            SELECT oc.codicePartecipazione,
                   SUM(CASE WHEN r.punteggio IS NOT NULL THEN r.punteggio ELSE 0 END) AS tot
            FROM RispostaUtenteQuiz oc
            JOIN Risposta r ON r.numero = oc.numeroRisposta
                           AND r.numeroDomanda = oc.numeroDomanda
                           AND r.codiceQuiz = oc.codiceQuiz
            GROUP BY oc.codicePartecipazione
        ) punteggi ON punteggi.codicePartecipazione = p.codice
        WHERE p.codiceQuiz = ?
        ORDER BY p.data DESC
        LIMIT 10
    """, [quiz_id])

    # Punteggio medio tramite query raw (calcolo complesso)
    max_pt = esegui_query_singola(
        "SELECT SUM(punteggio) AS tot FROM Risposta WHERE codiceQuiz = ? AND punteggio IS NOT NULL",
        [quiz_id]
    )
    max_punti = float(max_pt['tot']) if max_pt and max_pt['tot'] else 0
    punteggio_medio = None
    if max_punti > 0:
        punteggi_part = esegui_query("""
            SELECT SUM(r.punteggio) AS tot
            FROM RispostaUtenteQuiz oc
            JOIN Risposta r ON r.numero = oc.numeroRisposta
                           AND r.numeroDomanda = oc.numeroDomanda
                           AND r.codiceQuiz = oc.codiceQuiz
            WHERE oc.codiceQuiz = ? AND r.punteggio IS NOT NULL
            GROUP BY oc.codicePartecipazione
        """, [quiz_id])
        if punteggi_part:
            punteggio_medio = round(
                sum(p['tot'] or 0 for p in punteggi_part) / len(punteggi_part), 2
            )

    # Dati aggiuntivi per il template (manteniamo compatibilità)
    quiz_ctx = {
        'codice':            quiz.codice,
        'titolo':            quiz.titolo,
        'dataInizio':        quiz.dataInizio,
        'dataFine':          quiz.dataFine,
        'creatore':          quiz.creatore_id,
        'nome':              quiz.creatore.nome,
        'cognome':           quiz.creatore.cognome,
        'eMail':             quiz.creatore.eMail,
        'num_domande':       len(domande),
        'num_partecipazioni': quiz.num_partecipazioni,
        'punteggio_medio':   punteggio_medio,
    }

    utente_attivo = request.session.get('utente_attivo', '')
    is_owner = bool(utente_attivo and utente_attivo == quiz.creatore_id)

    return render(request, 'quiz/quiz_dettaglio.html', {
        'page_title':       quiz.titolo,
        'active_nav':       'quiz',
        'quiz':             quiz_ctx,
        'domande':          domande,
        'partecipazioni':   partecipazioni,
        'aperto':           quiz.is_aperto,
        'futuro':           quiz.is_futuro,
        'is_owner':         is_owner,
        'ha_partecipazioni': quiz.num_partecipazioni > 0,
        'utenti_header':    _utenti_header(),
        'oggi':             date.today().isoformat(),
    })


# ─────────────────────────────────────────────────────────────────────────────
# FORM QUIZ (CREA / MODIFICA) — quiz_form.php
# ─────────────────────────────────────────────────────────────────────────────

def quiz_form_modifica(request, quiz_id):
    return quiz_form(request, quiz_id=quiz_id)

def quiz_form(request, quiz_id=None):
    is_edit = quiz_id is not None
    quiz = None
    domande = []

    if is_edit:
        quiz = get_object_or_404(Quiz, pk=quiz_id)

        # Blocco accesso se quiz non modificabile (usa la @property del modello)
        if not quiz.puo_essere_modificato:
            return redirect('quiz_dettaglio', quiz_id=quiz_id)

        domande_qs = (
            Domanda.objects
            .filter(codiceQuiz=quiz)
            .order_by('numero')
        )
        
        tutte_risposte = list(Risposta.objects.filter(codiceQuiz=quiz_id).order_by('numero').values(
            'numero', 'numeroDomanda', 'testo', 'tipo', 'punteggio'
        ))
        
        for dom in domande_qs:
            domande.append({
                'numero':   dom.numero,
                'testo':    dom.testo,
                'risposte': [r for r in tutte_risposte if r['numeroDomanda'] == dom.numero],
            })

    # Dati iniziali del form (per pre-compilare in modifica)
    initial = {}
    if is_edit and quiz:
        initial = {
            'titolo':     quiz.titolo,
            'dataInizio': quiz.dataInizio,
            'dataFine':   quiz.dataFine,
            'creatore':   quiz.creatore_id,
        }

    if request.method == 'POST' and 'titolo' in request.POST:
        form = QuizForm(request.POST, quiz_id=quiz_id if is_edit else None)

        if form.is_valid():
            titolo      = form.cleaned_data['titolo']
            data_inizio = form.cleaned_data['dataInizio']
            data_fine   = form.cleaned_data['dataFine']
            creatore_id = form.cleaned_data['creatore']
            testi_domande = request.POST.getlist('domanda_testo[]')

            try:
                with transaction.atomic():
                    if is_edit:
                        # Aggiorna il quiz esistente con ORM
                        quiz.titolo     = titolo
                        quiz.dataInizio = data_inizio
                        quiz.dataFine   = data_fine
                        quiz.creatore_id = creatore_id
                        quiz.save()
                        # Elimina le domande (CASCADE elimina le risposte)
                        Domanda.objects.filter(codiceQuiz=quiz).delete()
                        codice_quiz = quiz_id
                    else:
                        # Crea il quiz con ORM
                        quiz_obj = Quiz.objects.create(
                            titolo=titolo,
                            dataInizio=data_inizio,
                            dataFine=data_fine,
                            creatore_id=creatore_id,
                        )
                        codice_quiz = quiz_obj.codice

                    # Inserisce domande e risposte con ORM
                    for i, testo_dom in enumerate(testi_domande, start=1):
                        testo_dom = testo_dom.strip()
                        if not testo_dom:
                            continue
                        dom_obj = Domanda.objects.create(
                            numero=i,
                            codiceQuiz_id=codice_quiz,
                            testo=testo_dom,
                        )
                        testi_risp = request.POST.getlist(f'risposta_testo_{i}[]')
                        tipi_risp  = request.POST.getlist(f'risposta_tipo_{i}[]')
                        punti_risp = request.POST.getlist(f'risposta_punti_{i}[]')

                        risp_objs = []
                        for j, (tr, tipo, pt) in enumerate(zip(testi_risp, tipi_risp, punti_risp), start=1):
                            tr = tr.strip()
                            if not tr:
                                continue
                            corretta  = tipo == 'Corretta'
                            punteggio = float(pt) if corretta and pt else None
                            risp_objs.append(Risposta(
                                numero=j,
                                numeroDomanda=i,
                                codiceQuiz=codice_quiz,
                                testo=tr,
                                tipo=tipo,
                                punteggio=punteggio,
                            ))
                        Risposta.objects.bulk_create(risp_objs)

                return redirect('quiz_dettaglio', quiz_id=codice_quiz)

            except Exception as e:
                form.add_error(None, f'Errore durante il salvataggio: {e}')
    else:
        form = QuizForm(initial=initial, quiz_id=quiz_id if is_edit else None)

    quiz_ctx = None
    if is_edit and quiz:
        quiz_ctx = {
            'codice':     quiz.codice,
            'titolo':     quiz.titolo,
            'dataInizio': quiz.dataInizio,
            'dataFine':   quiz.dataFine,
            'creatore':   quiz.creatore_id,
        }

    return render(request, 'quiz/quiz_form.html', {
        'page_title':    'Modifica Quiz' if is_edit else 'Nuovo Quiz',
        'active_nav':    'quiz',
        'is_edit':       is_edit,
        'quiz':          quiz_ctx,
        'domande':       domande,
        'utenti':        _utenti_header(),
        'utenti_header': _utenti_header(),
        'form':          form,
        # Compatibilità con template che usa msg/msg_type
        'msg':           form.non_field_errors()[0] if not form.is_valid() and form.non_field_errors() else '',
        'msg_type':      'error' if not form.is_valid() else '',
        'form_data': {
            'titolo':     request.POST.get('titolo', ''),
            'dataInizio': request.POST.get('dataInizio', ''),
            'dataFine':   request.POST.get('dataFine', ''),
            'creatore':   request.POST.get('creatore', ''),
        } if request.method == 'POST' else {},
    })


# ─────────────────────────────────────────────────────────────────────────────
# ELIMINA QUIZ — quiz_delete.php
# ─────────────────────────────────────────────────────────────────────────────

def quiz_elimina(request, quiz_id):
    quiz = get_object_or_404(Quiz.objects.select_related('creatore'), pk=quiz_id)

    # Blocco accesso se quiz non eliminabile (usa la @property del modello)
    if not quiz.puo_essere_eliminato:
        return redirect('quiz_dettaglio', quiz_id=quiz_id)

    if request.method == 'POST' and request.POST.get('conferma'):
        try:
            with transaction.atomic():
                # Elimina nell'ordine corretto per rispettare le FK
                Risposta.objects.filter(codiceQuiz=quiz_id).delete()
                Domanda.objects.filter(codiceQuiz=quiz).delete()
                quiz.delete()
            return redirect('quiz_lista')
        except Exception as e:
            pass  # in caso di errore rimane sulla pagina con il messaggio

    quiz_ctx = {
        'codice':             quiz.codice,
        'titolo':             quiz.titolo,
        'dataInizio':         quiz.dataInizio,
        'dataFine':           quiz.dataFine,
        'creatore':           quiz.creatore_id,
        'nome':               quiz.creatore.nome,
        'cognome':            quiz.creatore.cognome,
        'num_domande':        Domanda.objects.filter(codiceQuiz=quiz).count(),
        'num_partecipazioni': quiz.num_partecipazioni,
    }

    return render(request, 'quiz/quiz_elimina.html', {
        'page_title':    'Elimina Quiz',
        'active_nav':    'quiz',
        'quiz':          quiz_ctx,
        'utenti_header': _utenti_header(),
    })


# ─────────────────────────────────────────────────────────────────────────────
# PARTECIPA — partecipa.php
# ─────────────────────────────────────────────────────────────────────────────

def partecipa(request, quiz_id):
    quiz = get_object_or_404(Quiz.objects.select_related('creatore'), pk=quiz_id)

    # Usa la @property del modello per verificare lo stato
    if not quiz.is_aperto:
        return redirect('quiz_dettaglio', quiz_id=quiz_id)

    domande_qs = (
        Domanda.objects
        .filter(codiceQuiz=quiz)
        .order_by('numero')
    )
    if not domande_qs.exists():
        return redirect('quiz_dettaglio', quiz_id=quiz_id)

    domande = []
    for dom in domande_qs:
        domande.append({
            'numero':   dom.numero,
            'testo':    dom.testo,
            'risposte': list(
                Risposta.objects
                .filter(numeroDomanda=dom.numero, codiceQuiz=quiz_id)
                .order_by('numero')
                .values('numero', 'testo')
            ),
        })

    form = PartecipaForm()
    err_utente = ''
    oggi = date.today().isoformat()

    if request.POST.get('is_submission') == '1':
        form = PartecipaForm(request.POST)
        if form.is_valid():
            nome_utente = form.cleaned_data['nomeUtente']

            risposte = {}
            for chiave, valori in request.POST.lists():
                m = re.match(r'^risposte\[(\d+)\]\[\]$', chiave)
                if m:
                    risposte[int(m.group(1))] = [int(v) for v in valori]

            if len(risposte) < len(domande):
                err_utente = 'Devi rispondere a tutte le domande.'
            else:
                try:
                    with transaction.atomic():
                        part = Partecipazione.objects.create(
                            data=oggi,
                            nomeUtente_id=nome_utente,
                            codiceQuiz_id=quiz_id,
                        )
                        ruq_objs = []
                        for num_dom, scelte in risposte.items():
                            for num_risp in scelte:
                                ruq_objs.append(RispostaUtenteQuiz(
                                    codicePartecipazione=part.codice,
                                    numeroRisposta=num_risp,
                                    numeroDomanda=num_dom,
                                    codiceQuiz=quiz_id,
                                ))
                        RispostaUtenteQuiz.objects.bulk_create(ruq_objs)

                    return redirect('risultato', part_id=part.codice)

                except Exception as e:
                    err_utente = f'Errore: {e}'
        else:
            err_utente = form.errors.get('nomeUtente', ['Seleziona un utente.'])[0]

    quiz_ctx = {
        'codice':   quiz.codice,
        'titolo':   quiz.titolo,
        'nome':     quiz.creatore.nome,
        'cognome':  quiz.creatore.cognome,
    }

    return render(request, 'quiz/partecipa.html', {
        'page_title':    f'Partecipa: {quiz.titolo}',
        'active_nav':    'quiz',
        'quiz':          quiz_ctx,
        'domande':       domande,
        'utenti':        _utenti_header(),
        'utenti_header': _utenti_header(),
        'err_utente':    err_utente,
    })


# ─────────────────────────────────────────────────────────────────────────────
# RISULTATO — risultato.php
# ─────────────────────────────────────────────────────────────────────────────

def risultato(request, part_id):
    """Risultato di una partecipazione. part_id arriva dalla path URL (/risultato/<id>/)."""
    part_obj = get_object_or_404(
        Partecipazione.objects.select_related('nomeUtente', 'codiceQuiz__creatore'),
        pk=part_id
    )
    quiz = part_obj.codiceQuiz

    oggi = date.today().isoformat()
    referer = request.META.get('HTTP_REFERER', '')
    da_partecipazioni = '/partecipazioni' in referer

    # Calcolo punteggio (query raw per la logica complessa)
    risposte = esegui_query("""
        SELECT d.numero AS numDom, d.testo AS testoDom,
               r.numero AS numRisp, r.testo AS testoRisp, r.punteggio
        FROM RispostaUtenteQuiz oc
        JOIN Domanda d ON d.numero = oc.numeroDomanda AND d.codiceQuiz = oc.codiceQuiz
        JOIN Risposta r ON r.numero = oc.numeroRisposta
                       AND r.numeroDomanda = oc.numeroDomanda
                       AND r.codiceQuiz = oc.codiceQuiz
        WHERE oc.codicePartecipazione = ?
        ORDER BY d.numero
    """, [part_id])

    punteggio = 0.0
    grouped = {}
    for r in risposte:
        dom = r['numDom']
        if dom not in grouped:
            grouped[dom] = {
                'testo': r['testoDom'],
                'risposte_date': [],
                'errore': False,
                'punti_parziali': 0.0,
            }
        grouped[dom]['risposte_date'].append(r)
        if r['punteggio'] is None:
            grouped[dom]['errore'] = True
        else:
            grouped[dom]['punti_parziali'] += float(r['punteggio'])

    for g in grouped.values():
        if not g['errore']:
            punteggio += g['punti_parziali']

    max_punti_row = esegui_query_singola(
        "SELECT SUM(punteggio) AS tot FROM Risposta WHERE codiceQuiz = ? AND punteggio IS NOT NULL",
        [quiz.codice]
    )
    max_punti = float(max_punti_row['tot']) if max_punti_row and max_punti_row['tot'] else 0.0
    perc = round((punteggio / max_punti) * 100) if max_punti > 0 else 0
    perc = min(perc, 100)

    # Compatibilità con template: manteniamo struttura dict
    part_ctx = {
        'codice':       part_obj.codice,
        'data':         part_obj.data,
        'nomeUtente':   part_obj.nomeUtente_id,
        'nome':         part_obj.nomeUtente.nome,
        'cognome':      part_obj.nomeUtente.cognome,
        'eMail':        part_obj.nomeUtente.eMail,
        'titolo':       quiz.titolo,
        'codiceQuiz':   quiz.codice,
        'dataInizio':   quiz.dataInizio,
        'dataFine':     quiz.dataFine,
        'num_domande':  Domanda.objects.filter(codiceQuiz=quiz).count(),
    }
    quiz_aperto = quiz.is_aperto

    return render(request, 'quiz/risultato.html', {
        'page_title':       'Risultato',
        'active_nav':       'quiz',
        'part':             part_ctx,
        'grouped':          grouped,
        'punteggio':        punteggio,
        'max_punti':        max_punti,
        'perc':             perc,
        'quiz_aperto':      quiz_aperto,
        'utenti_header':    _utenti_header(),
        'oggi':             oggi,
        'da_partecipazioni': da_partecipazioni,
    })


# ─────────────────────────────────────────────────────────────────────────────
# UTENTI — utenti.php
# ─────────────────────────────────────────────────────────────────────────────

def utenti(request):
    username_prefilter = request.GET.get('username', '')

    return render(request, 'quiz/utenti.html', {
        'page_title':       'Utenti',
        'active_nav':       'utenti',
        'utenti_header':    _utenti_header(),
        'username_prefilter': username_prefilter,
    })


# ─────────────────────────────────────────────────────────────────────────────
# PARTECIPAZIONI — partecipazioni.php
# ─────────────────────────────────────────────────────────────────────────────

def partecipazioni(request):
    utente_prefilter      = request.GET.get('utente', '')
    quiz_codice_prefilter = request.GET.get('quiz_codice', '')

    return render(request, 'quiz/partecipazioni.html', {
        'page_title':           'Partecipazioni',
        'active_nav':           'partecipazioni',
        'utenti':               _utenti_header(),
        'utenti_header':        _utenti_header(),
        'utente_prefilter':     utente_prefilter,
        'quiz_codice_prefilter': quiz_codice_prefilter,
    })


# ============================================================
# ENDPOINT AJAX — SQL raw mantenuto per le query complesse
# ============================================================

def ajax_search_quiz(request):
    titolo   = request.GET.get('titolo', '').strip()
    creatore = request.GET.get('creatore', '').strip()
    stato    = request.GET.get('stato', '').strip()
    dal      = request.GET.get('dal', '').strip()
    al       = request.GET.get('al', '').strip()
    sort, dire = _parse_sort(request,
        {'codice', 'titolo', 'creatore', 'dataInizio', 'dataFine', 'num_domande', 'num_partecipazioni'},
        'dataFine', 'desc'
    )
    oggi = date.today().isoformat()

    sql = """
        SELECT q.codice, q.titolo, q.dataInizio, q.dataFine, q.creatore,
               u.nome, u.cognome, u.eMail,
               COUNT(DISTINCT d.numero) AS num_domande,
               COUNT(DISTINCT p.codice) AS num_partecipazioni,
               ROUND(AVG(COALESCE(punteggi.tot, 0)), 1) AS punteggio_medio
        FROM Quiz q
        JOIN Utente u ON q.creatore = u.nomeUtente
        LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
        LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
        LEFT JOIN (
            SELECT p1.codicePartecipazione, SUM(r1.punteggio) AS tot
            FROM RispostaUtenteQuiz p1
            JOIN Risposta r1 ON p1.numeroRisposta = r1.numero
                            AND p1.numeroDomanda = r1.numeroDomanda
                            AND p1.codiceQuiz = r1.codiceQuiz
            WHERE NOT EXISTS (
                SELECT 1 FROM RispostaUtenteQuiz p2
                JOIN Risposta r2 ON p2.numeroRisposta = r2.numero
                                AND p2.numeroDomanda = r2.numeroDomanda
                                AND p2.codiceQuiz = r2.codiceQuiz
                WHERE p2.codicePartecipazione = p1.codicePartecipazione
                  AND p2.numeroDomanda = p1.numeroDomanda
                  AND r2.punteggio IS NULL
            )
            GROUP BY p1.codicePartecipazione
        ) punteggi ON punteggi.codicePartecipazione = p.codice
        WHERE 1=1
    """
    params = []

    if titolo:
        sql += " AND q.titolo LIKE ?"
        params.append(f'%{titolo}%')
    if creatore:
        m = re.search(r'\(([^)]+)\)\s*$', creatore)
        if m:
            sql += " AND q.creatore = ?"
            params.append(m.group(1))
        else:
            sql += " AND (u.nome LIKE ? OR u.cognome LIKE ? OR q.creatore LIKE ?)"
            like = f'%{creatore}%'
            params += [like, like, like]
    if dal:
        sql += " AND q.dataInizio >= ?"
        params.append(dal)
    if al:
        sql += " AND q.dataFine <= ?"
        params.append(al)
    if stato:
        stati = stato.split(',')
        conds = []
        if 'aperto' in stati:
            conds.append("(q.dataInizio <= ? AND q.dataFine >= ?)")
            params += [oggi, oggi]
        if 'chiuso' in stati:
            conds.append("(q.dataFine < ?)")
            params.append(oggi)
        if 'futuro' in stati:
            conds.append("(q.dataInizio > ?)")
            params.append(oggi)
        if conds:
            sql += " AND (" + " OR ".join(conds) + ")"

    sql += " GROUP BY q.codice"

    col_map = {
        'codice': 'q.codice', 'titolo': 'q.titolo', 'creatore': 'q.creatore',
        'dataInizio': 'q.dataInizio', 'dataFine': 'q.dataFine',
        'num_domande': 'num_domande', 'num_partecipazioni': 'num_partecipazioni',
    }
    order_col = col_map.get(sort, 'q.dataFine')
    sql += f" ORDER BY {order_col} {'ASC' if dire == 'asc' else 'DESC'}"

    quiz_list = esegui_query(sql, params)
    oggi_str = date.today().isoformat()
    for q in quiz_list:
        d_ini = q.get('dataInizio') or ''
        d_fin = q.get('dataFine') or ''
        if isinstance(d_ini, str):
            q['aperto'] = d_ini <= oggi_str <= d_fin
            q['futuro'] = oggi_str < d_ini
        else:
            q['aperto'] = str(d_ini) <= oggi_str <= str(d_fin)
            q['futuro'] = oggi_str < str(d_ini)

    pagina = _pagina(request, quiz_list, RIGHE_PER_PAGINA_QUIZ)

    return render(request, 'quiz/ajax/tabella_quiz.html', {
        'quiz_list': pagina,
        'oggi':      oggi_str,
        'sort':      sort,
        'dir':       dire,
        'totale':    len(quiz_list),
    })


def ajax_search_quiz_cards(request):
    titolo   = request.GET.get('titolo', '').strip()
    creatore = request.GET.get('creatore', '').strip()
    stato    = request.GET.get('stato', '').strip()
    dal      = request.GET.get('dal', '').strip()
    al       = request.GET.get('al', '').strip()
    oggi     = date.today().isoformat()

    sql = """
        SELECT q.codice, q.titolo, q.dataInizio, q.dataFine, q.creatore,
               u.nome, u.cognome,
               COUNT(DISTINCT d.numero) AS num_domande,
               COUNT(DISTINCT p.codice) AS num_partecipazioni,
               ROUND(AVG(punteggi.tot), 1) AS punteggio_medio
        FROM Quiz q
        JOIN Utente u ON q.creatore = u.nomeUtente
        LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
        LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
        LEFT JOIN (
            SELECT calc_domande.codicePartecipazione, SUM(calc_domande.punti_domanda) AS tot
            FROM (
                SELECT ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda,
                       CASE WHEN SUM(CASE WHEN r_sub.punteggio IS NULL THEN 1 ELSE 0 END) > 0
                            THEN 0
                            ELSE SUM(r_sub.punteggio)
                       END AS punti_domanda
                FROM RispostaUtenteQuiz ruq_sub
                JOIN Risposta r_sub ON ruq_sub.numeroRisposta = r_sub.numero
                                   AND ruq_sub.numeroDomanda = r_sub.numeroDomanda
                                   AND ruq_sub.codiceQuiz = r_sub.codiceQuiz
                GROUP BY ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda
            ) AS calc_domande
            GROUP BY calc_domande.codicePartecipazione
        ) punteggi ON punteggi.codicePartecipazione = p.codice
        WHERE 1=1
    """
    params = []

    if titolo:
        sql += " AND q.titolo LIKE ?"
        params.append(f'%{titolo}%')
    if creatore:
        sql += " AND q.creatore = ?"
        params.append(creatore)
    if dal:
        sql += " AND q.dataInizio >= ?"
        params.append(dal)
    if al:
        sql += " AND q.dataFine <= ?"
        params.append(al)
    if stato == 'aperto':
        sql += " AND q.dataInizio <= ? AND q.dataFine >= ?"
        params += [oggi, oggi]
    elif stato == 'chiuso':
        sql += " AND q.dataFine < ?"
        params.append(oggi)
    elif stato == 'futuro':
        sql += " AND q.dataInizio > ?"
        params.append(oggi)

    sql += " GROUP BY q.codice ORDER BY q.dataFine DESC, q.titolo"

    quiz_list = esegui_query(sql, params)
    for q in quiz_list:
        d_ini = str(q.get('dataInizio') or '')
        d_fin = str(q.get('dataFine') or '')
        q['aperto'] = d_ini <= oggi <= d_fin
        q['futuro'] = oggi < d_ini

    return render(request, 'quiz/ajax/cards_quiz.html', {
        'quiz_list': quiz_list,
        'oggi':      oggi,
    })


def ajax_search_utenti(request):
    cognome   = request.GET.get('cognome', '').strip()
    nome      = request.GET.get('nome', '').strip()
    email     = request.GET.get('email', '').strip()
    username  = request.GET.get('username', '').strip()
    min_quiz  = request.GET.get('min_quiz', '').strip()
    max_quiz  = request.GET.get('max_quiz', '').strip()
    min_part  = request.GET.get('min_part', '').strip()
    max_part  = request.GET.get('max_part', '').strip()
    sort, dire = _parse_sort(request,
        {'nomeUtente', 'nome', 'cognome', 'eMail', 'quiz_creati', 'partecipazioni'},
        'cognome', 'asc'
    )

    sql = """
        SELECT u.nomeUtente, u.nome, u.cognome, u.eMail,
               COUNT(DISTINCT q.codice) AS quiz_creati,
               COUNT(DISTINCT p.codice) AS partecipazioni
        FROM Utente u
        LEFT JOIN Quiz q ON q.creatore = u.nomeUtente
        LEFT JOIN Partecipazione p ON p.nomeUtente = u.nomeUtente
        WHERE 1=1
    """
    params = []

    if cognome:
        sql += " AND u.cognome LIKE ?"
        params.append(f'%{cognome}%')
    if nome:
        sql += " AND u.nome LIKE ?"
        params.append(f'%{nome}%')
    if username:
        sql += " AND u.nomeUtente LIKE ?"
        params.append(f'%{username}%')
    if email:
        sql += " AND u.eMail LIKE ?"
        params.append(f'%{email}%')

    col_map = {
        'nomeUtente': 'u.nomeUtente', 'nome': 'u.nome', 'cognome': 'u.cognome',
        'eMail': 'u.eMail', 'quiz_creati': 'quiz_creati', 'partecipazioni': 'partecipazioni',
    }
    order_col = col_map.get(sort, 'u.cognome')
    sql += " GROUP BY u.nomeUtente"

    having_clauses = []
    if min_quiz:
        having_clauses.append("COUNT(DISTINCT q.codice) >= ?")
        params.append(int(min_quiz))
    if max_quiz:
        having_clauses.append("COUNT(DISTINCT q.codice) <= ?")
        params.append(int(max_quiz))
    if min_part:
        having_clauses.append("COUNT(DISTINCT p.codice) >= ?")
        params.append(int(min_part))
    if max_part:
        having_clauses.append("COUNT(DISTINCT p.codice) <= ?")
        params.append(int(max_part))

    if having_clauses:
        sql += " HAVING " + " AND ".join(having_clauses)

    sql += f" ORDER BY {order_col} {'ASC' if dire == 'asc' else 'DESC'}"

    utenti_list = esegui_query(sql, params)
    pagina = _pagina(request, utenti_list, RIGHE_PER_PAGINA_UTENTI)

    return render(request, 'quiz/ajax/tabella_utenti.html', {
        'utenti_list': pagina,
        'sort':        sort,
        'dir':         dire,
        'totale':      len(utenti_list),
    })


def ajax_search_partecipazioni(request):
    quiz_codice = request.GET.get('quiz_codice', '').strip()
    quiz_titolo = request.GET.get('quiz_titolo', '').strip()
    utente      = request.GET.get('utente', '').strip()
    perc_min    = request.GET.get('perc_min', '').strip()
    perc_max    = request.GET.get('perc_max', '').strip()
    data_dal    = request.GET.get('data_dal', '').strip()
    data_al     = request.GET.get('data_al', '').strip()
    sort, dire  = _parse_sort(request,
        {'codice', 'data', 'nomeUtente', 'codiceQuiz', 'quiz_titolo', 'percentuale'},
        'data', 'desc'
    )

    sql = """
        SELECT p.codice, p.data, p.nomeUtente, p.codiceQuiz,
               q.titolo AS quiz_titolo,
               u.nome AS utente_nome, u.cognome AS utente_cognome,
               punteggi_calcolati.tot AS punteggio_ottenuto,
               max_q.totale_possibile,
               CASE
                 WHEN max_q.totale_possibile > 0
                 THEN ROUND(COALESCE(punteggi_calcolati.tot, 0) * 100.0 / max_q.totale_possibile, 0)
                 ELSE 0
               END AS percentuale
        FROM Partecipazione p
        JOIN Quiz q ON p.codiceQuiz = q.codice
        JOIN Utente u ON p.nomeUtente = u.nomeUtente
        LEFT JOIN (
            SELECT calc_domande.codicePartecipazione, SUM(calc_domande.punti_domanda) AS tot
            FROM (
                SELECT ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda,
                       CASE WHEN SUM(CASE WHEN r_sub.punteggio IS NULL THEN 1 ELSE 0 END) > 0
                            THEN 0
                            ELSE SUM(r_sub.punteggio)
                       END AS punti_domanda
                FROM RispostaUtenteQuiz ruq_sub
                JOIN Risposta r_sub ON ruq_sub.numeroRisposta = r_sub.numero
                                   AND ruq_sub.numeroDomanda = r_sub.numeroDomanda
                                   AND ruq_sub.codiceQuiz = r_sub.codiceQuiz
                GROUP BY ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda
            ) AS calc_domande
            GROUP BY calc_domande.codicePartecipazione
        ) punteggi_calcolati ON punteggi_calcolati.codicePartecipazione = p.codice
        LEFT JOIN (
            SELECT codiceQuiz, SUM(punteggio) AS totale_possibile
            FROM Risposta
            WHERE punteggio IS NOT NULL
            GROUP BY codiceQuiz
        ) max_q ON max_q.codiceQuiz = p.codiceQuiz
        WHERE 1=1
    """
    params = []

    if quiz_codice:
        sql += " AND p.codiceQuiz = ?"
        params.append(int(quiz_codice))
    if quiz_titolo:
        sql += " AND q.titolo LIKE ?"
        params.append(f'%{quiz_titolo}%')
    if utente:
        m = re.search(r'\(([^)]+)\)\s*$', utente)
        if m:
            sql += " AND p.nomeUtente = ?"
            params.append(m.group(1))
        else:
            sql += " AND (u.nome LIKE ? OR u.cognome LIKE ? OR p.nomeUtente LIKE ?)"
            like = f'%{utente}%'
            params += [like, like, like]
    if data_dal:
        sql += " AND p.data >= ?"
        params.append(data_dal)
    if data_al:
        sql += " AND p.data <= ?"
        params.append(data_al)

    col_map = {
        'codice': 'p.codice', 'data': 'p.data', 'nomeUtente': 'p.nomeUtente',
        'codiceQuiz': 'p.codiceQuiz', 'quiz_titolo': 'q.titolo', 'percentuale': 'percentuale',
    }
    order_col = col_map.get(sort, 'p.data')
    sql += f" ORDER BY {order_col} {'ASC' if dire == 'asc' else 'DESC'}"

    part_list_raw = esegui_query(sql, params)

    if perc_min or perc_max:
        filtered = []
        for p in part_list_raw:
            perc = p.get('percentuale') or 0
            if perc_min and float(perc) < float(perc_min):
                continue
            if perc_max and float(perc) > float(perc_max):
                continue
            filtered.append(p)
        part_list = filtered
    else:
        part_list = part_list_raw

    pagina = _pagina(request, part_list, RIGHE_PER_PAGINA_PART)

    return render(request, 'quiz/ajax/tabella_partecipazioni.html', {
        'part_list': pagina,
        'sort':      sort,
        'dir':       dire,
        'totale':    len(part_list),
    })


def ajax_set_utente_sessione(request):
    if request.method == 'POST':
        username = request.POST.get('username', '').strip()
        label    = request.POST.get('label', '').strip()
        if username:
            request.session['utente_attivo'] = username
            request.session['utente_label']  = label
        else:
            request.session.pop('utente_attivo', None)
            request.session.pop('utente_label', None)
        return JsonResponse({'username': username, 'label': label})
    return JsonResponse({'error': 'Metodo non consentito'}, status=405)
