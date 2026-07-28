<?php
// ============================================================
// ajax/search_utenti.php - Ricerca Utenti (Table Layout)
// ============================================================
require_once '../db.php';

$nome     = trim($_GET['nome']     ?? '');
$email    = trim($_GET['email']    ?? '');
$username = trim($_GET['username'] ?? '');

$sql = "
    SELECT u.nomeUtente, u.nome, u.cognome, u.eMail,
           COUNT(DISTINCT q.codice)  AS quiz_creati,
           COUNT(DISTINCT p.codice)  AS partecipazioni
    FROM Utente u
    LEFT JOIN Quiz q ON q.creatore = u.nomeUtente
    LEFT JOIN Partecipazione p ON p.nomeUtente = u.nomeUtente
    WHERE 1=1
";
$params = [];

if ($nome) {
    $sql .= " AND (u.nome LIKE ? OR u.cognome LIKE ?)";
    $like = '%' . $nome . '%';
    $params[] = $like; $params[] = $like;
}
if ($username) {
    $sql .= " AND u.nomeUtente LIKE ?";
    $params[] = '%' . $username . '%';
}
if ($email) {
    $sql .= " AND u.eMail LIKE ?";
    $params[] = '%' . $email . '%';
}

$sql .= " GROUP BY u.nomeUtente ORDER BY u.cognome, u.nome";

$pdo  = getDB();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$utenti = $stmt->fetchAll();

if (empty($utenti)) {
    echo '<div class="empty-state" style="padding:4rem"><div class="empty-icon">👥</div><p>Nessun utente trovato.</p></div>';
    exit;
}

$count = count($utenti);
echo "<p style='font-size:0.82rem;color:var(--gray);margin:1rem;font-weight:600'>Risultati: $count utente/i trovato/i</p>";

?>
<table>
  <thead>
    <tr>
      <th style="width: 50px"></th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Nome Completo</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Username</th>
      <th class="sortable" style="cursor:pointer;" title="Ordina">Email</th>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Quiz Creati</th>
      <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Partecipazioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($utenti as $u):
        $iniziali  = strtoupper(substr($u['nome'], 0, 1) . substr($u['cognome'], 0, 1));
        $username  = htmlspecialchars($u['nomeUtente']);
        $linkQuiz = "<a href=\"quiz.php?creatore=$username\" style=\"font-weight:700\">" . (int)$u['quiz_creati'] . " quiz</a>";
        $linkPart = "<a href=\"partecipazioni.php?utente=$username\" style=\"font-weight:700;\">" . (int)$u['partecipazioni'] . " part.</a>";
    ?>
    <tr>
      <td>
        <div class="avatar" style="width:36px;height:36px;font-size:0.85rem"><?= $iniziali ?></div>
      </td>
      <td><strong><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?></strong></td>
      <td>@<?= $username ?></td>
      <td><?= htmlspecialchars($u['eMail']) ?></td>
      <td class="td-num"><?= $linkQuiz ?></td>
      <td class="td-num"><?= $linkPart ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
