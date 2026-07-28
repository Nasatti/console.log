<?php
// ============================================================
// index.php - Homepage riassuntiva di console.log
// Mostra statistiche live dal DB e presentazione del progetto
// ============================================================
require_once 'db.php';

$pageTitle = 'Home';
$activeNav = 'home';
?>
<?php include 'header.php'; ?>

<?php
// Statistiche generali
// Caricamento dei dati dopo header.php così il CSS è già impostato
$pdo = getDB();

$nQuiz           = (int) $pdo->query("SELECT COUNT(*) FROM Quiz")->fetchColumn();
$nUtenti         = (int) $pdo->query("SELECT COUNT(*) FROM Utente")->fetchColumn();
$nPartecipazioni = (int) $pdo->query("SELECT COUNT(*) FROM Partecipazione")->fetchColumn();
$nDomande        = (int) $pdo->query("SELECT COUNT(*) FROM Domanda")->fetchColumn();

// Ultimi 5 quiz
$ultimi = $pdo->query(
  "SELECT q.codice, q.titolo, q.dataInizio, q.dataFine, q.creatore,
          u.nome, u.cognome,
          (SELECT COUNT(*) FROM Domanda d WHERE d.codiceQuiz = q.codice) AS nDomande,
          (SELECT COUNT(*) FROM Partecipazione p2 WHERE p2.codiceQuiz = q.codice) AS nPartecipanti,
          CASE
            WHEN CURDATE() < q.dataInizio THEN 'futuro'
            WHEN CURDATE() > q.dataFine   THEN 'chiuso'
            ELSE 'aperto'
          END AS stato
   FROM Quiz q
   JOIN Utente u ON u.nomeUtente = q.creatore
   ORDER BY q.codice DESC
   LIMIT 5"
)->fetchAll();

// Quiz più partecipato
$top = $pdo->query(
  "SELECT q.codice, q.titolo, COUNT(p.codice) AS tot
   FROM Quiz q
   LEFT JOIN Partecipazione p ON p.codiceQuiz = q.codice
   GROUP BY q.codice
   ORDER BY tot DESC
   LIMIT 1"
)->fetch();

// Creatore con più quiz
$topCreatore = $pdo->query(
  "SELECT u.nome, u.cognome, u.nomeUtente, COUNT(q.codice) AS tot
   FROM Utente u
   LEFT JOIN Quiz q ON q.creatore = u.nomeUtente
   GROUP BY u.nomeUtente
   ORDER BY tot DESC
   LIMIT 1"
)->fetch();
?>

