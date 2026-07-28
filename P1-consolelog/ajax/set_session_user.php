<?php
// ============================================================
// ajax/set_session_user.php - Imposta l'utente attivo in sessione
// ============================================================
session_start();

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$label    = isset($_POST['label']) ? trim($_POST['label']) : '';

if ($username !== '') {
    $_SESSION['utenteAttivo'] = $username;
    $_SESSION['utenteAttivoLabel'] = $label;
} else {
    unset($_SESSION['utenteAttivo']);
    unset($_SESSION['utenteAttivoLabel']);
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'username' => $username,
    'label' => $label
]);
