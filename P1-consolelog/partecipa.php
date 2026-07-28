<?php
// ============================================================
// partecipa.php - Svolgimento Quiz (Partecipazione)
// ============================================================
require_once 'db.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: quiz.php'); exit; }

$pdo = getDB();

// Carica il quiz
$stmt = $pdo->prepare("SELECT q.*, u.nome, u.cognome FROM Quiz q JOIN Utente u ON q.creatore = u.nomeUtente WHERE q.codice = ?");
$stmt->execute([$id]);
$quiz = $stmt->fetch();

if (!$quiz) { header('Location: quiz.php'); exit; }

$today = date('Y-m-d');
$aperto = ($today >= $quiz['dataInizio'] && $today <= $quiz['dataFine']);
if (!$aperto) {
    header("Location: quiz_detail.php?id=$id&msg=chiuso");
    exit;
}

// Carica domande
$domStmt = $pdo->prepare("SELECT d.numero, d.testo FROM Domanda d WHERE d.codiceQuiz = ? ORDER BY d.numero");
$domStmt->execute([$id]);
$domande = $domStmt->fetchAll();

if (empty($domande)) {
    header("Location: quiz_detail.php?id=$id&msg=nodom");
    exit;
}

// Carica tutti gli utenti per selezionare il partecipante
$utenti = $pdo->query("SELECT nomeUtente, nome, cognome FROM Utente ORDER BY cognome, nome")->fetchAll();

// Carica risposte per ogni domanda
foreach ($domande as &$dom) {
    $rStmt = $pdo->prepare("SELECT numero, testo FROM Risposta WHERE numeroDomanda = ? AND codiceQuiz = ? ORDER BY numero");
    $rStmt->execute([$dom['numero'], $id]);
    $dom['risposte'] = $rStmt->fetchAll();
}
unset($dom);

