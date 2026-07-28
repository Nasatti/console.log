function navigatePost(url, data) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrfmiddlewaretoken';
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfInput.value = csrfMeta.getAttribute('content');
    } else {
        const csrfEl = document.querySelector('[name=csrfmiddlewaretoken]');
        if (csrfEl) csrfInput.value = csrfEl.value;
    }
    form.appendChild(csrfInput);

    for (const key in data) {
        if (data.hasOwnProperty(key)) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = data[key];
            form.appendChild(hiddenField);
        }
    }
    
    document.body.appendChild(form);
    form.submit();
}

$(document).on('click', '.nav-post', function(e) {
    e.preventDefault();
    let url = $(this).data('url');
    let data = {};
    if ($(this).data('quiz-id')) data.quiz_id = $(this).data('quiz-id');
    if ($(this).data('part-id')) data.part_id = $(this).data('part-id');
    navigatePost(url, data);
});

// --- Da base.html ---

(function(){
  var inp = document.getElementById('header-utente-input');
  if (!inp) return;

  var saved = sessionStorage.getItem('utenteAttivoLabel');
  if (saved) inp.value = saved;

  function extractUsername(val) {
    var m = val.match(/\(([^)]+)\)\s*$/);
    return m ? m[1] : '';
  }

  inp.addEventListener('input', function() {
    if (this.value.length >= 3) {
      this.setAttribute('list', 'dl-header-utenti');
    } else {
      this.removeAttribute('list');
    }
  });

  inp.addEventListener('change', function() {
    var username = extractUsername(this.value);
    var label = this.value;

    $.ajax({
      url: window.AppConfig.urls.ajaxSetUtenteSessione,
      type: 'POST',
      data: { username: username, label: label, csrfmiddlewaretoken: window.AppConfig.csrfToken },
      dataType: 'json',
      success: function(res) {
        if (res.username) {
          sessionStorage.setItem('utenteAttivo', res.username);
          sessionStorage.setItem('utenteAttivoLabel', res.label);
        } else {
          sessionStorage.removeItem('utenteAttivo');
          sessionStorage.removeItem('utenteAttivoLabel');
        }
        window.dispatchEvent(new CustomEvent('utenteAttivoChanged', {
          detail: { username: res.username, label: res.label }
        }));
      }
    });
  });
})();

// --- Da quiz_lista.html ---

// Variabili globali di stato per la lista quiz
var quizSortCol = 'dataFine';
var quizSortDir = 'desc';
var quizPage = 1;

function initQuizLista() {
  

  cercaQuiz();

  let timer;
  $('#f-titolo, #f-creatore').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function(){ quizPage = 1; cercaQuiz(); }, 350);
  });
  
  $('#f-creatore').on('input', function () {
    if ($(this).val().length >= 3) {
      $(this).attr('list', 'dl-creatore');
    } else {
      $(this).removeAttr('list');
    }
  });
  $('input[name="f-stato"]').on('change', function () { quizPage = 1; cercaQuiz(); });
  $('#f-dal, #f-al').on('change', function () { quizPage = 1; cercaQuiz(); });

  $('#btn-reset').on('click', function () {
    $('#f-titolo').val('');
    $('#f-creatore').val('');
    $('input[name="f-stato"]').prop('checked', false);
    $('#f-dal, #f-al').val('');
    quizPage = 1;
    quizSortCol = 'dataFine';
    quizSortDir = 'desc';
    cercaQuiz();
  });
}

function syncOwnerButtons() {
  var utente = sessionStorage.getItem('utenteAttivo') || '';
  document.querySelectorAll('tr[data-creatore]').forEach(function(row) {
    var isOwner = (utente && row.getAttribute('data-creatore') === utente);
    var noPart  = row.getAttribute('data-partecipazioni') === '0';
    var isFuturo = row.getAttribute('data-futuro') === '1';
    row.querySelectorAll('.owner-btn').forEach(function(btn) {
      btn.style.display = (isOwner && noPart && isFuturo) ? 'inline-flex' : 'none';
    });
  });
}

