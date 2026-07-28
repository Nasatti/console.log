"""
URL dell'applicazione quiz.

Tabella di corrispondenza con i file PHP originali:
  index.php               → /
  quiz.php                → /quiz/
  quiz_detail.php?id=5    → /quiz/5/          (prima: /quiz/dettaglio/?quiz_id=5)
  quiz_form.php           → /quiz/nuovo/
  quiz_form.php?id=5      → /quiz/5/modifica/ (prima: /quiz/modifica/?quiz_id=5)
  quiz_delete.php?id=5    → /quiz/5/elimina/  (prima: /quiz/elimina/?quiz_id=5)
  partecipa.php?id=5      → /quiz/5/partecipa/(prima: /quiz/partecipa/?quiz_id=5)
  risultato.php?p=3       → /risultato/3/     (prima: /risultato/?part_id=3)
  utenti.php              → /utenti/
  partecipazioni.php      → /partecipazioni/
  ajax/search_quiz.php         → /ajax/quiz/
  ajax/search_utenti.php       → /ajax/utenti/
  ajax/search_partecipazioni.php → /ajax/partecipazioni/
  ajax/set_session_user.php    → /ajax/utente-sessione/
  ajax/search_quiz_cards.php   → /ajax/quiz-cards/

Miglioria rispetto alla versione precedente:
  I parametri quiz_id e part_id sono ora nella path URL (REST-like)
  invece che come parametri GET (?quiz_id=5). Questo è il pattern
  idiomatico Django e rende i link più leggibili e bookmarkabili.
"""

from django.urls import path
from . import views

urlpatterns = [
    # Pagina principale (home)
    path('', views.home, name='home'),

    # Lista quiz con filtri
    path('quiz/', views.quiz_lista, name='quiz_lista'),

    # Form per creare un quiz nuovo
    path('quiz/nuovo/', views.quiz_form, name='quiz_nuovo'),

    # Dettaglio di un quiz specifico (ID nella path)
    path('quiz/<int:quiz_id>/', views.quiz_dettaglio, name='quiz_dettaglio'),

    # Form per modificare un quiz esistente (ID nella path)
    path('quiz/<int:quiz_id>/modifica/', views.quiz_form_modifica, name='quiz_modifica'),

    # Pagina di conferma eliminazione quiz (ID nella path)
    path('quiz/<int:quiz_id>/elimina/', views.quiz_elimina, name='quiz_elimina'),

    # Pagina per svolgere il quiz (ID nella path)
    path('quiz/<int:quiz_id>/partecipa/', views.partecipa, name='partecipa'),

    # Risultato di una partecipazione specifica (ID nella path)
    path('risultato/<int:part_id>/', views.risultato, name='risultato'),

    # Lista utenti
    path('utenti/', views.utenti, name='utenti'),

    # Lista partecipazioni
    path('partecipazioni/', views.partecipazioni, name='partecipazioni'),

    # Endpoint AJAX: restituiscono HTML parziale per aggiornare la pagina senza ricaricarla
    path('ajax/quiz/',             views.ajax_search_quiz,            name='ajax_search_quiz'),
    path('ajax/quiz-cards/',       views.ajax_search_quiz_cards,      name='ajax_search_quiz_cards'),
    path('ajax/utenti/',           views.ajax_search_utenti,          name='ajax_search_utenti'),
    path('ajax/partecipazioni/',   views.ajax_search_partecipazioni,  name='ajax_search_partecipazioni'),
    path('ajax/utente-sessione/',  views.ajax_set_utente_sessione,    name='ajax_set_utente_sessione'),
]
