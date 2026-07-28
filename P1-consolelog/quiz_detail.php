<?php
// ============================================================
// quiz_detail.php - Dettaglio Quiz
// Mostra info quiz, domande, statistiche JOIN, link partecipa
// ============================================================
require_once 'db.php';

$codice = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($codice <= 0) {
    header('Location: quiz.php');
    exit;
}

$pdo = getDB();

// Dati quiz con info creatore e statistiche (JOIN)
$stmt = $pdo->prepare("
    SELECT q.*, u.nome, u.cognome, u.eMail,
           COUNT(DISTINCT p.codice) AS num_partecipazioni,
           ROUND(AVG(COALESCE(punteggi.tot, 0)), 2) AS punteggio_medio,
           COUNT(DISTINCT d.numero) AS num_domande
    FROM Quiz q
    JOIN Utente u ON q.creatore = u.nomeUtente
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
    LEFT JOIN Domanda d ON d.codiceQuiz = q.codice
    WHERE q.codice = ?
    GROUP BY q.codice
");
$stmt->execute([$codice]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: quiz.php');
    exit;
}

// Domande con risposte
$domande = $pdo->prepare("
    SELECT d.numero, d.testo
    FROM Domanda d
    WHERE d.codiceQuiz = ?
    ORDER BY d.numero
");
$domande->execute([$codice]);
$domande = $domande->fetchAll();

foreach ($domande as &$dom) {
    $rs = $pdo->prepare("
        SELECT r.numero, r.testo, r.punteggio
        FROM Risposta r
        WHERE r.numeroDomanda = ? AND r.codiceQuiz = ?
        ORDER BY r.numero
    ");
    $rs->execute([$dom['numero'], $codice]);
    $dom['risposte'] = $rs->fetchAll();
}
unset($dom);

// Ultime partecipazioni
$stmt = $pdo->prepare("
    SELECT p.codice, p.data, u.nome, u.cognome,
           punteggi.tot AS punteggio_tot
    FROM Partecipazione p
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
    ) punteggi ON punteggi.codicePartecipazione = p.codice
    WHERE p.codiceQuiz = ?
    ORDER BY p.data DESC
    LIMIT 10
");
$stmt->execute([$codice]);
$partecipazioni = $stmt->fetchAll();

$today = date('Y-m-d');
$aperto = ($today >= $quiz['dataInizio'] && $today <= $quiz['dataFine']);
$futuro = ($today < $quiz['dataInizio']);

$pageTitle = htmlspecialchars($quiz['titolo']);
$activeNav = 'quiz';
$cssPath = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>📋 Riepilogo</h2>

    <div class="filter-group">
      <label>Codice</label>
      <div style="font-weight:700;font-size:1rem;padding:4px 0">#<?= $quiz['codice'] ?></div>
    </div>
    <div class="filter-group">
      <label>Stato</label>
      <?php if ($aperto): ?>
        <span class="quiz-status-badge badge-open" style="position:static; display:inline-block;" title="Aperto">✅ Aperto</span>
      <?php elseif ($futuro): ?>
        <span class="quiz-status-badge badge-upcoming" style="position:static; display:inline-block;" title="Non ancora iniziato">🕐 Futuro</span>
      <?php else: ?>
        <span class="quiz-status-badge badge-closed" style="position:static; display:inline-block;" title="Chiuso">🔒 Chiuso</span>
      <?php endif; ?>
    </div>
    <div class="filter-group">
      <label>Creatore</label>
      <div><a href="utenti.php?username=<?= urlencode($quiz['creatore']) ?>"><?= htmlspecialchars($quiz['nome'] . ' ' . $quiz['cognome']) ?></a></div>
      <div style="font-size:0.78rem;color:#888"><?= htmlspecialchars($quiz['eMail']) ?></div>
    </div>
    <div class="filter-group">
      <label>Periodo</label>
      <div style="font-size:0.85rem">
        📅 Dal <?= date('d/m/Y', strtotime($quiz['dataInizio'])) ?><br>
        al <?= date('d/m/Y', strtotime($quiz['dataFine'])) ?>
      </div>
    </div>

    <hr style="border-color:var(--yellow-main);margin:1rem 0">

    <a href="quiz_form.php?id=<?= $codice ?>" class="btn btn-primary owner-action" style="width:100%;margin-bottom:8px;justify-content:center;display:none" data-creatore="<?= htmlspecialchars($quiz['creatore']) ?>">
      ✏️ Modifica
    </a>
    <a href="quiz_delete.php?id=<?= $codice ?>" class="btn btn-danger owner-action" style="width:100%;margin-bottom:8px;justify-content:center;display:none" data-creatore="<?= htmlspecialchars($quiz['creatore']) ?>" onclick="return confirm('Sei sicuro di voler eliminare il quiz \"<?= addslashes(htmlspecialchars($quiz['titolo'])) ?>\"?\nQuesta operazione non può essere annullata.')">
      🗑 Elimina
    </a>
    <?php if ($aperto): ?>
    <a href="partecipa.php?id=<?= $codice ?>" class="btn btn-success" style="width:100%;justify-content:center">
      ▶ Partecipa
    </a>
    <?php endif; ?>
    <a href="quiz.php" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center">
      ← Torna alla lista
    </a>
  </aside>

  <main class="main-content">
    <div class="breadcrumb">
      <a href="quiz.php">Quiz</a>
      <span>›</span>
      <?= htmlspecialchars($quiz['titolo']) ?>
    </div>

    <!-- Intestazione quiz -->
    <div class="detail-header">
      <h2><?= htmlspecialchars($quiz['titolo']) ?></h2>
      <div class="meta-row">
        <span class="meta-pill">👤 <a href="utenti.php?username=<?= urlencode($quiz['creatore']) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($quiz['nome'] . ' ' . $quiz['cognome']) ?></a></span>
        <span class="meta-pill" style="display:inline-flex; align-items:center;">
          📅 <?= date('d/m/Y', strtotime($quiz['dataInizio'])) ?> → <?= date('d/m/Y', strtotime($quiz['dataFine'])) ?>
          <span style="margin-left: 8px;">
            <?php if ($aperto): ?><span class="badge-open" style="padding:2px 8px;border-radius:12px;font-size:1rem;font-weight:bold;" title="Aperto">✅</span><?php elseif ($futuro): ?><span class="badge-upcoming" style="padding:2px 8px;border-radius:12px;font-size:1rem;font-weight:bold;" title="Non ancora iniziato">🕐</span><?php else: ?><span class="badge-closed" style="padding:2px 8px;border-radius:12px;font-size:1rem;font-weight:bold;" title="Chiuso">🔒</span><?php endif; ?>
          </span>
        </span>
      </div>
    </div>

    <!-- Statistiche -->
    <div class="stats-bar">
      <div class="stats-card">
        <div class="stat-num"><?= $quiz['num_domande'] ?></div>
        <div class="stat-label">Domande</div>
      </div>
      <div class="stats-card">
        <div class="stat-num"><a href="partecipazioni.php?quiz_codice=<?= $codice ?>" style="color:inherit;text-decoration:none;"><?= $quiz['num_partecipazioni'] ?></a></div>
        <div class="stat-label">Partecipazioni</div>
      </div>
      <div class="stats-card">
        <div class="stat-num"><?= $quiz['punteggio_medio'] !== null ? number_format($quiz['punteggio_medio'], 1) : '-' ?></div>
        <div class="stat-label">Punteggio medio</div>
      </div>
    </div>

    <!-- Domande e risposte (visibili solo al creatore) -->
    <div class="domande-section" id="domande-section" style="display:none">
      <h3>❓ Domande del Quiz</h3>
      <?php if (empty($domande)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <p>Nessuna domanda presente per questo quiz.</p>
        </div>
      <?php else: ?>
        <?php foreach ($domande as $dom): ?>
          <div class="domanda-item">
            <div class="domanda-header" onclick="toggleRisposte(this)">
              <span class="domanda-num"><?= $dom['numero'] ?></span>
              <span><?= htmlspecialchars($dom['testo']) ?></span>
              <span style="margin-left:auto;color:#888;font-size:0.8rem">▼</span>
            </div>
            <div class="risposte-list">
              <?php foreach ($dom['risposte'] as $r): ?>
                <div class="risposta-item <?= $r['punteggio'] !== null ? 'corretta' : 'sbagliata' ?>">
                  <?php if ($r['punteggio'] !== null): ?>
                    <span>✅</span>
                    <span><?= htmlspecialchars($r['testo']) ?></span>
                    <span style="margin-left:auto;font-weight:700;color:var(--success)">(+<?= $r['punteggio'] ?> pt)</span>
                  <?php else: ?>
                    <span>❌</span>
                    <span><?= htmlspecialchars($r['testo']) ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Partecipazioni recenti -->
    <?php if (!empty($partecipazioni)): ?>
    <div style="margin-top:2rem">
      <h3 style="font-size:1.1rem;font-weight:700;border-bottom:2px solid var(--yellow-main);padding-bottom:.5rem;margin-bottom:1rem">
        🏆 Ultime Partecipazioni
      </h3>
      <div class="table-wrap">
        <table class="basic-table">
          <thead>
            <tr>
              <th class="sortable th-num" style="cursor:pointer;" title="Ordina">#</th>
              <th class="sortable" style="cursor:pointer;" title="Ordina">Utente</th>
              <th class="sortable th-date" style="cursor:pointer;" title="Ordina">Data</th>
              <th class="sortable th-num" style="cursor:pointer;" title="Ordina">Punteggio</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($partecipazioni as $p): ?>
            <tr>
              <td class="td-num"><strong><a href="risultato.php?p=<?= $p['codice'] ?>" style="color:inherit;text-decoration:none;">#<?= $p['codice'] ?></a></strong></td>
              <td><?= htmlspecialchars($p['nome'] . ' ' . $p['cognome']) ?></td>
              <td class="td-date"><?= date('d/m/Y', strtotime($p['data'])) ?></td>
              <td class="td-num"><strong><?= number_format($p['punteggio_tot'], 1) ?></strong> pt</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<?php include 'footer.php'; ?>

<script>
function toggleRisposte(header) {
  const list = header.nextElementSibling;
  const arrow = header.querySelector('span:last-child');
  if (list.style.display === 'block') {
    list.style.display = 'none';
    arrow.textContent = '▼';
  } else {
    list.style.display = 'block';
    arrow.textContent = '▲';
  }
}

$(document).on('click', 'th.sortable', function() {
  var table = $(this).closest('table');
  var index = $(this).index();
  var asc = $(this).data('asc') || false;
  $(this).data('asc', !asc);
  var rows = table.find('tbody tr').toArray().sort(function(a, b) {
    var valA = $(a).children('td').eq(index).text().trim();
    var valB = $(b).children('td').eq(index).text().trim();
    function parseItDate(str) {
      var m = str.match(/(\d{2})\/(\d{2})\/(\d{4})/);
      if (m) return new Date(m[3], m[2]-1, m[1]).getTime();
      return null;
    }
    var dateA = parseItDate(valA);
    var dateB = parseItDate(valB);
    if (dateA !== null && dateB !== null) {
      return asc ? dateA - dateB : dateB - dateA;
    }
    var numA = parseFloat(valA.replace(/[^0-9.-]+/g,""));
    var numB = parseFloat(valB.replace(/[^0-9.-]+/g,""));
    if (!isNaN(numA) && !isNaN(numB) && $.isNumeric(numA) && $.isNumeric(numB)) {
      return asc ? numA - numB : numB - numA;
    }
    return asc ? valA.localeCompare(valB) : valB.localeCompare(valA);
  });
  for (var i = 0; i < rows.length; i++) { table.find('tbody').append(rows[i]); }
});

// Mostra bottoni Modifica/Elimina e sezione domande solo se l'utente attivo è il creatore
function syncDetailOwnerActions(utente) {
  var creatore = '<?= htmlspecialchars($quiz["creatore"]) ?>';
  var isOwner = (utente && utente === creatore);
  document.querySelectorAll('.owner-action').forEach(function(el){
    el.style.display = isOwner ? 'flex' : 'none';
  });
  var domSection = document.getElementById('domande-section');
  if (domSection) domSection.style.display = isOwner ? 'block' : 'none';
}

// Inizializzazione
syncDetailOwnerActions(sessionStorage.getItem('utenteAttivo') || '');

// Ascolta l'evento del cambio utente attivo dall'header
window.addEventListener('utenteAttivoChanged', function(e) {
  syncDetailOwnerActions(e.detail.username);
});
</script>
