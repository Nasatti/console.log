"""
Modelli del database.

Ogni classe qui corrisponde a una tabella del database SQLite.
Con managed=True (default), Django crea e gestisce le tabelle
tramite le migrazioni.

Struttura del DB:
  Utente → Quiz → Domanda → Risposta
                ↘ Partecipazione → RispostaUtenteQuiz

Miglioramenti rispetto alla versione precedente:
  - @property sui modelli per incapsulare la logica di business
    (stato quiz, permessi di modifica/eliminazione)
  - clean() su Quiz per validazione a livello modello
  - __str__ su tutti i modelli per debugging
"""

from datetime import date
from django.db import models
from django.core.exceptions import ValidationError


class Utente(models.Model):
    # Chiave primaria: il nome utente (stringa, non numero)
    nomeUtente = models.CharField(max_length=50, primary_key=True)
    nome       = models.CharField(max_length=50)
    cognome    = models.CharField(max_length=50)
    eMail      = models.CharField(max_length=100)

    class Meta:
        db_table = 'Utente'

    def __str__(self):
        return f'{self.nome} {self.cognome} (@{self.nomeUtente})'


class Quiz(models.Model):
    codice     = models.AutoField(primary_key=True)
    titolo     = models.CharField(max_length=200)
    dataInizio = models.DateField()
    dataFine   = models.DateField()
    # ForeignKey: ogni quiz ha un creatore che è un Utente
    creatore   = models.ForeignKey(
        Utente,
        db_column='creatore',
        on_delete=models.RESTRICT  # non si può eliminare un utente se ha quiz
    )

    class Meta:
        db_table = 'Quiz'

    def __str__(self):
        return self.titolo

    def clean(self):
        """Validazione a livello modello: dataFine non può precedere dataInizio."""
        super().clean()
        if self.dataInizio and self.dataFine and self.dataFine < self.dataInizio:
            raise ValidationError(
                "La data di fine non può essere precedente alla data di inizio."
            )

    # ── Proprietà calcolate ──────────────────────────────────────────────────

    @property
    def stato(self):
        """Stato calcolato: 'aperto', 'chiuso' o 'futuro'."""
        oggi = date.today()
        if self.dataInizio and self.dataFine:
            if self.dataInizio <= oggi <= self.dataFine:
                return 'aperto'
            elif oggi < self.dataInizio:
                return 'futuro'
        return 'chiuso'

    @property
    def is_aperto(self):
        return self.stato == 'aperto'

    @property
    def is_futuro(self):
        return self.stato == 'futuro'

    @property
    def num_partecipazioni(self):
        """Numero di partecipazioni al quiz."""
        return self.partecipazione_set.count()

    @property
    def puo_essere_modificato(self):
        """Un quiz è modificabile solo se non ha partecipazioni e non è ancora iniziato."""
        return self.num_partecipazioni == 0 and self.is_futuro

    @property
    def puo_essere_eliminato(self):
        """Un quiz è eliminabile solo se non ha partecipazioni e non è ancora iniziato."""
        return self.num_partecipazioni == 0 and self.is_futuro


class Domanda(models.Model):
    numero     = models.IntegerField()
    # ForeignKey: ogni domanda appartiene a un quiz
    codiceQuiz = models.ForeignKey(
        Quiz,
        db_column='codiceQuiz',
        on_delete=models.CASCADE  # se si elimina il quiz, si eliminano le domande
    )
    testo      = models.TextField()

    class Meta:
        db_table    = 'Domanda'
        unique_together = [('numero', 'codiceQuiz')]  # chiave primaria composta

    def __str__(self):
        return f'Dom. {self.numero} del Quiz #{self.codiceQuiz_id}'


class Risposta(models.Model):
    TIPO_CHOICES = [
        ('Corretta',  'Corretta'),
        ('Sbagliata', 'Sbagliata'),
    ]
    numero        = models.IntegerField()
    numeroDomanda = models.IntegerField()
    codiceQuiz    = models.IntegerField()  # gestito manualmente per la PK composta
    testo         = models.TextField()
    tipo          = models.CharField(max_length=10, choices=TIPO_CHOICES, default='Sbagliata')
    # punteggio: null se la risposta è sbagliata, un valore se corretta
    punteggio     = models.DecimalField(max_digits=5, decimal_places=2, null=True, blank=True)

    class Meta:
        db_table    = 'Risposta'
        unique_together = [('numero', 'numeroDomanda', 'codiceQuiz')]

    def __str__(self):
        return f'Risp. {self.numero} - Dom. {self.numeroDomanda}'

    @property
    def is_corretta(self):
        """True se la risposta è di tipo Corretta."""
        return self.tipo == 'Corretta'


class Partecipazione(models.Model):
    codice     = models.AutoField(primary_key=True)
    data       = models.DateField()
    nomeUtente = models.ForeignKey(
        Utente,
        db_column='nomeUtente',
        on_delete=models.RESTRICT
    )
    codiceQuiz = models.ForeignKey(
        Quiz,
        db_column='codiceQuiz',
        on_delete=models.CASCADE
    )

    class Meta:
        db_table = 'Partecipazione'

    def __str__(self):
        return f'Part. #{self.codice} — {self.nomeUtente_id} su Quiz #{self.codiceQuiz_id}'


class RispostaUtenteQuiz(models.Model):
    # Tutti e 4 i campi formano la chiave primaria
    codicePartecipazione = models.IntegerField()
    numeroRisposta       = models.IntegerField()
    numeroDomanda        = models.IntegerField()
    codiceQuiz           = models.IntegerField()

    class Meta:
        db_table    = 'RispostaUtenteQuiz'
        unique_together = [('codicePartecipazione', 'numeroRisposta', 'numeroDomanda', 'codiceQuiz')]

    def __str__(self):
        return f'RispostaUtente: Part#{self.codicePartecipazione} Risp#{self.numeroRisposta} Dom#{self.numeroDomanda}'
