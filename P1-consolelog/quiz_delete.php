<?php
// ============================================================
// quiz_delete.php - Eliminazione Quiz (DELETE)
// ============================================================
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: quiz.php'); exit; }

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT q.*, u.nome, u.cognome,
           COUNT(DISTINCT d.numero) AS num_domande,
           COUNT(DISTINCT p.codice) AS num_partecipazioni
    FROM Quiz q
    JOIN Utente u ON q.creatore = u.nomeUtente
    LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
    LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
    WHERE q.codice = ?
    GROUP BY q.codice
");
$stmt->execute([$id]);
$quiz = $stmt->fetch();

if (!$quiz) { header('Location: quiz.php'); exit; }

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma'])) {
    try {
        // Le foreign key con CASCADE eliminano domande, risposte, partecipazioni e opzioni scelta
        $del = $pdo->prepare("DELETE FROM Quiz WHERE codice = ?");
        $del->execute([$id]);
        header('Location: quiz.php?msg=deleted');
        exit;
    } catch (Exception $e) {
        $msg = 'Errore durante l\'eliminazione: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$pageTitle = 'Elimina Quiz';
$activeNav = 'quiz';
$cssPath = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>⚠️ Attenzione</h2>
    <p style="font-size:0.82rem;color:#555;line-height:1.6">
      L'eliminazione del quiz rimuoverà in modo permanente:<br><br>
      ✕ &nbsp;Tutte le <strong>domande</strong><br>
      ✕ &nbsp;Tutte le <strong>risposte</strong><br>
      ✕ &nbsp;Tutte le <strong>partecipazioni</strong><br><br>
      Questa operazione <strong>non può essere annullata</strong>.
    </p>
    <hr style="border-color:var(--yellow-main);margin:1rem 0">
    <a href="quiz_detail.php?id=<?= $id ?>" class="btn btn-outline" style="width:100%;justify-content:center">
      ← Torna al quiz
    </a>
  </aside>

  <main class="main-content" style="display:flex;align-items:flex-start;justify-content:center;padding-top:3rem">
    <div style="width:100%;max-width:560px">
      <div class="breadcrumb">
        <a href="quiz.php">Quiz</a>
        <span>›</span>
        <a href="quiz_detail.php?id=<?= $id ?>"><?= htmlspecialchars($quiz['titolo']) ?></a>
        <span>›</span>
        Elimina
      </div>

      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="delete-confirm-box">
        <div style="font-size:3rem;margin-bottom:0.5rem">🗑️</div>
        <h3>Elimina Quiz</h3>
        <p>
          Stai per eliminare il quiz:<br>
          <strong style="font-size:1rem;color:var(--dark)">"<?= htmlspecialchars($quiz['titolo']) ?>"</strong>
        </p>

        <div style="background:#fdf3f2;border:1px solid #f5a899;border-radius:8px;padding:1rem;margin:1rem 0;text-align:left;font-size:0.85rem">
          <div>📋 Codice: <strong>#<?= $id ?></strong></div>
          <div>👤 Creatore: <strong><?= htmlspecialchars($quiz['nome'] . ' ' . $quiz['cognome']) ?></strong></div>
          <div>❓ Domande: <strong><?= $quiz['num_domande'] ?></strong></div>
          <div>👥 Partecipazioni: <strong><?= $quiz['num_partecipazioni'] ?></strong></div>
        </div>

        <form method="POST">
          <div style="display:flex;gap:12px;justify-content:center">
            <a href="quiz_detail.php?id=<?= $id ?>" class="btn btn-outline">Annulla</a>
            <button type="submit" name="conferma" value="1" class="btn btn-danger">
              🗑 Elimina Definitivamente
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include 'footer.php'; ?>
