<?php
// ============================================================
// partecipazioni.php - Ricerca e Lista Partecipazioni
// ============================================================
require_once 'db.php';

$pageTitle = 'Partecipazioni';
$activeNav = 'partecipazioni';
$cssPath   = '';

$pdo = getDB();
$utenti = $pdo->query("SELECT nomeUtente, nome, cognome FROM Utente ORDER BY cognome, nome")->fetchAll();
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>🔍 Filtra Partecipazioni</h2>
    <div class="filter-group">
      <label>Quiz</label>
      <div style="display: flex; gap: 8px;">
        <input type="number" id="f-quiz-codice" placeholder="ID" style="width: 35%" autocomplete="off">
        <input type="text" id="f-quiz-titolo" placeholder="Titolo..." style="width: 65%" autocomplete="off">
      </div>
    </div>
    <div class="filter-group">
      <label for="f-utente">Utente</label>
      <input type="text" id="f-utente" list="dl-utente" placeholder="— Tutti —" autocomplete="off">
      <datalist id="dl-utente">
        <?php foreach ($utenti as $u): ?>
          <option value="<?= htmlspecialchars($u['nome'] . ' ' . $u['cognome'] . ' (' . $u['nomeUtente'] . ')') ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="filter-group">
      <label>Punti (Min - Max)</label>
      <div style="display: flex; gap: 8px;">
        <input type="number" id="f-punti-min" placeholder="Min" style="width: 50%">
        <input type="number" id="f-punti-max" placeholder="Max" style="width: 50%">
      </div>
    </div>
    <button class="btn-reset-filter" id="btn-reset">✕ Azzera filtri</button>
  </aside>

  <main class="main-content">
    <div class="page-title">
      <h2>📊 Tutte le Partecipazioni</h2>
    </div>

    <div class="spinner" id="spinner"></div>
    <div id="results-container" class="table-container-scroll" style="margin-bottom: 1.5rem;"></div>
  </main>
</div>

<?php include 'footer.php'; ?>

<script>
$(document).ready(function () {
  <?php if (!empty($_GET['utente'])): ?>
    $('#f-utente').val("<?= htmlspecialchars($_GET['utente']) ?>");
  <?php endif; ?>
  <?php if (!empty($_GET['quiz_codice'])): ?>
    $('#f-quiz-codice').val("<?= htmlspecialchars($_GET['quiz_codice']) ?>");
  <?php endif; ?>

  cercaPartecipazioni();

  let timer;
  $('#f-quiz-codice, #f-quiz-titolo, #f-utente, #f-punti-min, #f-punti-max').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(cercaPartecipazioni, 350);
  });

  $('#btn-reset').on('click', function () {
    $('#f-quiz-codice').val('');
    $('#f-quiz-titolo').val('');
    $('#f-utente').val('');
    $('#f-punti-min').val('');
    $('#f-punti-max').val('');
    cercaPartecipazioni();
  });

  $(document).on('click', '.table-container-scroll th.sortable', function() {
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
});

function cercaPartecipazioni() {
  $('#spinner').show();
  $('#results-container').html('');
  $.ajax({
    url: 'ajax/search_partecipazioni.php',
    type: 'GET',
    data: {
      quiz_codice: $('#f-quiz-codice').val(),
      quiz_titolo: $('#f-quiz-titolo').val(),
      utente: $('#f-utente').val(),
      punti_min: $('#f-punti-min').val(),
      punti_max: $('#f-punti-max').val()
    },
    success: function (data) {
      $('#spinner').hide();
      $('#results-container').html(data);
    },
    error: function() {
      $('#spinner').hide();
      $('#results-container').html('<div class="alert alert-error">⚠ Errore durante la ricerca.</div>');
    }
  });
}
</script>