function cercaQuiz() {
  $('#spinner').show();
  $('#results-container').html('');
  $.ajax({
    url: window.AppConfig.urls.ajaxSearchQuiz,
    type: 'GET',
    data: {
      titolo:   $('#f-titolo').val(),
      creatore: $('#f-creatore').val(),
      stato:    $('input[name="f-stato"]:checked').map(function(){ return $(this).val(); }).get().join(','),
      dal:      $('#f-dal').val(),
      al:       $('#f-al').val(),
      sort:     quizSortCol,
      dir:      quizSortDir,
      page:     quizPage,
    },
    success: function (data) {
      $('#spinner').hide();
      $('#results-container').html(data);
      syncOwnerButtons();
    },
    error: function () {
      $('#spinner').hide();
      $('#results-container').html('<div class="alert alert-error">⚠ Errore durante la ricerca. Riprova.</div>');
    }
  });
}

// Chiamate dai bottoni nel template AJAX
function cercaQuizConPagina(page) {
  quizPage = parseInt(page);
  cercaQuiz();
}

function cercaQuizOrdinato(col) {
  if (quizSortCol === col) {
    quizSortDir = quizSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    quizSortCol = col;
    quizSortDir = 'asc';
  }
  quizPage = 1;
  cercaQuiz();
}

window.addEventListener('utenteAttivoChanged', function() {
  syncOwnerButtons();
});

// --- Da utenti.html ---

var utentiSortCol = 'cognome';
var utentiSortDir = 'asc';
var utentiPage = 1;

function initUtentiLista() {
  

  cercaUtenti();

  let timer;
  $('#f-cognome, #f-nome, #f-email, #f-username, #f-min-quiz, #f-max-quiz, #f-min-part, #f-max-part').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function(){ utentiPage = 1; cercaUtenti(); }, 350);
  });

  $('#btn-reset').on('click', function () {
    $('#f-cognome, #f-nome, #f-email, #f-username').val('');
    $('#f-min-quiz, #f-max-quiz, #f-min-part, #f-max-part').val('');
    utentiPage = 1;
    utentiSortCol = 'cognome';
    utentiSortDir = 'asc';
    cercaUtenti();
  });
}

function cercaUtenti() {
  $('#spinner').show();
  $('#results-container').html('');
  $.ajax({
    url: window.AppConfig.urls.ajaxSearchUtenti,
    data: {
      cognome:  $('#f-cognome').val(),
      nome:     $('#f-nome').val(),
      email:    $('#f-email').val(),
      username: $('#f-username').val(),
      min_quiz: $('#f-min-quiz').val(),
      max_quiz: $('#f-max-quiz').val(),
      min_part: $('#f-min-part').val(),
      max_part: $('#f-max-part').val(),
      sort:     utentiSortCol,
      dir:      utentiSortDir,
      page:     utentiPage,
    },
    success: function(data) { $('#spinner').hide(); $('#results-container').html(data); },
    error: function() { $('#spinner').hide(); $('#results-container').html('<div class="alert alert-error">⚠ Errore durante la ricerca.</div>'); }
  });
}

function cercaUtentiConPagina(page) {
  utentiPage = parseInt(page);
  cercaUtenti();
}

function cercaUtentiOrdinati(col) {
  if (utentiSortCol === col) {
    utentiSortDir = utentiSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    utentiSortCol = col;
    utentiSortDir = 'asc';
  }
  utentiPage = 1;
  cercaUtenti();
}

// --- Da quiz_dettaglio.html ---
$(document).on('click', '.domanda-header-toggle', function() {
  const container = this.parentNode;
  const list = container.querySelector('.risposte-list');
  const arrow = this.querySelector('span:last-child');
  if (list && list.style.display === 'block') {
    list.style.display = 'none';
    arrow.textContent = '▼';
  } else if (list) {
    list.style.display = 'block';
    arrow.textContent = '▲';
  }
});