// Gestione POST: salva la partecipazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUtente = trim($_POST['nomeUtente'] ?? '');
    $risposte   = $_POST['risposte'] ?? [];

    if (!$nomeUtente) {
        $errUtente = 'Seleziona un partecipante.';
    } elseif (count($risposte) < count($domande)) {
        $errUtente = 'Devi rispondere a tutte le domande.';
    } else {
        // Verifica che tutte le risposte appartengano al quiz
        $valid = true;
        foreach ($risposte as $numDom => $scelte) {
            foreach ($scelte as $numRisp) {
                $check = $pdo->prepare("
                    SELECT 1 FROM Risposta
                    WHERE numero = ? AND numeroDomanda = ? AND codiceQuiz = ?
                ");
                $check->execute([$numRisp, $numDom, $id]);
                if (!$check->fetch()) { $valid = false; break 2; }
            }
        }

        if (!$valid) {
            $errUtente = 'Dati non validi. Riprova.';
        } else {
            try {
                $pdo->beginTransaction();

                // Crea la partecipazione
                $ins = $pdo->prepare("INSERT INTO Partecipazione (data, nomeUtente, codiceQuiz) VALUES (?,?,?)");
                $ins->execute([$today, $nomeUtente, $id]);
                $codPart = $pdo->lastInsertId();

                // Salva le opzioni scelte
                $insOpt = $pdo->prepare("INSERT INTO RispostaUtenteQuiz (codicePartecipazione, numeroRisposta, numeroDomanda, codiceQuiz) VALUES (?,?,?,?)");
                foreach ($risposte as $numDom => $scelte) {
                    foreach ($scelte as $numRisp) {
                        $insOpt->execute([$codPart, $numRisp, $numDom, $id]);
                    }
                }

                $pdo->commit();
                header("Location: risultato.php?p=$codPart");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errUtente = 'Errore: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Partecipa: ' . $quiz['titolo'];
$activeNav = 'quiz';
$cssPath = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>📋 Quiz</h2>
    <p style="font-size:0.9rem;font-weight:700;margin-bottom:.5rem"><?= htmlspecialchars($quiz['titolo']) ?></p>
    <p style="font-size:0.8rem;color:#666">
      👤 <?= htmlspecialchars($quiz['nome'] . ' ' . $quiz['cognome']) ?><br>
      ❓ <?= count($domande) ?> domande
    </p>
    <hr style="border-color:var(--yellow-main);margin:1rem 0">
    <div style="font-size:0.8rem;color:#555;line-height:1.6">
      <strong>Istruzioni:</strong><br>
      Seleziona il tuo nome utente. Più risposte possono essere corrette: spunta tutte le opzioni che ritieni valide.<br><br>
      Al termine premi <strong>"Consegna Quiz"</strong>.
    </div>
    <hr style="border-color:var(--yellow-main);margin:1rem 0">
    <a href="quiz_detail.php?id=<?= $id ?>" class="btn btn-outline" style="width:100%;justify-content:center">
      ← Torna al quiz
    </a>
  </aside>

  <main class="main-content">
    <div class="breadcrumb">
      <a href="quiz.php">Quiz</a>
      <span>›</span>
      <a href="quiz_detail.php?id=<?= $id ?>"><?= htmlspecialchars($quiz['titolo']) ?></a>
      <span>›</span>
      Partecipa
    </div>

    <div class="partecipa-wrapper">

      <?php if (isset($errUtente)): ?>
        <div class="alert alert-error">⚠ <?= htmlspecialchars($errUtente) ?></div>
      <?php endif; ?>

      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progress-fill" style="width:0%"></div>
      </div>

      <form method="POST" id="quiz-play-form">

        <!-- Partecipante prelevato dall'utente attivo dell'header -->
        <input type="hidden" name="nomeUtente" id="nomeUtente-hidden">
        <div class="form-section" style="margin-bottom:1.5rem" id="partecipante-banner">
          <h3>👤 Chi partecipa?</h3>
          <div id="partecipante-info" style="font-size:0.95rem; padding:8px 0;"></div>
        </div>

        <!-- Domande -->
        <?php foreach ($domande as $dom): ?>
          <div class="domanda-box" data-numdom="<?= $dom['numero'] ?>">
            <div style="font-size:0.78rem;color:var(--gray);margin-bottom:.5rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
              Domanda <?= $dom['numero'] ?> di <?= count($domande) ?>
            </div>
            <div class="domanda-testo"><?= htmlspecialchars($dom['testo']) ?></div>

            <div class="risposte-play">
              <?php foreach ($dom['risposte'] as $r): ?>
                <label class="risposta-radio">
                  <input type="checkbox"
                         name="risposte[<?= $dom['numero'] ?>][]"
                         value="<?= $r['numero'] ?>"
                         <?= (isset($_POST['risposte'][$dom['numero']]) && in_array($r['numero'], $_POST['risposte'][$dom['numero']])) ? 'checked' : '' ?>
                         onchange="updateProgress()">
                  <?= htmlspecialchars($r['testo']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:1rem">
          <a href="quiz_detail.php?id=<?= $id ?>" class="btn btn-outline">Annulla</a>
          <button type="submit" class="btn btn-success btn-lg" id="submit-btn">
            ✅ Consegna Quiz
          </button>
        </div>
      </form>
    </div>
  </main>
</div>

<?php include 'footer.php'; ?>

<script>
const totalDomande = <?= count($domande) ?>;

function updateProgress() {
  const rispAns = new Set($('input[type="checkbox"]:checked').map(function() { return this.name; }).get()).size;
  const pct = Math.round((rispAns / totalDomande) * 100);
  $('#progress-fill').css('width', pct + '%');
}

$(document).ready(function () {
  // --- Gestione utente attivo dall'header ---
  function syncPartecipante(utenteAttivo, utenteLabel) {
    var $hidden = $('#nomeUtente-hidden');
    var $info   = $('#partecipante-info');
    var $banner = $('#partecipante-banner');

    if (utenteAttivo) {
      $hidden.val(utenteAttivo);
      $info.html(
        '<span style="font-weight:700;color:var(--dark);">' +
        (utenteLabel ? utenteLabel : '@' + utenteAttivo) +
        '</span>' +
        ' &nbsp;<span style="font-size:0.8rem;color:var(--gray)">— selezionato in alto a destra</span>'
      );
      $banner.css('border-left', '4px solid var(--success)');
    } else {
      $hidden.val('');
      $info.html(
        '<span style="color:var(--danger);font-weight:600;">⚠ Nessun utente selezionato.</span>' +
        '<br><span style="font-size:0.82rem;color:var(--gray)">Seleziona il tuo utente nel selettore in alto a destra prima di partecipare.</span>'
      );
      $banner.css('border-left', '4px solid var(--danger)');
    }
  }

  // Inizializzazione
  syncPartecipante(
    sessionStorage.getItem('utenteAttivo') || '',
    sessionStorage.getItem('utenteAttivoLabel') || ''
  );

  // Ascolta il cambio utente attivo dall'header
  window.addEventListener('utenteAttivoChanged', function(e) {
    syncPartecipante(e.detail.username, e.detail.label);
  });

  updateProgress();

  // Stile visuale su selezione risposta
  $(document).on('change', 'input[type="checkbox"]', function () {
    const $label = $(this).closest('.risposta-radio');
    if (this.checked) {
      $label.addClass('selected');
    } else {
      $label.removeClass('selected');
    }
    updateProgress();
  });

  // Ripristina stato visuale se già risposto (es. su errore POST)
  $('input[type="checkbox"]:checked').each(function () {
    $(this).closest('.risposta-radio').addClass('selected');
  });

  $('#quiz-play-form').on('submit', function (e) {
    var utente = $('#nomeUtente-hidden').val();
    if (!utente) {
      e.preventDefault();
      alert('Seleziona prima il tuo utente tramite il selettore in alto a destra nella pagina.');
      return;
    }
    const rispAns = new Set($('input[type="checkbox"]:checked').map(function() { return this.name; }).get()).size;
    if (rispAns < totalDomande) {
      e.preventDefault();
      alert('Devi selezionare almeno un\'opzione per ciascuna delle ' + totalDomande + ' domande.');
    }
  });
});
</script>
