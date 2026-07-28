<?php
// ============================================================
// ajax/search_quiz.php - Ricerca Quiz (ritorna HTML)
// ============================================================
require_once '../db.php';

$titolo   = trim($_GET['titolo']   ?? '');
$creatore = trim($_GET['creatore'] ?? '');
$stato    = trim($_GET['stato']    ?? '');
$dal      = trim($_GET['dal']      ?? '');
$al       = trim($_GET['al']       ?? '');
$today    = date('Y-m-d');

$sql = "
    SELECT q.codice, q.titolo, q.dataInizio, q.dataFine, q.creatore,
           u.nome, u.cognome,
           COUNT(DISTINCT d.numero) AS num_domande,
           COUNT(DISTINCT p.codice) AS num_partecipazioni,
           ROUND(AVG(punteggi.tot), 1) AS punteggio_medio
    FROM Quiz q
    JOIN Utente u ON q.creatore = u.nomeUtente
    LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
    LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
    LEFT JOIN (
        SELECT oc.codicePartecipazione, SUM(r.punteggio) AS tot
        FROM RispostaUtenteQuiz oc
        JOIN Risposta r ON r.numero = oc.numeroRisposta
                       AND r.numeroDomanda = oc.numeroDomanda
                       AND r.codiceQuiz = oc.codiceQuiz
        WHERE r.punteggio IS NOT NULL
        GROUP BY oc.codicePartecipazione
    ) punteggi ON punteggi.codicePartecipazione = p.codice
    WHERE 1=1
";
$params = [];

if ($titolo) {
    $sql .= " AND q.titolo LIKE ?";
    $params[] = '%' . $titolo . '%';
}
if ($creatore) {
    $sql .= " AND q.creatore = ?";
    $params[] = $creatore;
}
if ($dal) {
    $sql .= " AND q.dataInizio >= ?";
    $params[] = $dal;
}
if ($al) {
    $sql .= " AND q.dataFine <= ?";
    $params[] = $al;
}
if ($stato === 'aperto') {
    $sql .= " AND q.dataInizio <= ? AND q.dataFine >= ?";
    $params[] = $today; $params[] = $today;
} elseif ($stato === 'chiuso') {
    $sql .= " AND q.dataFine < ?";
    $params[] = $today;
} elseif ($stato === 'futuro') {
    $sql .= " AND q.dataInizio > ?";
    $params[] = $today;
}

$sql .= " GROUP BY q.codice ORDER BY q.dataFine DESC, q.titolo";

$pdo  = getDB();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quiz = $stmt->fetchAll();

if (empty($quiz)) {
    echo '<div class="empty-state"><div class="empty-icon">🔍</div><p>Nessun quiz trovato con i filtri selezionati.</p></div>';
    exit;
}

echo '<div class="quiz-grid">';
foreach ($quiz as $q) {
    $aperto  = ($today >= $q['dataInizio'] && $today <= $q['dataFine']);
    $futuro  = ($today < $q['dataInizio']);
    if ($aperto)       { $badgeClass = 'badge-open';    $badgeLabel = '✅ Aperto'; }
    elseif ($futuro)   { $badgeClass = 'badge-upcoming'; $badgeLabel = '🕐 Futuro'; }
    else               { $badgeClass = 'badge-closed';  $badgeLabel = '🔒 Chiuso'; }

    $titolo   = htmlspecialchars($q['titolo']);
    $creatore = htmlspecialchars($q['nome'] . ' ' . $q['cognome']);
    $dal      = date('d/m/Y', strtotime($q['dataInizio']));
    $al       = date('d/m/Y', strtotime($q['dataFine']));
    $media    = $q['punteggio_medio'] !== null ? number_format($q['punteggio_medio'], 1) . ' pt' : '-';
    $codice   = (int)$q['codice'];

    echo <<<HTML
    <div class="quiz-card">
      <div class="quiz-card-top">
        <div class="quiz-code">Quiz #$codice</div>
        <h3>$titolo</h3>
        <span class="quiz-status-badge $badgeClass">$badgeLabel</span>
      </div>
      <div class="quiz-card-body">
        <div class="quiz-meta">
          <span>👤 $creatore</span>
          <span>📅 $dal → $al</span>
        </div>
        <div class="quiz-stats">
          <div class="stat-item">
            <div class="stat-num">{$q['num_domande']}</div>
            <div class="stat-label">Domande</div>
          </div>
          <div class="stat-item">
            <div class="stat-num">{$q['num_partecipazioni']}</div>
            <div class="stat-label">Partecipazioni</div>
          </div>
          <div class="stat-item">
            <div class="stat-num">$media</div>
            <div class="stat-label">Media</div>
          </div>
        </div>
      </div>
      <div class="quiz-card-footer">
        <a href="quiz_detail.php?id=$codice" class="btn btn-secondary btn-sm">🔍 Dettagli</a>
        <a href="quiz_form.php?id=$codice" class="btn btn-outline btn-sm">✏️ Modifica</a>
        <a href="quiz_delete.php?id=$codice" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare questo quiz?\nQuesta operazione non può essere annullata.')">🗑</a>
HTML;
    if ($aperto) {
        echo "<a href=\"partecipa.php?id=$codice\" class=\"btn btn-success btn-sm\">▶ Partecipa</a>";
    }
    echo '</div></div>';
}
echo '</div>';
