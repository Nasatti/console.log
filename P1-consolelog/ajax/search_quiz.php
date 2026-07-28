<?php
// ============================================================
// ajax/search_quiz.php - Ricerca Quiz (Table Layout)
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
           u.nome, u.cognome, u.eMail,
           COUNT(DISTINCT d.numero) AS num_domande,
           COUNT(DISTINCT p.codice) AS num_partecipazioni,
           ROUND(AVG(COALESCE(punteggi.tot, 0)), 1) AS punteggio_medio
    FROM Quiz q
    JOIN Utente u ON q.creatore = u.nomeUtente
    LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
    LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
    LEFT JOIN (
        SELECT calc_domande.codicePartecipazione, SUM(calc_domande.punti_domanda) AS tot
        FROM (
            SELECT ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda,
                   IF(SUM(CASE WHEN r_sub.punteggio IS NULL THEN 1 ELSE 0 END) > 0, 0, SUM(r_sub.punteggio)) AS punti_domanda
            FROM RispostaUtenteQuiz ruq_sub
            JOIN Risposta r_sub ON ruq_sub.numeroRisposta = r_sub.numero 
                                AND ruq_sub.numeroDomanda = r_sub.numeroDomanda 
                                AND ruq_sub.codiceQuiz = r_sub.codiceQuiz
            GROUP BY ruq_sub.codicePartecipazione, ruq_sub.numeroDomanda
        ) AS calc_domande
        GROUP BY calc_domande.codicePartecipazione
    ) punteggi ON punteggi.codicePartecipazione = p.codice
    WHERE 1=1
";
$params = [];

if ($titolo) {
    $sql .= " AND q.titolo LIKE ?";
    $params[] = '%' . $titolo . '%';
}
if ($creatore) {
    if (preg_match('/\((.*?)\)$/', $creatore, $matches)) {
        $sql .= " AND q.creatore = ?";
        $params[] = $matches[1];
    } else {
        $sql .= " AND (u.nome LIKE ? OR u.cognome LIKE ? OR q.creatore LIKE ?)";
        $like = '%' . $creatore . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
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
    echo '<div class="empty-state" style="padding:4rem"><div class="empty-icon">🔍</div><p>Nessun quiz trovato con i filtri selezionati.</p></div>';
    exit;
}
?>
<table>
  <thead>
    <tr>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Codice</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Titolo</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Creatore</th>
      <th class="sortable th-date" style="cursor:pointer;" title="Ordina">Periodo</th>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Domande</th>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Partecipazioni</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Stato</th>
      <th style="min-width: 130px">Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($quiz as $q):
        $codice = (int)$q['codice'];
        $aperto = ($today >= $q['dataInizio'] && $today <= $q['dataFine']);
        $futuro = ($today < $q['dataInizio']);

        if ($aperto)       { $badgeClass = 'badge-open';    $badgeLabel = '✅'; $badgeTitle = 'Aperto'; }
        elseif ($futuro)   { $badgeClass = 'badge-upcoming'; $badgeLabel = '🕐'; $badgeTitle = 'Non ancora iniziato'; }
        else               { $badgeClass = 'badge-closed';  $badgeLabel = '🔒'; $badgeTitle = 'Chiuso'; }

        $linkDomande = "<a href=\"quiz_detail.php?id=$codice#domande\" style=\"font-weight:700\">{$q['num_domande']} domande</a>";
        $linkPart = "<a href=\"partecipazioni.php?quiz_codice=" . $codice . "\" style=\"font-weight:700\">{$q['num_partecipazioni']} part.</a>";
        $creatore = htmlspecialchars($q['creatore']);
    ?>
    <tr data-creatore="<?= $creatore ?>">
      <td class="td-num"><strong>#<?= $codice ?></strong></td>
      <td><strong><a href="quiz_detail.php?id=<?= $codice ?>"><?= htmlspecialchars($q['titolo']) ?></a></strong></td>
      <td>
        <a href="utenti.php?username=<?= urlencode($q['creatore']) ?>" style="text-decoration:none; color:inherit;">
          <div style="font-weight:600; text-decoration:underline;"><?= htmlspecialchars($q['nome'] . ' ' . $q['cognome']) ?></div>
          <div style="font-size:0.75rem;color:var(--gray)"><?= htmlspecialchars($q['eMail']) ?></div>
        </a>
      </td>
      <td class="td-date">
        <?= date('d/m/Y', strtotime($q['dataInizio'])) ?><br>
        <?= date('d/m/Y', strtotime($q['dataFine'])) ?>
      </td>
      <td class="td-num"><?= $linkDomande ?></td>
      <td class="td-num">
        <?= $linkPart ?>
        <div style="font-size:0.75rem;color:var(--gray)"><?= $q['punteggio_medio'] !== null ? $q['punteggio_medio'].' pt media' : '-' ?></div>
      </td>
      <td style="text-align:center">
        <span class="quiz-status-badge <?= $badgeClass ?>" style="position:static; font-size:1.1rem;" title="<?= $badgeTitle ?>"><?= $badgeLabel ?></span>
      </td>
      <td style="vertical-align:middle;">
        <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
          <a href="quiz_detail.php?id=<?= $codice ?>" class="btn-icon-only btn-secondary" title="Dettagli">🔍</a>
          <?php if ($aperto): ?>
            <a href="partecipa.php?id=<?= $codice ?>" class="btn-icon-only btn-success" title="Partecipa">▶</a>
          <?php endif; ?>
          <a href="quiz_form.php?id=<?= $codice ?>" class="btn-icon-only btn-primary owner-btn" title="Modifica" style="display:none">✏️</a>
          <a href="quiz_delete.php?id=<?= $codice ?>" class="btn-icon-only btn-danger owner-btn" title="Elimina" style="display:none" onclick="return confirm('Eliminare il quiz &quot;<?= addslashes(htmlspecialchars($q[\'titolo\'])) ?>&quot;?\nQuesta operazione non può essere annullata.')">🗑️</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<script>
(function(){
  var utente = sessionStorage.getItem('utenteAttivo') || '';
  document.querySelectorAll('tr[data-creatore]').forEach(function(row){
    if (utente && row.getAttribute('data-creatore') === utente) {
      row.querySelectorAll('.owner-btn').forEach(function(btn){ btn.style.display = 'inline-flex'; });
    }
  });
})();
</script>