$(document).on('click', 'th.sortable', function() {
  var table = $(this).closest('table'), index = $(this).index();
  var asc = $(this).data('asc') || false;
  $(this).data('asc', !asc);
  var rows = table.find('tbody tr').toArray().sort(function(a, b) {
    var valA = $(a).children('td').eq(index).text().trim();
    var valB = $(b).children('td').eq(index).text().trim();
    function parseItDate(str) { var m = str.match(/(\d{2})\/(\d{2})\/(\d{4})/); return m ? new Date(m[3], m[2]-1, m[1]).getTime() : null; }
    var dA = parseItDate(valA), dB = parseItDate(valB);
    if (dA && dB) return asc ? dA - dB : dB - dA;
    var nA = parseFloat(valA.replace(/[^0-9.-]+/g,"")), nB = parseFloat(valB.replace(/[^0-9.-]+/g,""));
    if (!isNaN(nA) && !isNaN(nB)) return asc ? nA - nB : nB - nA;
    return asc ? valA.localeCompare(valB) : valB.localeCompare(valA);
  });
  for (var i = 0; i < rows.length; i++) { table.find('tbody').append(rows[i]); }
});

function syncDetailOwnerActions(utente) {
  document.querySelectorAll('.owner-action').forEach(function(el) {
    var creatore = el.dataset.creatore;
    var isOwner = (utente && utente === creatore);
    el.style.display = isOwner ? 'inline-flex' : 'none';
  });
}

