<?php
// ============================================================
// quiz.php - Lista Quiz: Ricerca e Lista Quiz
// CRUD: READ + ricerca filtrata via AJAX
// ============================================================
require_once 'db.php';

$pageTitle = 'Quiz';
$activeNav = 'quiz';

// Carica utenti per filtro creatore
$pdo = getDB();
$utenti = $pdo->query("SELECT nomeUtente, nome, cognome FROM Utente ORDER BY cognome, nome")->fetchAll();
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">

  <!-- SIDEBAR FILTRO -->
  <aside class="sidebar">
    <h2>🔍 Filtra Quiz</h2>
    <div class="filter-group">
      <label for="f-titolo">Titolo</label>
      <input type="text" id="f-titolo" placeholder="Es. Matematica…" autocomplete="off">
    </div>
    <div class="filter-group">
      <label for="f-creatore">Creatore</label>
      <input type="text" id="f-creatore" list="dl-creatore" placeholder="— Tutti —" autocomplete="off">
      <datalist id="dl-creatore">
        <?php foreach ($utenti as $u): ?>
          <option value="<?= htmlspecialchars($u['nome'] . ' ' . $u['cognome'] . ' (' . $u['nomeUtente'] . ')') ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="filter-group">
      <label for="f-stato">Stato</label>
      <select id="f-stato">
        <option value="">— Tutti —</option>
        <option value="aperto">✅ Aperto</option>
        <option value="chiuso">🔒 Chiuso</option>
        <option value="futuro">🕐 Non ancora iniziato</option>
      </select>
    </div>
    <div class="filter-group">
      <label for="f-dal">Data inizio dal</label>
      <input type="date" id="f-dal">
    </div>
    <div class="filter-group">
      <label for="f-al">Data fine entro</label>
      <input type="date" id="f-al">
    </div>
    <button class="btn-reset-filter" id="btn-reset">✕ Azzera filtri</button>
  </aside>

  <!-- CONTENUTO PRINCIPALE -->
  <main class="main-content">
    <div class="page-title">
      <h2>📋 Quiz Disponibili</h2>
      <div class="title-actions">
        <a href="quiz_form.php" class="btn btn-primary">➕ Nuovo Quiz</a>
      </div>
    </div>

    <div class="spinner" id="spinner"></div>

    <div id="results-container" class="table-container-scroll" style="margin-bottom: 1.5rem;">
      <!-- Risultati caricati via AJAX -->
    </div>
  </main>
</div>

<?php include 'footer.php'; ?>

<script src="js/main.js"></script>
<script>
// Carica i quiz all'avvio
$(document).ready(function () {
  <?php if (!empty($_GET['creatore'])): ?>
    $('#f-creatore').val("<?= htmlspecialchars($_GET['creatore']) ?>");
  <?php endif; ?>

  cercaQuiz();

  // Ricerca real-time su titolo
  let timer;
  $('#f-titolo, #f-creatore').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(cercaQuiz, 350);
  });

  // Ricerca al cambio altri filtri
  $('#f-stato, #f-dal, #f-al').on('change', function () {
    cercaQuiz();
  });

  $('#btn-reset').on('click', function () {
    $('#f-titolo').val('');
    $('#f-creatore, #f-stato').val('');
    $('#f-dal, #f-al').val('');
    cercaQuiz();
  });

  $(document).on('click', '.table-container-scroll th.sortable', function() {
    var table = $(this).closest('table');
    var index = $(this).index();
    var asc = $(this).data('asc') || false;
    $(this).data('asc', !asc);
    var rows = table.find('tbody tr').toArray().sort(function(a, b) {
      var valA = $(a).children('td').eq(index).text().trim();
      var valB = $(b).children('td').eq(index).text().trim();
      // Converti date in formato dd/mm/yyyy (o dd/mm/yyyy\ndd/mm/yyyy) in timestamp
      function parseItDate(str) {
        // Prende la prima data trovata nel testo della cella
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

function syncOwnerButtons() {
  var utente = sessionStorage.getItem('utenteAttivo') || '';
  document.querySelectorAll('tr[data-creatore]').forEach(function(row) {
    var isOwner = (utente && row.getAttribute('data-creatore') === utente);
    row.querySelectorAll('.owner-btn').forEach(function(btn) {
      btn.style.display = isOwner ? 'inline-flex' : 'none';
    });
  });
}

function cercaQuiz() {
  $('#spinner').show();
  $('#results-container').html('');

  $.ajax({
    url: 'ajax/search_quiz.php',
    type: 'GET',
    data: {
      titolo:   $('#f-titolo').val(),
      creatore: $('#f-creatore').val(),
      stato:    $('#f-stato').val(),
      dal:      $('#f-dal').val(),
      al:       $('#f-al').val()
    },
    success: function (data) {
      $('#spinner').hide();
      $('#results-container').html(data);
      syncOwnerButtons(); // Sincronizza i pulsanti all'inserimento
    },
    error: function () {
      $('#spinner').hide();
      $('#results-container').html('<div class="alert alert-error">⚠ Errore durante la ricerca. Riprova.</div>');
    }
  });
}

// Ascolta il cambio utente attivo dall'header per aggiornare la lista al volo
window.addEventListener('utenteAttivoChanged', function() {
  syncOwnerButtons();
});
</script>
