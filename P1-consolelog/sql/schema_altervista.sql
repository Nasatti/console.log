-- ============================================================
-- DB11 Quiz - Schema per Altervista
-- Progetto Programmazione Web 2025-2026
-- ============================================================
-- ISTRUZIONI: Importare in phpMyAdmin tramite Importa > Scegli file.
-- Eseguire PRIMA di popolamento_completo.sql.
-- ============================================================

CREATE DATABASE IF NOT EXISTS my_quizconsolelog
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE my_quizconsolelog;

-- ============================================================
-- TABELLE
-- ============================================================

CREATE TABLE IF NOT EXISTS Utente (
  nomeUtente VARCHAR(50)  NOT NULL,
  nome       VARCHAR(50)  NOT NULL,
  cognome    VARCHAR(50)  NOT NULL,
  eMail      VARCHAR(100) NOT NULL,
  PRIMARY KEY (nomeUtente)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Quiz (
  codice     INT          NOT NULL AUTO_INCREMENT,
  titolo     VARCHAR(200) NOT NULL,
  dataInizio DATE         NOT NULL,
  dataFine   DATE         NOT NULL,
  creatore   VARCHAR(50)  NOT NULL,
  PRIMARY KEY (codice),
  FOREIGN KEY (creatore) REFERENCES Utente(nomeUtente) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Domanda (
  numero     INT  NOT NULL,
  codiceQuiz INT  NOT NULL,
  testo      TEXT NOT NULL,
  PRIMARY KEY (numero, codiceQuiz),
  FOREIGN KEY (codiceQuiz) REFERENCES Quiz(codice) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Risposta (
  numero         INT     NOT NULL,
  numeroDomanda  INT     NOT NULL,
  codiceQuiz     INT     NOT NULL,
  testo          TEXT    NOT NULL,
  tipo           ENUM('Corretta', 'Sbagliata') NOT NULL,
  punteggio      DECIMAL(5,2) NULL DEFAULT NULL,  -- NULL = risposta sbagliata
  PRIMARY KEY (numero, numeroDomanda, codiceQuiz),
  FOREIGN KEY (numeroDomanda, codiceQuiz) REFERENCES Domanda(numero, codiceQuiz) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_risposta_tipo CHECK (tipo = 'Corretta' AND punteggio IS NOT NULL OR tipo = 'Sbagliata' AND punteggio IS NULL)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Partecipazione (
  codice     INT         NOT NULL AUTO_INCREMENT,
  data       DATE        NOT NULL,
  nomeUtente VARCHAR(50) NOT NULL,
  codiceQuiz INT         NOT NULL,
  PRIMARY KEY (codice),
  FOREIGN KEY (nomeUtente) REFERENCES Utente(nomeUtente) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (codiceQuiz) REFERENCES Quiz(codice) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS RispostaUtenteQuiz (
  codicePartecipazione INT NOT NULL,
  numeroRisposta       INT NOT NULL,
  numeroDomanda        INT NOT NULL,
  codiceQuiz           INT NOT NULL,
  PRIMARY KEY (codicePartecipazione, numeroRisposta, numeroDomanda, codiceQuiz),
  FOREIGN KEY (codicePartecipazione) REFERENCES Partecipazione(codice) ON UPDATE CASCADE ON DELETE CASCADE,
  FOREIGN KEY (numeroRisposta, numeroDomanda, codiceQuiz) REFERENCES Risposta(numero, numeroDomanda, codiceQuiz) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
