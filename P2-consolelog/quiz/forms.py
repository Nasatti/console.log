"""
Forms del progetto console.log.

Centralizza la validazione dei dati di input seguendo il pattern Django:
ogni Form ha metodi clean_<campo>() che validano il singolo campo e
un clean() opzionale per validazioni cross-campo.

Forms disponibili:
  - QuizForm: creazione e modifica di un quiz (titolo, date, creatore)
  - PartecipaForm: invio di una partecipazione (selezione utente attivo)
"""

from datetime import date
from django import forms
from django.core.exceptions import ValidationError

from .models import Utente, Quiz, Partecipazione


class QuizForm(forms.Form):
    """Form per la creazione e la modifica di un quiz.

    Valida che:
    - Tutti i campi obbligatori siano compilati (gestito da required=True)
    - La data di fine non preceda la data di inizio
    - Il creatore esista come Utente nel database
    """
    titolo = forms.CharField(
        label='Titolo',
        max_length=200,
        widget=forms.TextInput(attrs={
            'class': 'form-control',
            'placeholder': 'Es. Quiz di Python avanzato',
        })
    )
    dataInizio = forms.DateField(
        label='Data inizio',
        widget=forms.DateInput(attrs={
            'type': 'date',
            'class': 'form-control',
        })
    )
    dataFine = forms.DateField(
        label='Data fine',
        widget=forms.DateInput(attrs={
            'type': 'date',
            'class': 'form-control',
        })
    )
    creatore = forms.CharField(
        label='Creatore',
        max_length=50,
        widget=forms.TextInput(attrs={
            'class': 'form-control',
            'placeholder': 'Username del creatore',
        })
    )

    def __init__(self, *args, quiz_id=None, **kwargs):
        """Accetta quiz_id per distinguere creazione da modifica."""
        super().__init__(*args, **kwargs)
        self._quiz_id = quiz_id  # usato in clean() per controlli specifici modifica

    def clean_creatore(self):
        """Verifica che il creatore esista come Utente nel database."""
        username = self.cleaned_data['creatore'].strip()
        if not Utente.objects.filter(nomeUtente=username).exists():
            raise ValidationError(
                f"Nessun utente trovato con username '{username}'."
            )
        return username

    def clean(self):
        """Validazione cross-campo: dataFine >= dataInizio."""
        cleaned = super().clean()
        data_inizio = cleaned.get('dataInizio')
        data_fine = cleaned.get('dataFine')

        if data_inizio and data_fine:
            if data_fine < data_inizio:
                raise ValidationError(
                    "La data di fine deve essere successiva o uguale alla data di inizio."
                )

            # In modifica: blocca se il quiz ha partecipazioni o è già iniziato
            if self._quiz_id is not None:
                try:
                    quiz = Quiz.objects.get(pk=self._quiz_id)
                    if quiz.num_partecipazioni > 0:
                        raise ValidationError(
                            "Non è possibile modificare un quiz che ha già partecipazioni registrate."
                        )
                    if quiz.stato != 'futuro':
                        raise ValidationError(
                            "Non è possibile modificare un quiz già iniziato o concluso."
                        )
                except Quiz.DoesNotExist:
                    raise ValidationError("Quiz non trovato.")

        return cleaned


class PartecipaForm(forms.Form):
    """Form per la sottomissione di una partecipazione.

    Valida che:
    - Un utente sia stato selezionato
    - L'utente esista nel database
    """
    nomeUtente = forms.CharField(
        label='Utente partecipante',
        max_length=50,
        widget=forms.HiddenInput()
    )

    def clean_nomeUtente(self):
        """Verifica che il partecipante esista nel database."""
        username = self.cleaned_data.get('nomeUtente', '').strip()
        if not username:
            raise ValidationError("Seleziona un utente prima di partecipare.")
        if not Utente.objects.filter(nomeUtente=username).exists():
            raise ValidationError(
                f"Utente '{username}' non trovato. Riseleziona il tuo profilo in alto."
            )
        return username
