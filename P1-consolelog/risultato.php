<?php
// ============================================================
// risultato.php - Risultato Partecipazione
// ============================================================
require_once 'db.php';

$codPart = isset($_GET['p']) ? (int)$_GET['p'] : 0;
if ($codPart <= 0) { header('Location: quiz.php'); exit; }

$pdo = getDB();

// Dati partecipazione
$stmt = $pdo->prepare("
    SELECT p.*, u.nome, u.cognome, u.eMail, q.titolo, q.codice AS codiceQuiz, q.dataInizio, q.dataFine,
           COUNT(DISTINCT d.numero) AS num_domande
    FROM Partecipazione p
    JOIN Utente u ON p.nomeUtente = u.nomeUtente
    JOIN Quiz q ON p.codiceQuiz = q.codice
    LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
    WHERE p.codice = ?
    GROUP BY p.codice
");
$stmt->execute([$codPart]);
$part = $stmt->fetch();

if (!$part) { header('Location: quiz.php'); exit; }

// Dettaglio risposte date
$risposteStmt = $pdo->prepare("
    SELECT d.numero AS numDom, d.testo AS testoDom,
           r.numero AS numRisp, r.testo AS testoRisp, r.punteggio
    FROM RispostaUtenteQuiz oc
    JOIN Domanda d ON d.numero = oc.numeroDomanda AND d.codiceQuiz = oc.codiceQuiz
    JOIN Risposta r ON r.numero = oc.numeroRisposta AND r.numeroDomanda = oc.numeroDomanda AND r.codiceQuiz = oc.codiceQuiz
    WHERE oc.codicePartecipazione = ?
    ORDER BY d.numero
");
$risposteStmt->execute([$codPart]);
$risposte = $risposteStmt->fetchAll();

// Calcolo punteggio ottenuto raggruppato
$punteggio = 0;
$grouped = [];
foreach ($risposte as $r) {
    $dom = $r['numDom'];
    if (!isset($grouped[$dom])) {
        $grouped[$dom] = [
            'testo' => $r['testoDom'],
            'risposte_date' => [],
            'errore' => false,
            'punti_parziali' => 0
        ];
    }
    $grouped[$dom]['risposte_date'][] = $r;
    
    if ($r['punteggio'] === null) {
        $grouped[$dom]['errore'] = true;
    } else {
        $grouped[$dom]['punti_parziali'] += $r['punteggio'];
    }
}

foreach ($grouped as $g) {
    if (!$g['errore']) {
        $punteggio += $g['punti_parziali'];
    }
}

// Punteggio massimo possibile
$stmtMax = $pdo->prepare("SELECT SUM(punteggio) FROM Risposta WHERE codiceQuiz = ? AND punteggio IS NOT NULL");
$stmtMax->execute([$part['codiceQuiz']]);
$maxPunti = (float)$stmtMax->fetchColumn();

$totale = $part['num_domande'];
$perc = $maxPunti > 0 ? round(($punteggio / $maxPunti) * 100) : 0;
if ($perc > 100) $perc = 100;

$pageTitle = 'Risultato';
$activeNav = 'quiz';
$cssPath = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>📊 Riepilogo</h2>
    <div class="filter-group">
      <label>Quiz</label>
      <div style="font-size:0.85rem;font-weight:600">
        <a href="quiz_detail.php?id=<?= $part['codiceQuiz'] ?>" style="color:inherit;text-decoration:none;">
          <?= htmlspecialchars($part['titolo']) ?>
        </a>
      </div>
    </div>
    <div class="filter-group">
      <label>Partecipante</label>
      <div>
        <a href="utenti.php?username=<?= urlencode($part['nomeUtente']) ?>" style="color:inherit;text-decoration:none;font-weight:600">
          <?= htmlspecialchars($part['nome'] . ' ' . $part['cognome']) ?>
        </a>
        <div style="font-size:0.78rem;color:#888"><?= htmlspecialchars($part['eMail']) ?></div>
      </div>
    </div>
    <div class="filter-group">
      <label>Data</label>
      <div><?= date('d/m/Y', strtotime($part['data'])) ?></div>
    </div>
    <hr style="border-color:var(--yellow-main);margin:1rem 0">
    <a href="quiz_detail.php?id=<?= $part['codiceQuiz'] ?>" class="btn btn-outline" style="width:100%;justify-content:center">
      ← Torna al quiz
    </a>
    <?php 
    $today = date('Y-m-d');
    $quizAperto = ($today >= $part['dataInizio'] && $today <= $part['dataFine']);
    if ($quizAperto): 
    ?>
    <a href="partecipa.php?id=<?= $part['codiceQuiz'] ?>" class="btn btn-primary" style="width:100%;margin-top:8px;justify-content:center">
      🔄 Rifai il quiz
    </a>
    <?php endif; ?>
  </aside>

  <main class="main-content">
    <div class="breadcrumb">
      <a href="quiz.php">Quiz</a>
      <span>›</span>
      <a href="quiz_detail.php?id=<?= $part['codiceQuiz'] ?>"><?= htmlspecialchars($part['titolo']) ?></a>
      <span>›</span>
      Risultato
    </div>

    <!-- Punteggio principale -->
    <div class="risultato-box" style="margin-bottom:2rem">
      <div class="score-circle">
        <span><?= $perc ?>%</span>
        <span style="font-size:0.75rem;font-weight:400"><span style="font-weight:bold"><?= number_format($punteggio,1) ?></span> / <?= number_format($maxPunti,1) ?></span>
      </div>

      <h2 style="font-size:1.3rem;margin-bottom:.5rem">
        <?php if ($perc >= 80): ?> 🏆 Ottimo risultato!
        <?php elseif ($perc >= 60): ?> 👍 Buon risultato!
        <?php elseif ($perc >= 40): ?> 💪 Quasi!
        <?php else: ?> 📚 Riprova!
        <?php endif; ?>
      </h2>

      <p style="color:var(--gray);font-size:0.9rem">
        Hai ottenuto <strong><?= number_format($punteggio, 1) ?></strong> punti su un massimo teorico di <strong><?= number_format($maxPunti, 1) ?></strong>.<br>
        (Basato sulle opzioni che hai selezionato)
      </p>
    </div>

    <!-- Dettaglio risposte -->
    <h3 style="font-size:1.1rem;font-weight:700;border-bottom:2px solid var(--yellow-main);padding-bottom:.5rem;margin-bottom:1rem; flex-shrink: 0;">
      📝 Dettaglio Risposte
    </h3>

    <?php foreach ($grouped as $numDom => $g): ?>
      <div class="domanda-item" style="flex-shrink: 0;">
        <div class="domanda-header" style="cursor:default; <?= $g['errore'] ? 'background:#ffebee;' : '' ?>">
          <span class="domanda-num"><?= $numDom ?></span>
          <span><?= htmlspecialchars($g['testo']) ?></span>
          <span style="margin-left:auto; white-space:nowrap; padding-left:1rem;">
            <?php 
              if ($g['errore']) {
                  echo '❌ <span style="font-size:0.8rem;color:var(--danger)">Annullata (0 pt)</span>';
              } else {
                  echo '✅ <span style="font-size:0.8rem;color:var(--success)">+' . $g['punti_parziali'] . ' pt</span>';
              }
            ?>
          </span>
        </div>
        <div style="padding:10px 16px;font-size:0.87rem; display:flex; flex-direction:column; gap:4px;">
          <?php foreach ($g['risposte_date'] as $rr): ?>
            <div>
              <span style="color:var(--gray)">Scelta: </span>
              <strong><?= htmlspecialchars($rr['testoRisp']) ?></strong>
              <?php if ($rr['punteggio'] !== null): ?>
                <span style="color:var(--success);font-weight:700;margin-left:12px">(+<?= $rr['punteggio'] ?> pt)</span>
              <?php else: ?>
                <span style="color:var(--danger);font-weight:600;margin-left:12px">Sbagliata</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  </main>
</div>

<?php include 'footer.php'; ?>