// Global initialization for view-specific actions
$(document).ready(function() {
  // Sync owner actions if we are on a page that has them
  if (document.querySelectorAll('.owner-action').length > 0) {
    syncDetailOwnerActions(sessionStorage.getItem('utenteAttivo') || '');
    window.addEventListener('utenteAttivoChanged', function(e) {
      syncDetailOwnerActions(e.detail.username);
    });
  }

  // --- Da partecipa.html ---
  function syncPartecipante(utenteAttivo, utenteLabel) {
    var $hidden = $('#nomeUtente-hidden');
    var $info   = $('#partecipante-info');
    var $banner = $('#partecipante-banner');
    if (utenteAttivo) {
      $hidden.val(utenteAttivo);
      $info.html('<span class="extracted-style-24a85d">' + (utenteLabel ? utenteLabel : '@' + utenteAttivo) + '</span> &nbsp;<span class="extracted-style-339650">— selezionato in alto a destra</span>');
      $banner.css('border-left', '4px solid var(--success)');
    } else {
      $hidden.val('');
      $info.html('<span class="extracted-style-dae144">⚠ Nessun utente selezionato.</span><br><span class="extracted-style-d892a2">Seleziona il tuo utente nel selettore in alto a destra prima di partecipare.</span>');
      $banner.css('border-left', '4px solid var(--danger)');
    }
  }

  function updateProgress() {
    var totalDomandeStr = $('#quiz-play-form').data('total-domande');
    if (!totalDomandeStr) return;
    var totalDomande = Number(totalDomandeStr);
    const rispAns = new Set($('input[type="checkbox"]:checked').map(function() { return this.name; }).get()).size;
    const pct = Math.round((rispAns / totalDomande) * 100);
    $('#progress-fill').css('width', pct + '%');
  }

  if ($('#quiz-play-form').length > 0) {
    syncPartecipante(sessionStorage.getItem('utenteAttivo') || '', sessionStorage.getItem('utenteAttivoLabel') || '');
    window.addEventListener('utenteAttivoChanged', function(e) { syncPartecipante(e.detail.username, e.detail.label); });
    updateProgress();

    $(document).on('change', 'input[type="checkbox"]', function () {
      $(this).closest('.risposta-radio').toggleClass('selected', this.checked);
      updateProgress();
    });

    $('#quiz-play-form').on('submit', function (e) {
      var utente = $('#nomeUtente-hidden').val();
      if (!utente) {
        e.preventDefault();
        var $info = $('#partecipante-info');
        $info.html('<span class="extracted-style-dae144">⚠ DEVI selezionare il tuo utente nel selettore in alto a destra!</span>');
        $('#partecipante-banner').css('border-left', '4px solid var(--danger)');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }

  // --- Da home.html ---
  var counters = document.querySelectorAll('.stat-big[data-target]');
  if (counters.length > 0) {
    function animateCounter(el) {
      var target = parseInt(el.getAttribute('data-target'), 10);
      var duration = 1200;
      var step = Math.max(1, Math.ceil(target / (duration / 16)));
      var current = 0;
      function tick() {
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString('it-IT');
        if (current < target) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    }
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) { animateCounter(entry.target); observer.unobserve(entry.target); }
      });
    }, { threshold: 0.3 });
    counters.forEach(function(c) { observer.observe(c); });
  }

  // --- Global Table Pagination & Sorting ---
  $(document).on('click', '.btn-pag', function() {
    var page = $(this).data('page');
    if (typeof cercaQuizConPagina === 'function' && window.location.pathname.includes('/quiz')) {
      cercaQuizConPagina(page);
    } else if (typeof cercaUtentiConPagina === 'function' && window.location.pathname.includes('/utenti')) {
      cercaUtentiConPagina(page);
    } else if (typeof cercaPartecipazioniConPagina === 'function' && window.location.pathname.includes('/partecipazioni')) {
      cercaPartecipazioniConPagina(page);
    }
  });

  $(document).on('click', 'th.th-sort', function() {
    var col = $(this).data('col');
    if (typeof cercaQuizOrdinato === 'function' && window.location.pathname.includes('/quiz')) {
      cercaQuizOrdinato(col);
    } else if (typeof cercaUtentiOrdinati === 'function' && window.location.pathname.includes('/utenti')) {
      cercaUtentiOrdinati(col);
    } else if (typeof cercaPartecipazioniOrdinate === 'function' && window.location.pathname.includes('/partecipazioni')) {
      cercaPartecipazioniOrdinate(col);
    }
  });

  // --- Da quiz_form.html ---
  if ($('#quiz-form').length > 0) {
    let $form = $('#quiz-form');
    let isEditMode = $form.data('is-edit') === 1;
    let domandaCount = parseInt($form.data('domande-length')) || 0;

    function renumberDomande() {
      $('#domande-container .domanda-block').each(function(i) {
        $(this).attr('data-idx', i);
        $(this).find('.d-num').text(i + 1);
      });
      domandaCount = $('#domande-container .domanda-block').length;
    }

    function addRisposta(dIdx, rIdx) {
      return `
        <div class="risposta-block">
          <input type="text" name="risposta_testo_${dIdx+1}[]" placeholder="Testo risposta..." required>
          <label>Punteggio (corretta):</label>
          <input type="number" name="risposta_punti_${dIdx+1}[]"
                 placeholder="vuoto=sbagliata" step="0.5" min="0.5" class="punteggio-input">
          <input type="hidden" name="risposta_tipo_${dIdx+1}[]" value="Sbagliata">
          <button type="button" class="btn btn-danger btn-sm remove-risposta">✕</button>
        </div>`;
    }

    function addDomanda() {
      const idx = domandaCount;
      const html = `
        <div class="domanda-block" data-idx="${idx}">
          <div class="domanda-block-header">
            <span>Domanda #<span class="d-num">${idx + 1}</span></span>
            <button type="button" class="btn btn-danger btn-sm remove-domanda">✕ Rimuovi</button>
          </div>
          <div class="form-group">
            <label>Testo della domanda <span class="req-star">*</span></label>
            <textarea name="domanda_testo[]" rows="2" placeholder="Inserisci la domanda..." required></textarea>
          </div>
          <div class="risposte-container">
            ${addRisposta(idx, 0)}
            ${addRisposta(idx, 1)}
          </div>
          <button type="button" class="btn btn-outline btn-sm add-risposta extracted-style-d79ce2">➕ Aggiungi Risposta</button>
        </div>`;
      $('#domande-container').append(html);
      domandaCount++;
    }

    function aggiornaTipiRisposta() {
      $('.risposta-block').each(function() {
        var pt = $(this).find('.punteggio-input').val().trim();
        var tipoInput = $(this).find('input[name*="risposta_tipo"]');
        if (pt !== '' && parseFloat(pt) > 0) {
          tipoInput.val('Corretta');
        } else {
          tipoInput.val('Sbagliata');
        }
      });
    }

    function mostraMsg(testo) {
      var $m = $('#msg-form-inline');
      $m.addClass('alert-error').removeClass('alert-success').text(testo).show();
      $m[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function syncCreatore(utenteAttivo, utenteLabel) {
      var $hidden = $('#creatore-hidden');
      var $banner = $('#creatore-banner');
      if (utenteAttivo) {
        $hidden.val(utenteAttivo);
        $banner.html('<span class="extracted-style-24a85d">' + (utenteLabel ? utenteLabel : '@' + utenteAttivo) + '</span> &nbsp;<span class="extracted-style-339650">— selezionato in alto a destra</span>');
        $banner.closest('.form-group').css('border-left','4px solid var(--success)').css('padding-left','10px');
      } else {
        $hidden.val('');
        $banner.html('<span class="extracted-style-dae144">⚠ Nessun utente selezionato.</span><br><span class="extracted-style-d892a2">Seleziona il tuo utente nel selettore in alto a destra.</span>');
        $banner.closest('.form-group').css('border-left','4px solid var(--danger)').css('padding-left','10px');
      }
    }

    renumberDomande();

    $('#add-domanda').on('click', function () {
      addDomanda();
      renumberDomande();
    });

    $(document).on('click', '.remove-domanda', function () {
      $(this).closest('.domanda-block').remove();
      renumberDomande();
    });

    $(document).on('click', '.add-risposta', function () {
      const $domanda = $(this).closest('.domanda-block');
      const dIdx = parseInt($domanda.attr('data-idx'));
      const rIdx = $domanda.find('.risposta-block').length;
      $domanda.find('.risposte-container').append(addRisposta(dIdx, rIdx));
    });

    $(document).on('click', '.remove-risposta', function () {
      const $domanda = $(this).closest('.domanda-block');
      if ($domanda.find('.risposta-block').length <= 2) {
        mostraMsg('Ogni domanda deve avere almeno 2 risposte.');
        return;
      }
      $(this).closest('.risposta-block').remove();
    });

    $form.on('submit', function (e) {
      aggiornaTipiRisposta();
      $('#msg-form-inline').hide();

      if (!isEditMode) {
        var creatore = $('#creatore-hidden').val();
        if (!creatore) {
          e.preventDefault();
          mostraMsg('Seleziona prima il tuo utente tramite il selettore in alto a destra.');
          return;
        }
      }

      if ($('.domanda-block').length > 0) {
        var allValid = true;
        $('#domande-container .domanda-block').each(function (i) {
          var hasCorrect = false;
          $(this).find('.punteggio-input').each(function () {
            var val = $(this).val().trim();
            if (val !== '' && !isNaN(val) && parseFloat(val) > 0) hasCorrect = true;
          });
          if (!hasCorrect) {
            mostraMsg('La domanda #' + (i + 1) + ' deve avere almeno una risposta corretta (punteggio > 0).');
            allValid = false;
            return false;
          }
        });
        if (!allValid) { e.preventDefault(); return; }
      }
    });

    if (!isEditMode) {
      syncCreatore(sessionStorage.getItem('utenteAttivo') || '', sessionStorage.getItem('utenteAttivoLabel') || '');
      window.addEventListener('utenteAttivoChanged', function(e) { syncCreatore(e.detail.username, e.detail.label); });
    }
  }

  // --- Da quiz_lista.html, utenti.html, partecipazioni.html ---
  if ($('#f-creatore').length > 0 && typeof initQuizLista === 'function') {
    const filter = $('#f-creatore').data('prefilter');
    if (filter) $('#f-creatore').val(filter);
    initQuizLista();
  }

  if ($('#f-username').length > 0 && typeof initUtentiLista === 'function') {
    const filter = $('#f-username').data('prefilter');
    if (filter) $('#f-username').val(filter);
    initUtentiLista();
  }

  if ($('#f-quiz-codice').length > 0 && typeof initPartecipazioniLista === 'function') {
    const filterCodice = $('#f-quiz-codice').data('prefilter');
    if (filterCodice) $('#f-quiz-codice').val(filterCodice);
    const filterUtente = $('#f-utente').data('prefilter');
    if (filterUtente) $('#f-utente').val(filterUtente);
    initPartecipazioniLista();
  }
});

// --- Da partecipazioni.html ---
var partSortCol = 'data';
var partSortDir = 'desc';
var partPage = 1;

window.cercaPartecipazioni = function() {
  $('#spinner').show();
  $('#results-container').html('');
  $.ajax({
    url: window.AppConfig && window.AppConfig.urls && window.AppConfig.urls.ajaxSearchPartecipazioni ? window.AppConfig.urls.ajaxSearchPartecipazioni : '/ajax/partecipazioni/',
    type: 'GET',
    data: {
      quiz_codice: $('#f-quiz-codice').val(),
      quiz_titolo: $('#f-quiz-titolo').val(),
      utente:      $('#f-utente').val(),
      perc_min:    $('#f-perc-min').val(),
      perc_max:    $('#f-perc-max').val(),
      data_dal:    $('#f-data-dal').val(),
      data_al:     $('#f-data-al').val(),
      sort:        partSortCol,
      dir:         partSortDir,
      page:        partPage,
    },
    success: function (data) { $('#spinner').hide(); $('#results-container').html(data); },
    error: function() { $('#spinner').hide(); $('#results-container').html('<div class="alert alert-error">⚠ Errore durante la ricerca.</div>'); }
  });
}

window.cercaPartecipazioniConPagina = function(page) {
  partPage = parseInt(page);
  window.cercaPartecipazioni();
}

window.cercaPartecipazioniOrdinate = function(col) {
  if (partSortCol === col) {
    partSortDir = partSortDir === 'asc' ? 'desc' : 'asc';
  } else {
    partSortCol = col;
    partSortDir = 'asc';
  }
  partPage = 1;
  window.cercaPartecipazioni();
}

window.initPartecipazioniLista = function() {
  $('#f-utente').on('input', function () {
    if ($(this).val().length >= 3) {
      $(this).attr('list', 'dl-utente');
    } else {
      $(this).removeAttr('list');
    }
  });

  window.cercaPartecipazioni();

  let timer;
  $('#f-quiz-codice, #f-quiz-titolo, #f-utente, #f-perc-min, #f-perc-max').on('input', function () {
    clearTimeout(timer);
    timer = setTimeout(function(){ partPage = 1; window.cercaPartecipazioni(); }, 350);
  });
  $('#f-data-dal, #f-data-al').on('change', function(){ partPage = 1; window.cercaPartecipazioni(); });

  $('#btn-reset').on('click', function () {
    $('#f-quiz-codice, #f-quiz-titolo, #f-utente, #f-perc-min, #f-perc-max').val('');
    $('#f-data-dal, #f-data-al').val('');
    partPage = 1;
    partSortCol = 'data';
    partSortDir = 'desc';
    window.cercaPartecipazioni();
  });
};