<!-- Homepage principale -->
<div class="home-wrapper">

  <!-- Sezione Hero -->
  <section class="home-hero">
    <div class="hero-content">
      <div class="hero-badge">🎓 Progetto Programmazione Web &mdash; A.A. 2025-2026</div>
      <h2 class="hero-title">
        Benvenuto su <span class="hero-brand">console<span class="hero-dot">.log</span></span>
      </h2>
      <p class="hero-desc">
        La piattaforma per la gestione e la partecipazione a <strong>quiz online</strong>.<br>
        Crea quiz, invita utenti, monitora i risultati e analizza le performance &mdash;
        tutto in un'unica interfaccia.
      </p>
      <div class="hero-actions">
        <a href="quiz.php" class="btn btn-primary btn-lg">📋 Esplora i Quiz</a>
        <a href="quiz_form.php" class="btn btn-secondary btn-lg">➕ Crea un Quiz</a>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-logo-big">Q</div>
    </div>
  </section>

  <!-- Statistiche live -->
  <section class="home-stats-section">
    <div class="section-label">📊 Statistiche in tempo reale</div>
    <div class="home-stats-grid">

      <div class="home-stat-card" style="--accent:#F5C518;">
        <div class="stat-icon">📋</div>
        <div class="stat-big" data-target="<?= $nQuiz ?>" data-suffix="">0</div>
        <div class="stat-name">Quiz Totali</div>
        <div class="stat-sub">creati sulla piattaforma</div>
      </div>

      <div class="home-stat-card" style="--accent:#27AE60;">
        <div class="stat-icon">👥</div>
        <div class="stat-big" data-target="<?= $nUtenti ?>" data-suffix="">0</div>
        <div class="stat-name">Utenti Registrati</div>
        <div class="stat-sub">pronti a sfidare</div>
      </div>

      <div class="home-stat-card" style="--accent:#2980B9;">
        <div class="stat-icon">📊</div>
        <div class="stat-big" data-target="<?= $nPartecipazioni ?>" data-suffix="">0</div>
        <div class="stat-name">Partecipazioni</div>
        <div class="stat-sub">quiz completati</div>
      </div>

      <div class="home-stat-card" style="--accent:#8E44AD;">
        <div class="stat-icon">❓</div>
        <div class="stat-big" data-target="<?= $nDomande ?>" data-suffix="">0</div>
        <div class="stat-name">Domande</div>
        <div class="stat-sub">nel database</div>
      </div>

    </div>
  </section>

  <!-- Sezione Highlights -->
  <section class="home-highlights">
    <?php if ($top && $top['tot'] > 0): ?>
    <div class="highlight-card">
      <div class="highlight-icon">🏆</div>
      <div class="highlight-body">
        <div class="highlight-label">Quiz più popolare</div>
        <div class="highlight-value">
          <a href="quiz_detail.php?id=<?= (int)$top['codice'] ?>" style="color:inherit;font-weight:700;">
            <?= htmlspecialchars($top['titolo']) ?>
          </a>
        </div>
        <div class="highlight-sub"><?= (int)$top['tot'] ?> partecipazioni</div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($topCreatore && $topCreatore['tot'] > 0): ?>
    <div class="highlight-card">
      <div class="highlight-icon">✍️</div>
      <div class="highlight-body">
        <div class="highlight-label">Creatore più prolifico</div>
        <div class="highlight-value">
          <a href="utenti.php?username=<?= urlencode($topCreatore['nomeUtente']) ?>" style="color:inherit;font-weight:700;">
            <?= htmlspecialchars($topCreatore['nome'] . ' ' . $topCreatore['cognome']) ?>
          </a>
        </div>
        <div class="highlight-sub">
          <?= (int)$topCreatore['tot'] ?> quiz creati &middot;
          <a href="quiz.php?creatore=<?= urlencode($topCreatore['nome'].' '.$topCreatore['cognome'].' ('.$topCreatore['nomeUtente'].')') ?>">
            vedi tutti &rarr;
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="highlight-card">
      <div class="highlight-icon">📅</div>
      <div class="highlight-body">
        <div class="highlight-label">Dati aggiornati al</div>
        <div class="highlight-value"><?= date('d/m/Y') ?></div>
        <div class="highlight-sub">Università degli Studi di Bergamo</div>
      </div>
    </div>
  </section>

  <!-- Collegamenti rapidi alle sezioni -->
  <section class="home-features-section">
    <div class="section-label">🗺️ Esplora il sito</div>
    <div class="home-features-grid">

      <div class="feature-card">
        <div class="feature-icon-wrap" style="background:#FFF8DC;">📋</div>
        <h3>Quiz</h3>
        <p>
          Sfoglia tutti i quiz disponibili, filtrali per titolo, creatore, stato e date.
          Crea nuovi quiz con domande e risposte multiple, modificali o eliminali.
        </p>
        <div class="feature-stats">
          <span class="f-stat"><strong><?= $nQuiz ?></strong> quiz</span>
          <span class="f-stat"><strong><?= $nDomande ?></strong> domande</span>
        </div>
        <a href="quiz.php" class="btn btn-primary btn-sm" style="margin-top:auto;">Vai ai Quiz &rarr;</a>
      </div>

      <div class="feature-card">
        <div class="feature-icon-wrap" style="background:#eafaf1;">👥</div>
        <h3>Utenti</h3>
        <p>
          Consulta gli utenti registrati sulla piattaforma, cerca per nome, cognome,
          username o email. Accedi al profilo di ogni utente e vedi le sue attività.
        </p>
        <div class="feature-stats">
          <span class="f-stat"><strong><?= $nUtenti ?></strong> utenti</span>
        </div>
        <a href="utenti.php" class="btn btn-success btn-sm" style="margin-top:auto;">Vai agli Utenti &rarr;</a>
      </div>

      <div class="feature-card">
        <div class="feature-icon-wrap" style="background:#eaf4fb;">📊</div>
        <h3>Partecipazioni</h3>
        <p>
          Analizza tutte le partecipazioni ai quiz. Filtra per quiz, utente o punteggio.
          Monitora le performance e i progressi degli utenti nel tempo.
        </p>
        <div class="feature-stats">
          <span class="f-stat"><strong><?= $nPartecipazioni ?></strong> partecipazioni</span>
        </div>
        <a href="partecipazioni.php" class="btn btn-info btn-sm" style="margin-top:auto;">Vai alle Partecipazioni &rarr;</a>
      </div>

    </div>
  </section>

  <!-- Ultimi quiz pubblicati -->
  <?php if (!empty($ultimi)): ?>
  <section class="home-recent-section">
    <div class="section-label">🕐 Ultimi quiz inseriti</div>
    <div class="home-recent-table-wrap">
      <table class="home-recent-table">
        <thead>
          <tr>
            <th class="th-num">Codice</th>
            <th>Titolo</th>
            <th>Creatore</th>
            <th class="th-date">Periodo</th>
            <th class="th-num">Domande</th>
            <th class="th-num">Partecipazioni</th>
            <th style="text-align:center">Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $today = date('Y-m-d');
          foreach ($ultimi as $q):
            $codice = (int)$q['codice'];
            $aperto = ($today >= $q['dataInizio'] && $today <= $q['dataFine']);
            $futuro = ($today < $q['dataInizio']);
            if ($aperto)     { $badgeClass = 'badge-open';    $badgeLabel = '✅'; $badgeTitle = 'Aperto'; }
            elseif ($futuro) { $badgeClass = 'badge-upcoming'; $badgeLabel = '🕐'; $badgeTitle = 'Non ancora iniziato'; }
            else             { $badgeClass = 'badge-closed';  $badgeLabel = '🔒'; $badgeTitle = 'Chiuso'; }
          ?>
          <tr>
            <td class="td-num"><strong>#<?= $codice ?></strong></td>
            <td><strong><a href="quiz_detail.php?id=<?= $codice ?>"><?= htmlspecialchars($q['titolo']) ?></a></strong></td>
            <td>
              <a href="utenti.php?username=<?= urlencode($q['creatore']) ?>" style="text-decoration:none;color:inherit;">
                <div style="font-weight:600;text-decoration:underline;"><?= htmlspecialchars($q['nome'] . ' ' . $q['cognome']) ?></div>
                <div style="font-size:0.75rem;color:var(--gray)"><?= htmlspecialchars($q['creatore']) ?></div>
              </a>
            </td>
            <td class="td-date">
              <?= date('d/m/Y', strtotime($q['dataInizio'])) ?><br>
              <?= date('d/m/Y', strtotime($q['dataFine'])) ?>
            </td>
            <td class="td-num">
              <a href="quiz_detail.php?id=<?= $codice ?>#domande" style="font-weight:700"><?= (int)$q['nDomande'] ?> domande</a>
            </td>
            <td class="td-num">
              <a href="partecipazioni.php?quiz_codice=<?= $codice ?>" style="font-weight:700"><?= (int)$q['nPartecipanti'] ?> part.</a>
            </td>
            <td style="text-align:center">
              <span class="quiz-status-badge <?= $badgeClass ?>" style="position:static;display:inline-block;font-size:1.1rem;" title="<?= $badgeTitle ?>"><?= $badgeLabel ?></span>
            </td>
            <td style="vertical-align:middle;">
              <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                <a href="quiz_detail.php?id=<?= $codice ?>" class="btn-icon-only btn-secondary" title="Dettagli">🔍</a>
                <?php if ($aperto): ?>
                  <a href="partecipa.php?id=<?= $codice ?>" class="btn-icon-only btn-success" title="Partecipa">&#9654;</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="text-align:right; margin-top:0.8rem;">
      <a href="quiz.php" class="btn btn-outline btn-sm">Vedi tutti i quiz &rarr;</a>
    </div>
  </section>
  <?php endif; ?>

</div><!-- /.home-wrapper -->

<?php include 'footer.php'; ?>

<!-- Animazione contatori -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var counters = document.querySelectorAll('.stat-big[data-target]');

  function animateCounter(el) {
    var target   = parseInt(el.getAttribute('data-target'), 10);
    var suffix   = el.getAttribute('data-suffix') || '';
    var duration = 1200;
    var step     = Math.max(1, Math.ceil(target / (duration / 16)));
    var current  = 0;

    function tick() {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString('it-IT') + suffix;
      if (current < target) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  counters.forEach(function(c) { observer.observe(c); });
});
</script>
