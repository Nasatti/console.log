"""
URL principali del progetto.

Qui colleghiamo le URL della nostra app quiz.
Ogni URL corrisponde a un file .php del progetto originale.
"""

from django.urls import path, include

urlpatterns = [
    # Tutte le URL partono da qui e vengono delegate all'app quiz
    path('', include('quiz.urls')),
]
