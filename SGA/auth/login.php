<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

if (sga_est_connecte_complet()) {
    header('Location: ../index.php', true, 303);
    exit;
}

$erreursAuth = [];
$action = texte_depuis_valeur($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'auth_login') {
    $rep = sga_traiter_formulaire_login($_POST);
    $erreursAuth = $rep['erreurs'] ?? [];

    if ($erreursAuth === [] && ($rep['etape'] ?? '') === 'ok') {
        header('Location: ../index.php', true, 303);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'auth_totp') {
    $rep = sga_traiter_formulaire_totp($_POST);
    $erreursAuth = $rep['erreurs'] ?? [];

    if ($erreursAuth === [] && ($rep['etape'] ?? '') === 'ok') {
        header('Location: ../index.php', true, 303);
        exit;
    }
}

$titrePage = 'Connexion — SGA';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $diagnosticConfig = sga_traiter_authentification([]);
    if ($diagnosticConfig['erreurs'] !== []) {
        $erreursAuth = [...$erreursAuth, ...$diagnosticConfig['erreurs']];
    }
}

$sga_assets_base = '..';
require __DIR__ . '/login_view.php';
