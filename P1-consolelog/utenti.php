<?php
// ============================================================
// utenti.php - Ricerca e Lista Utenti
// ============================================================
require_once 'db.php';

$pageTitle = 'Utenti';
$activeNav = 'utenti';
$cssPath   = '';
?>
<?php include 'header.php'; ?>

<div class="main-wrapper">
  <aside class="sidebar">
    <h2>🔍 Filtra Utenti</h2>
    <div class="filter-group">
      <label for="f-nome">Nome / Cognome</label>
      <input type="text" id="f-nome" placeholder="Es. Mario…" autocomplete="off">
    </div>
    <div class="filter-group">
      <label for="f-username">Username</label>
      <input type="text" id="f-username" placeholder="Es. mario_r…" autocomplete="off">
    </div>
    <div class="filter-group">
      <label for="f-email">Email</label>
      <input type="text" id="f-email" placeholder="Es. mario@…" autocomplete="off">
    </div>
    <button class="btn-reset-filter" id="btn-reset">✕ Azzera filtri</button>
  </aside>

  <main class="main-content">
    <div class="page-title">
      <h2>👥 Utenti</h2>
    </div>

    <div class="spinner" id="spinner"></div>
    <div id="results-container" class="table-container-scroll" style="margin-bottom: 1.5rem;"></div>
  </main>
</div>

<?php include 'footer.php'; ?>

<script>
$(document).ready(function () {
  <?php if (!empty($_GET['username'])): ?>
    $('#f-username').val("<?= htmlspecialchars($_GET['username']) ?>");
  <?php endif; ?>

  cercaUtenti();

  let timer;
  $('#f-nome, #f-email, #f-username').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(cercaUtenti, 350);
  });

  $('#btn-reset').on('click', function () {
    $('#f-nome, #f-email, #f-username').val('');
    cercaUtenti();
  });

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
});

function cercaUtenti() {
  $('#spinner').show();
  $('#results-container').html('');
  $.ajax({
    url: 'ajax/search_utenti.php',
    data: { nome: $('#f-nome').val(), email: $('#f-email').val(), username: $('#f-username').val() },
    success: function(data) {
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
