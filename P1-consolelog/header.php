<?php
// ============================================================
// header.php - Componente condiviso: header + nav
// ============================================================

// $pageTitle - titolo della pagina (impostato da ogni pagina)
// $activeNav  - voce di menu attiva es. 'quiz', 'utenti'
if (!isset($pageTitle)) $pageTitle = 'console.log';
if (!isset($activeNav)) $activeNav = '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - console.log</title>
  <meta name="description" content="Piattaforma web per la gestione e partecipazione a quiz online">
  <link rel="stylesheet" href="<?= $cssPath ?? '' ?>css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<!-- HEADER -->
<header>
  <div class="header-brand">
    <div class="logo-icon">Q</div>
    <div>
      <h1>console<span>.log</span></h1>
      <div class="header-subtitle">Piattaforma Quiz Online</div>
    </div>
  </div>
  <div class="header-user-selector">
    <span class="header-user-label">👤 Utente attivo:</span>
    <input type="text" id="header-utente-input" list="dl-header-utenti"
           placeholder="— Cerca o scegli —" autocomplete="off">
    <datalist id="dl-header-utenti">
      <?php
        if (!isset($utenti)) {
          $pdo2 = getDB();
          $utenti_header = $pdo2->query("SELECT nomeUtente, nome, cognome FROM Utente ORDER BY cognome, nome")->fetchAll();
        } else {
          $utenti_header = $utenti;
        }
        foreach ($utenti_header as $uh):
      ?>
      <option value="<?= htmlspecialchars($uh['nome'] . ' ' . $uh['cognome'] . ' (' . $uh['nomeUtente'] . ')') ?>"></option>
      <?php endforeach; ?>
    </datalist>
  </div>
</header>
<script>
(function(){
  var inp = document.getElementById('header-utente-input');
  if (!inp) return;

  // Ripristina il valore salvato
  var saved = sessionStorage.getItem('utenteAttivoLabel');
  if (saved) inp.value = saved;

  // Estrae lo username dalla stringa "Nome Cognome (username)"
  function extractUsername(val) {
    var m = val.match(/\(([^)]+)\)\s*$/);
    return m ? m[1] : '';
  }

  // Al cambio (selezione datalist o uscita dal campo) aggiorna sessione via AJAX e notifica la pagina
  inp.addEventListener('change', function() {
    var username = extractUsername(this.value);
    var label = this.value;
    var relativePath = '<?= $cssPath ?? "" ?>';

    $.ajax({
      url: relativePath + 'ajax/set_session_user.php',
      type: 'POST',
      data: { username: username, label: label },
      dataType: 'json',
      success: function(res) {
        if (res.username) {
          sessionStorage.setItem('utenteAttivo', res.username);
          sessionStorage.setItem('utenteAttivoLabel', res.label);
        } else {
          sessionStorage.removeItem('utenteAttivo');
          sessionStorage.removeItem('utenteAttivoLabel');
        }

        // Lancia l'evento globale per l'aggiornamento dinamico senza reload
        window.dispatchEvent(new CustomEvent('utenteAttivoChanged', {
          detail: { username: res.username, label: res.label }
        }));
      }
    });
  });
})();
</script>

<!-- NAVIGAZIONE -->
<nav>
  <a href="<?= $cssPath ?? '' ?>index.php"
     class="<?= $activeNav === 'home' ? 'active' : '' ?>">
    <span class="nav-icon">🏠</span> Home
  </a>
  <a href="<?= $cssPath ?? '' ?>quiz.php"
     class="<?= $activeNav === 'quiz' ? 'active' : '' ?>">
    <span class="nav-icon">📋</span> Quiz
  </a>
  <a href="<?= $cssPath ?? '' ?>utenti.php"
     class="<?= $activeNav === 'utenti' ? 'active' : '' ?>">
    <span class="nav-icon">👥</span> Utenti
  </a>
  <a href="<?= $cssPath ?? '' ?>partecipazioni.php"
     class="<?= $activeNav === 'partecipazioni' ? 'active' : '' ?>">
    <span class="nav-icon">📊</span> Partecipazioni
  </a>
</nav>
