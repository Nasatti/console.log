<?php
// ============================================================
// quiz_form.php - Creazione e Modifica Quiz (CREATE + UPDATE)
// ============================================================
require_once 'db.php';

$pdo = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$quiz = null;
$domande = [];
$msg = '';
$msgType = '';

// Carica utenti
$utenti = $pdo->query("SELECT nomeUtente, nome, cognome FROM Utente ORDER BY cognome, nome")->fetchAll();

// Se modifica, carica dati esistenti
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM Quiz WHERE codice = ?");
    $stmt->execute([$id]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: quiz.php'); exit; }

    $dStmt = $pdo->prepare("SELECT * FROM Domanda WHERE codiceQuiz = ? ORDER BY numero");
    $dStmt->execute([$id]);
    $domande = $dStmt->fetchAll();

    foreach ($domande as &$dom) {
        $rStmt = $pdo->prepare("SELECT * FROM Risposta WHERE numeroDomanda = ? AND codiceQuiz = ? ORDER BY numero");
        $rStmt->execute([$dom['numero'], $id]);
        $dom['risposte'] = $rStmt->fetchAll();
    }
    unset($dom);
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titolo    = trim($_POST['titolo'] ?? '');
    $dataInizio= trim($_POST['dataInizio'] ?? '');
    $dataFine  = trim($_POST['dataFine'] ?? '');
    $creatore  = trim($_POST['creatore'] ?? '');

    if (!$titolo || !$dataInizio || !$dataFine || !$creatore) {
        $msg = 'Tutti i campi obbligatori devono essere compilati.';
        $msgType = 'error';
    } elseif ($dataFine < $dataInizio) {
        $msg = 'La data di fine deve essere successiva alla data di inizio.';
        $msgType = 'error';
    } else {
        // Validazione domande: almeno una risposta corretta per ogni domanda
        $domandePost = $_POST['domande'] ?? [];
        $validQuestions = true;
        $errorMsg = '';
        
        if (empty($domandePost)) {
            $validQuestions = false;
            $errorMsg = 'Il quiz deve contenere almeno una domanda.';
        } else {
            $dNum = 1;
            foreach ($domandePost as $d) {
                $testoD = trim($d['testo'] ?? '');
                if (!$testoD) {
                    $dNum++;
                    continue; 
                }
                
                $risposte = $d['risposte'] ?? [];
                $hasCorrect = false;
                foreach ($risposte as $r) {
                    $testoR = trim($r['testo'] ?? '');
                    if (!$testoR) continue;
                    $punteggio = trim($r['punteggio'] ?? '');
                    if ($punteggio !== '' && is_numeric($punteggio) && (float)$punteggio > 0) {
                        $hasCorrect = true;
                        break;
                    }
                }
                if (!$hasCorrect) {
                    $validQuestions = false;
                    $errorMsg = "La domanda #$dNum deve avere almeno una risposta corretta (con punteggio specificato maggiore di 0).";
                    break;
                }
                $dNum++;
            }
        }

        if (!$validQuestions) {
            $msg = $errorMsg;
            $msgType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                if ($isEdit) {
                    // UPDATE
                    $stmt = $pdo->prepare("UPDATE Quiz SET titolo=?, dataInizio=?, dataFine=?, creatore=? WHERE codice=?");
                    $stmt->execute([$titolo, $dataInizio, $dataFine, $creatore, $id]);
                    // Elimina domande/risposte vecchie (CASCADE elimina le risposte)
                    $pdo->prepare("DELETE FROM Domanda WHERE codiceQuiz = ?")->execute([$id]);
                    $quizId = $id;
                } else {
                    // INSERT
                    $stmt = $pdo->prepare("INSERT INTO Quiz (titolo, dataInizio, dataFine, creatore) VALUES (?,?,?,?)");
                    $stmt->execute([$titolo, $dataInizio, $dataFine, $creatore]);
                    $quizId = $pdo->lastInsertId();
                }

                // Salva domande e risposte
                $numDomanda = 1;
                foreach ($domandePost as $d) {
                    $testoD = trim($d['testo'] ?? '');
                    if (!$testoD) continue;

                    $dStmt = $pdo->prepare("INSERT INTO Domanda (numero, codiceQuiz, testo) VALUES (?,?,?)");
                    $dStmt->execute([$numDomanda, $quizId, $testoD]);

                    $risposte = $d['risposte'] ?? [];
                    $numRisposta = 1;
                    foreach ($risposte as $r) {
                        $testoR = trim($r['testo'] ?? '');
                        if (!$testoR) continue;
                        $punteggio = trim($r['punteggio'] ?? '');
                        // Il vincolo chk_risposta_tipo richiede punteggio > 0 oppure NULL
                        $punteggio = ($punteggio !== '' && is_numeric($punteggio) && (float)$punteggio > 0) ? (float)$punteggio : null;

                        // Determina il tipo in base al punteggio per rispettare il vincolo chk_risposta_tipo
                        $tipo = ($punteggio !== null) ? 'Corretta' : 'Sbagliata';

                        $rStmt = $pdo->prepare("INSERT INTO Risposta (numero, numeroDomanda, codiceQuiz, testo, tipo, punteggio) VALUES (?,?,?,?,?,?)");
                        $rStmt->execute([$numRisposta, $numDomanda, $quizId, $testoR, $tipo, $punteggio]);
                        $numRisposta++;
                    }
                    $numDomanda++;
                }

                $pdo->commit();
                header("Location: quiz_detail.php?id=$quizId&msg=saved");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Errore durante il salvataggio: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

$pageTitle = $isEdit ? 'Modifica Quiz' : 'Nuovo Quiz';
$activeNav = 'nuovo';
$cssPath = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>ℹ️ Istruzioni</h2>
    <p style="font-size:0.82rem;color:#555;line-height:1.6">
      <?php if ($isEdit): ?>
        Stai <strong>modificando</strong> il quiz #<?= $id ?>.<br><br>
        Le domande esistenti verranno <strong>sostituite</strong> con quelle inserite di seguito.
      <?php else: ?>
        Compila il form per creare un <strong>nuovo quiz</strong>.<br><br>
        Aggiungi almeno una domanda con le relative risposte.<br><br>
        Le risposte con un punteggio sono <strong>corrette</strong>; quelle senza punteggio sono <strong>sbagliate</strong>.
      <?php endif; ?>
    </p>
    <hr style="border-color:var(--yellow-main);margin:1rem 0">
    <a href="quiz.php" class="btn btn-outline" style="width:100%;justify-content:center">
      ← Annulla
    </a>
  </aside>

  <main class="main-content">
    <div class="breadcrumb">
      <a href="quiz.php">Quiz</a>
      <span>›</span>
      <?= $isEdit ? 'Modifica Quiz #' . $id : 'Nuovo Quiz' ?>
    </div>

    <div class="page-title">
      <h2><?= $isEdit ? '✏️ Modifica Quiz' : '➕ Nuovo Quiz' ?></h2>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" id="quiz-form">

      <!-- Dati principali -->
      <div class="form-section">
        <h3>📋 Dati del Quiz</h3>

        <div class="form-group">
          <label for="titolo">Titolo <span class="req-star">*</span></label>
          <input type="text" id="titolo" name="titolo" required maxlength="200"
                 value="<?= htmlspecialchars($quiz['titolo'] ?? '') ?>"
                 placeholder="Es. Cultura Generale - Livello Base">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="dataInizio">Data Inizio <span class="req-star">*</span></label>
            <input type="date" id="dataInizio" name="dataInizio" required
                   value="<?= htmlspecialchars($quiz['dataInizio'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="dataFine">Data Fine <span class="req-star">*</span></label>
            <input type="date" id="dataFine" name="dataFine" required
                   value="<?= htmlspecialchars($quiz['dataFine'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="creatore">Creatore <span class="req-star">*</span></label>
          <?php if ($isEdit): ?>
            <!-- In modifica: mostra il creatore attuale come select -->
            <select id="creatore" name="creatore" required>
              <option value="">— Seleziona utente —</option>
              <?php foreach ($utenti as $u): ?>
                <option value="<?= htmlspecialchars($u['nomeUtente']) ?>"
                  <?= (isset($quiz['creatore']) && $quiz['creatore'] === $u['nomeUtente']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['nome'] . ' ' . $u['cognome'] . ' (' . $u['nomeUtente'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <!-- In creazione: usa l'utente selezionato nell'header -->
            <input type="hidden" name="creatore" id="creatore-hidden">
            <div id="creatore-banner" style="padding: 8px 0; font-size:0.95rem;"></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Domande -->
      <div class="form-section">
        <h3>❓ Domande e Risposte</h3>

        <div id="domande-container">
          <?php if (!empty($domande)): ?>
            <?php foreach ($domande as $i => $dom): ?>
              <?php
                $dIdx = $i;
                $rArray = $dom['risposte'];
              ?>
              <?= domandaHTML($dIdx, htmlspecialchars($dom['testo'], ENT_QUOTES), $rArray) ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <button type="button" class="btn btn-secondary" id="add-domanda">
          ➕ Aggiungi Domanda
        </button>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end">
        <a href="quiz.php" class="btn btn-outline">Annulla</a>
        <button type="submit" class="btn btn-primary btn-lg">
          💾 <?= $isEdit ? 'Salva Modifiche' : 'Crea Quiz' ?>
        </button>
      </div>

    </form>
  </main>
</div>

<?php include 'footer.php'; ?>

<?php
function domandaHTML(int $idx, string $testo = '', array $risposte = []): string {
    $html = <<<HTML
    <div class="domanda-block" data-idx="$idx">
      <div class="domanda-block-header">
        <span>Domanda #<span class="d-num"></span></span>
        <button type="button" class="btn btn-danger btn-sm remove-domanda">✕ Rimuovi</button>
      </div>
      <div class="form-group">
        <label>Testo della domanda <span class="req-star">*</span></label>
        <textarea name="domande[$idx][testo]" rows="2" placeholder="Inserisci la domanda..." required>$testo</textarea>
      </div>
      <div class="risposte-container">
HTML;

    $risiLen = !empty($risposte) ? count($risposte) : 2;
    for ($r = 0; $r < $risiLen; $r++) {
        $rt = htmlspecialchars($risposte[$r]['testo'] ?? '', ENT_QUOTES);
        $rp = $risposte[$r]['punteggio'] ?? '';
        $html .= rispuestaHTML($idx, $r, $rt, $rp);
    }

    $html .= <<<HTML
      </div>
      <button type="button" class="btn btn-outline btn-sm add-risposta" style="margin-top:8px">➕ Aggiungi Risposta</button>
    </div>
HTML;
    return $html;
}

function rispuestaHTML(int $dIdx, int $rIdx, string $testo = '', $punteggio = ''): string {
    return <<<HTML
    <div class="risposta-block">
      <input type="text" name="domande[$dIdx][risposte][$rIdx][testo]"
             value="$testo" placeholder="Testo risposta..." required>
      <label>Punteggio (corretta):</label>
      <input type="number" name="domande[$dIdx][risposte][$rIdx][punteggio]"
             value="$punteggio" placeholder="lascia vuoto se sbagliata"
             step="0.5" min="0.5" class="punteggio-input">
      <button type="button" class="btn btn-danger btn-sm remove-risposta">✕</button>
    </div>
HTML;
}
?>

<script>
let domandaCount = <?= !empty($domande) ? count($domande) : 0 ?>;
const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;

function renumberDomande() {
  $('#domande-container .domanda-block').each(function(i) {
    $(this).attr('data-idx', i);
    $(this).find('.d-num').text(i + 1);
    // Aggiorna name attributes
    $(this).find('[name]').each(function () {
      let n = $(this).attr('name').replace(/domande\[\d+\]/, 'domande[' + i + ']');
      $(this).attr('name', n);
    });
  });
  domandaCount = $('#domande-container .domanda-block').length;
}

function renumberRisposte($domanda) {
  $domanda.find('.risposta-block').each(function(i) {
    let dIdx = $domanda.attr('data-idx');
    $(this).find('[name]').each(function () {
      let n = $(this).attr('name').replace(/risposte\[\d+\]/, 'risposte[' + i + ']');
      $(this).attr('name', n);
    });
  });
}

function addDomanda() {
  const idx = domandaCount;
  const html = `
    <div class="domanda-block" data-idx="${idx}">
      <div class="domanda-block-header">
        <span>Domanda #<span class="d-num">${idx + 1}</span></span>
        <button type="button" class="btn btn-danger btn-sm remove-domanda">✕ Rimuovi</button>
      </div>
      <div class="form-group">
        <label>Testo della domanda <span class="req-star">*</span></label>
        <textarea name="domande[${idx}][testo]" rows="2" placeholder="Inserisci la domanda..." required></textarea>
      </div>
      <div class="risposte-container">
        ${addRisposta(idx, 0)}
        ${addRisposta(idx, 1)}
      </div>
      <button type="button" class="btn btn-outline btn-sm add-risposta" style="margin-top:8px">➕ Aggiungi Risposta</button>
    </div>`;
  $('#domande-container').append(html);
  domandaCount++;
}

function addRisposta(dIdx, rIdx) {
  return `
    <div class="risposta-block">
      <input type="text" name="domande[${dIdx}][risposte][${rIdx}][testo]" placeholder="Testo risposta..." required>
      <label>Punteggio (corretta):</label>
      <input type="number" name="domande[${dIdx}][risposte][${rIdx}][punteggio]"
             placeholder="vuoto=sbagliata" step="0.5" min="0.5" class="punteggio-input">
      <button type="button" class="btn btn-danger btn-sm remove-risposta">✕</button>
    </div>`;
}

$(document).ready(function () {
  // Numera domande esistenti
  renumberDomande();

  $('#add-domanda').on('click', function () {
    addDomanda();
    renumberDomande();
  });

  $(document).on('click', '.remove-domanda', function () {
    if ($('.domanda-block').length <= 1) {
      alert('Il quiz deve avere almeno una domanda.');
      return;
    }
    $(this).closest('.domanda-block').remove();
    renumberDomande();
  });

  $(document).on('click', '.add-risposta', function () {
    const $domanda = $(this).closest('.domanda-block');
    const dIdx = $domanda.attr('data-idx');
    const rIdx = $domanda.find('.risposta-block').length;
    $domanda.find('.risposte-container').append(addRisposta(dIdx, rIdx));
    renumberRisposte($domanda);
  });

  $(document).on('click', '.remove-risposta', function () {
    const $domanda = $(this).closest('.domanda-block');
    if ($domanda.find('.risposta-block').length <= 2) {
      alert('Ogni domanda deve avere almeno 2 risposte.');
      return;
    }
    $(this).closest('.risposta-block').remove();
    renumberRisposte($domanda);
  });

  // Validazione prima del submit
  $('#quiz-form').on('submit', function (e) {
    <?php if (!$isEdit): ?>
    // In create mode: controlla che il creatore sia stato scelto
    var creatore = $('#creatore-hidden').val();
    if (!creatore) {
      e.preventDefault();
      alert('Seleziona prima il tuo utente tramite il selettore in alto a destra.');
      return;
    }
    <?php endif; ?>
    if ($('.domanda-block').length === 0) {
      e.preventDefault();
      alert('Aggiungi almeno una domanda al quiz.');
      return;
    }

    // Controlla che ogni domanda abbia almeno una risposta con punteggio (corretta)
    var allValid = true;
    $('#domande-container .domanda-block').each(function (i) {
      var hasCorrect = false;
      $(this).find('.punteggio-input').each(function () {
        var val = $(this).val().trim();
        if (val !== '' && !isNaN(val) && parseFloat(val) > 0) {
          hasCorrect = true;
        }
      });
      if (!hasCorrect) {
        alert('La domanda #' + (i + 1) + ' deve avere almeno una risposta corretta (con punteggio specificato maggiore di 0).');
        allValid = false;
        return false; // Break $.each
      }
    });

    if (!allValid) {
      e.preventDefault();
      return;
    }

    if (isEditMode) {
      if (!confirm('Sei sicuro di voler salvare le modifiche al quiz?\nLe domande esistenti verranno sostituite.')) {
        e.preventDefault();
      }
    }
  });
});

<?php if (!$isEdit): ?>
// Popola il campo creatore da sessionStorage (solo in create mode)
function syncCreatore(utenteAttivo, utenteLabel) {
  var $hidden  = $('#creatore-hidden');
  var $banner  = $('#creatore-banner');
  if (utenteAttivo) {
    $hidden.val(utenteAttivo);
    $banner.html(
      '<span style="font-weight:700;color:var(--dark);">' +
      (utenteLabel ? utenteLabel : '@' + utenteAttivo) +
      '</span>' +
      ' &nbsp;<span style="font-size:0.8rem;color:var(--gray)">— selezionato in alto a destra</span>'
    );
    $banner.closest('.form-group').css('border-left','4px solid var(--success)').css('padding-left','10px');
  } else {
    $hidden.val('');
    $banner.html(
      '<span style="color:var(--danger);font-weight:600;">⚠ Nessun utente selezionato.</span>' +
      '<br><span style="font-size:0.82rem;color:var(--gray)">Seleziona il tuo utente nel selettore in alto a destra.</span>'
    );
    $banner.closest('.form-group').css('border-left','4px solid var(--danger)').css('padding-left','10px');
  }
}

// Inizializzazione
syncCreatore(
  sessionStorage.getItem('utenteAttivo') || '',
  sessionStorage.getItem('utenteAttivoLabel') || ''
);

// Ascolta il cambio utente attivo dall'header
window.addEventListener('utenteAttivoChanged', function(e) {
  syncCreatore(e.detail.username, e.detail.label);
});
<?php endif; ?>
</script>
