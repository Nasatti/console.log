<?php
// ============================================================
// ajax/search_partecipazioni.php - Ricerca Partecipazioni (Table Layout)
// ============================================================
require_once '../db.php';

$quiz_codice = trim($_GET['quiz_codice'] ?? '');
$quiz_titolo = trim($_GET['quiz_titolo'] ?? '');
$utente    = trim($_GET['utente'] ?? '');
$punti_min = trim($_GET['punti_min'] ?? '');
$punti_max = trim($_GET['punti_max'] ?? '');

$sql = "
    SELECT p.codice, p.data, p.nomeUtente, p.codiceQuiz,
           q.titolo AS quiz_titolo,
           u.nome AS utente_nome, u.cognome AS utente_cognome,
           punteggi_calcolati.tot AS punteggio_ottenuto,
           max_q.totale_possibile
    FROM Partecipazione p
    JOIN Quiz q ON p.codiceQuiz = q.codice
    JOIN Utente u ON p.nomeUtente = u.nomeUtente
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
    ) punteggi_calcolati ON punteggi_calcolati.codicePartecipazione = p.codice
    LEFT JOIN (
        SELECT codiceQuiz, SUM(punteggio) as totale_possibile
        FROM Risposta
        WHERE punteggio IS NOT NULL
        GROUP BY codiceQuiz
    ) max_q ON max_q.codiceQuiz = p.codiceQuiz
    WHERE 1=1
";
$params = [];

if ($quiz_codice !== '') {
    $sql .= " AND p.codiceQuiz = ?";
    $params[] = (int)$quiz_codice;
}
if ($quiz_titolo !== '') {
    $sql .= " AND q.titolo LIKE ?";
    $params[] = '%' . $quiz_titolo . '%';
}
if ($utente) {
    // Controllo regex per capire se ha selezionato dalla datalist con username fra parentesi
    if (preg_match('/\((.*?)\)$/', $utente, $matches)) {
        $sql .= " AND p.nomeUtente = ?";
        $params[] = $matches[1];
    } else {
        $sql .= " AND (u.nome LIKE ? OR u.cognome LIKE ? OR p.nomeUtente LIKE ?)";
        $like = '%' . $utente . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
}

$sql .= " GROUP BY p.codice ";

if ($punti_min !== '' || $punti_max !== '') {
    $havingClauses = [];
    if ($punti_min !== '') {
        $havingClauses[] = "(punteggi_calcolati.tot / max_q.totale_possibile * 100) >= " . floatval($punti_min);
    }
    if ($punti_max !== '') {
        $havingClauses[] = "(punteggi_calcolati.tot / max_q.totale_possibile * 100) <= " . floatval($punti_max);
    }
    $sql .= " HAVING " . implode(" AND ", $havingClauses);
}

$sql .= " ORDER BY p.data DESC, p.codice DESC";

$pdo  = getDB();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partecipazioni = $stmt->fetchAll();

if (empty($partecipazioni)) {
    echo '<div class="empty-state" style="padding:4rem"><div class="empty-icon">📊</div><p>Nessuna partecipazione trovata.</p></div>';
    exit;
}

$count = count($partecipazioni);
echo "<p style='font-size:0.82rem;color:var(--gray);margin:1rem;font-weight:600'>Risultati: $count partecipazione/i</p>";

?>
<table>
  <thead>
    <tr>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Codice</th>
      <th class="sortable th-date" style="cursor:pointer;" title="Ordina">Data</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Utente</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Quiz</th>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Punteggio %</th>
      <th>Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($partecipazioni as $p):
        $codPart = (int)$p['codice'];
        $codQuiz = (int)$p['codiceQuiz'];
        $username= htmlspecialchars($p['nomeUtente']);
        $punteggio = (float)$p['punteggio_ottenuto'];
        $totale    = (float)$p['totale_possibile'];
        $percent   = $totale > 0 ? round(($punteggio / $totale) * 100) : 0;
    ?>
    <tr>
      <td class="td-num"><strong>#<?= $codPart ?></strong></td>
      <td class="td-date"><?= date('d/m/Y', strtotime($p['data'])) ?></td>
      <td>
        <a href="utenti.php?username=<?= urlencode($username) ?>" style="text-decoration:none; color:inherit;">
          <div style="font-weight:600; text-decoration:underline;">
            <?= htmlspecialchars($p['utente_nome'] . ' ' . $p['utente_cognome']) ?>
          </div>
          <div style="font-size:0.75rem;color:var(--gray)">@<?= $username ?></div>
        </a>
      </td>
      <td>
        <a href="quiz_detail.php?id=<?= $codQuiz ?>" style="text-decoration:none; color:inherit;">
          <div style="font-weight:600; text-decoration:underline;"><?= htmlspecialchars($p['quiz_titolo']) ?></div>
          <div style="font-size:0.75rem;color:var(--gray)">ID: #<?= $codQuiz ?></div>
        </a>
      </td>
      <td class="td-num"><strong><?= $percent ?>%</strong></td>
      <td>
        <a href="risultato.php?p=<?= $codPart ?>" class="btn-icon-only btn-secondary" title="Risultato">🔍</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
