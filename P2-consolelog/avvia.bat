@echo off
cd /d "%~dp0"
REM ================================================================
REM  console.log — Script di avvio (Progetto 2, Django + SQLite)
REM  Doppio clic su questo file per installare e avviare il progetto.
REM  Prima di eseguire: assicurati che Python 3.12+ sia installato.
REM ================================================================

echo ================================================================
echo   console.log — Progetto Programmazione Web 2025-2026
echo ================================================================
echo.

REM --- Passo 1: Ambiente virtuale ---
if not exist "venv\Scripts\activate.bat" (
    echo [1/4] Creazione ambiente virtuale Python...
    py -m venv venv
    if errorlevel 1 (
        echo ERRORE: Impossibile creare il virtual environment.
        echo Assicurati che Python 3.12+ sia installato e nel PATH.
        pause
        exit /b 1
    )
    echo       Ambiente virtuale creato!
) else (
    echo [1/4] Ambiente virtuale gia' presente.
)

REM --- Passo 2: Dipendenze ---
echo [2/4] Installazione dipendenze Python (potrebbe richiedere 1-2 min)...
venv\Scripts\python.exe -m pip install -r requirements.txt --quiet
if errorlevel 1 (
    echo ERRORE: Installazione dipendenze fallita.
    pause
    exit /b 1
)
echo       Dipendenze installate!

REM --- Passo 3: Database ---
echo [3/4] Configurazione database SQLite...
venv\Scripts\python.exe manage.py migrate --run-syncdb 2>nul
venv\Scripts\python.exe manage.py migrate
if errorlevel 1 (
    echo ERRORE: Migrazione database fallita.
    pause
    exit /b 1
)

REM Importa i dati solo se il DB e' vuoto
echo       Importazione dati...
venv\Scripts\python.exe manage.py import_legacy_db
echo       Database pronto!

REM --- Passo 4: Avvio server ---
echo [4/4] Avvio server locale...
echo.
echo ================================================================
echo   Il sito e' accessibile su: http://127.0.0.1:8000/
echo   Premi CTRL+C per fermare il server.
echo   Riapri questo file per riavviare.
echo ================================================================
echo.

venv\Scripts\python.exe manage.py runserver 8000
pause
