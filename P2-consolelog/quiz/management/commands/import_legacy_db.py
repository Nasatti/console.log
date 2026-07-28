"""
Comando di management Django: import_legacy_db

Uso:
    py manage.py import_legacy_db
    py manage.py import_legacy_db --file percorso/al/file.sql

Legge il file SQL del progetto originale (MySQL) e inserisce
i dati nel database SQLite locale, riga per riga.
"""

import os
import re
from django.core.management.base import BaseCommand
from django.db import connection, transaction


class Command(BaseCommand):
    help = 'Importa i dati dal file SQL del progetto originale nel database SQLite locale.'

    def add_arguments(self, parser):
        parser.add_argument(
            '--file',
            default=None,
            help='Percorso al file SQL di popolamento (default: consolelog/sql/popolamento_esteso.sql)',
        )

    def handle(self, *args, **options):
        # Percorso di default del file SQL
        base_dir = os.path.dirname(os.path.dirname(os.path.dirname(
            os.path.dirname(os.path.abspath(__file__))
        )))
        default_sql = os.path.join(base_dir, 'sql', 'popolamento_esteso.sql')
        sql_file = options['file'] or default_sql

        if not os.path.exists(sql_file):
            self.stderr.write(self.style.ERROR(
                f"File SQL non trovato: {sql_file}\n"
                f"Specifica il percorso con --file oppure assicurati che esista:\n{default_sql}"
            ))
            return

        self.stdout.write(f"Lettura file: {sql_file}")

        with open(sql_file, 'r', encoding='utf-8', errors='replace') as f:
            contenuto = f.read()

        # Controlla se ci sono già dati nel DB
        with connection.cursor() as cur:
            cur.execute("SELECT COUNT(*) FROM Utente")
            n_utenti = cur.fetchone()[0]
            if n_utenti > 0:
                self.stdout.write(self.style.WARNING(
                    f"Il database contiene già {n_utenti} utenti. Importazione saltata.\n"
                    "Per reimportare: cancella db.sqlite3 e riesegui 'migrate' + 'import_legacy_db'."
                ))
                return

        # Divide il testo in statement SQL separati da ";"
        # Rimuove commenti -- e righe USE/CREATE DATABASE
        righe_pulite = []
        for riga in contenuto.splitlines():
            r = riga.strip()
            if (r.startswith('--') or r.startswith('USE ') or
                    r.startswith('CREATE DATABASE') or r.startswith('CREATE TABLE') or
                    r == ''):
                continue
            righe_pulite.append(r)

        # Ricombina e divide per ";"
        testo_pulito = ' '.join(righe_pulite)
        statements_raw = testo_pulito.split(';')

        # Mantieni solo gli INSERT INTO
        statements = []
        for s in statements_raw:
            s = s.strip()
            if s.upper().startswith('INSERT INTO'):
                # Sostituisce MySQL backticks e rimuove ENGINE= etc.
                s = s.replace('`', '')
                s = s.replace("\\'", "''")
                statements.append(s)

        self.stdout.write(f"Trovati {len(statements)} statement INSERT da eseguire.")

        # Tabelle da importare nell'ordine corretto (rispettando le FK)
        ordine = ['Utente', 'Quiz', 'Domanda', 'Risposta', 'Partecipazione', 'RispostaUtenteQuiz']
        contatori = {t: 0 for t in ordine}
        errori = 0

        # Raggruppa gli statement per tabella per mantenere l'ordine
        per_tabella = {t: [] for t in ordine}
        for s in statements:
            m = re.match(r'INSERT\s+(?:IGNORE\s+)?INTO\s+(\w+)', s, re.IGNORECASE)
            if m:
                tabella = m.group(1)
                if tabella in per_tabella:
                    per_tabella[tabella].append(s)

        with transaction.atomic():
            for tabella in ordine:
                for stmt in per_tabella[tabella]:
                    # Adatta la sintassi MySQL → SQLite
                    # MySQL: INSERT INTO Tab (cols) VALUES (...), (...);
                    # SQLite >= 3.7.11 supporta multi-row VALUES, ma alcune versioni no.
                    # Esploriamo singoli INSERT per sicurezza.
                    try:
                        # Prova prima con l'INSERT multi-riga nativo
                        sqlite_stmt = self._mysql_to_sqlite(stmt)
                        with connection.cursor() as cur:
                            cur.execute(sqlite_stmt)
                        contatori[tabella] += 1
                    except Exception as e:
                        # Se fallisce, prova a splittare in INSERT singoli
                        try:
                            singoli = self._split_multirow_insert(stmt)
                            for s in singoli:
                                sqlite_s = self._mysql_to_sqlite(s)
                                try:
                                    with connection.cursor() as cur:
                                        cur.execute(sqlite_s)
                                    contatori[tabella] += 1
                                except Exception:
                                    errori += 1
                        except Exception:
                            errori += 1

        self.stdout.write(self.style.SUCCESS("\n=== Importazione completata ==="))
        for tab in ordine:
            self.stdout.write(f"  {tab}: {contatori[tab]} batch inseriti")
        if errori:
            self.stdout.write(self.style.WARNING(f"  Righe con errori (duplicate/ignorate): {errori}"))
        self.stdout.write(self.style.SUCCESS("Database pronto!"))

        # Verifica finale
        with connection.cursor() as cur:
            for tab in ordine:
                cur.execute(f"SELECT COUNT(*) FROM {tab}")
                n = cur.fetchone()[0]
                self.stdout.write(f"  Totale {tab}: {n}")

    def _mysql_to_sqlite(self, stmt):
        """Adatta un INSERT MySQL per SQLite: OR IGNORE, rimuove ENGINE/CHARSET."""
        # INSERT INTO → INSERT OR IGNORE INTO
        stmt = re.sub(r'^INSERT\s+INTO\s+', 'INSERT OR IGNORE INTO ', stmt, flags=re.IGNORECASE)
        stmt = stmt.replace("\\'", "''")
        return stmt.strip()

    def _split_multirow_insert(self, stmt):
        """Divide un INSERT multi-riga in INSERT singoli."""
        m = re.match(
            r'INSERT\s+(?:IGNORE\s+)?INTO\s+(\w+)\s*\(([^)]+)\)\s*VALUES\s*(.*)',
            stmt, re.IGNORECASE | re.DOTALL
        )
        if not m:
            return [stmt]

        tabella = m.group(1)
        colonne = m.group(2)
        valori_raw = m.group(3).strip()
        if valori_raw.endswith(';'):
            valori_raw = valori_raw[:-1]

        gruppi = self._split_value_groups(valori_raw)
        singoli = []
        for g in gruppi:
            g = g.strip()
            if g:
                singoli.append(f"INSERT OR IGNORE INTO {tabella} ({colonne}) VALUES {g}")
        return singoli

    def _split_value_groups(self, s):
        """Divide la stringa VALUES in gruppi (...) gestendo stringhe con virgole."""
        groups = []
        depth = 0
        current = []
        in_string = False
        i = 0
        while i < len(s):
            ch = s[i]
            if in_string:
                if ch == "'" and i + 1 < len(s) and s[i + 1] == "'":
                    # escaped quote ''
                    current.append("''")
                    i += 2
                    continue
                elif ch == "'" :
                    in_string = False
                    current.append(ch)
                else:
                    current.append(ch)
            else:
                if ch == "'":
                    in_string = True
                    current.append(ch)
                elif ch == '(':
                    depth += 1
                    current.append(ch)
                elif ch == ')':
                    depth -= 1
                    current.append(ch)
                    if depth == 0:
                        groups.append(''.join(current))
                        current = []
                elif ch == ',' and depth == 0:
                    pass  # separatore tra gruppi
                else:
                    current.append(ch)
            i += 1
        return groups
