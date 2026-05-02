<?php

declare(strict_types=1);

$titrePage = $titrePage ?? 'SGA - Système de Gestion des Auditoires';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SGA - Système de Gestion Automatique du planning des auditoires">
    <title><?= htmlspecialchars((string) $titrePage, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E📚%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <div class="hero-copy">
                <p class="eyebrow">SGA</p>
                <h1>Gestion des Auditoires</h1>
                <p class="hero-text">
                    Cette interface permet d'organiser les cours de tronc commun pour les promotions L1 à L4 
                    et les cours d'option de L3 et L4. Six salles sont disponibles : quatre auditoires, 
                    une salle machine et une salle management.
                </p>
            </div>
            <div class="hero-panel">
                <span class="panel-label">Salles disponibles</span>
                <strong>6</strong>
                <span class="panel-note">4 auditoires • 1 salle machine • 1 salle management</span>
            </div>
        </header>
        <?php
        $sgaNomUtilisateur = function_exists('sga_utilisateur_session') ? sga_utilisateur_session() : '';
        $sgaConnecte = function_exists('sga_est_connecte_complet') && sga_est_connecte_complet();
        $sgaCsrf = function_exists('sga_csrf_token') ? sga_csrf_token() : '';
        ?>
        <?php if ($sgaConnecte && $sgaCsrf !== ''): ?>
            <nav class="session-bar" aria-label="Session utilisateur">
                <span class="session-user"><?= htmlspecialchars($sgaNomUtilisateur, ENT_QUOTES, 'UTF-8'); ?></span>
                <form method="post" class="session-logout-form" action="auth/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($sgaCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn-logout">Déconnexion</button>
                </form>
            </nav>
        <?php endif; ?>
        <main class="contenu">
