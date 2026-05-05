<?php
/**
 * En-tête HTML commun (affichage séparé de la logique).
 *
 * Variables attendues : $pageTitle (string)
 */
if (!isset($pageTitle)) {
    $pageTitle = 'SGA';
}
require_once __DIR__ . '/security.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sga_e($pageTitle); ?> — SGA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
    <div class="app-bg" aria-hidden="true"></div>
    <?php if (!empty($showNav)) : ?>
    <header class="app-header glass">
        <div class="header-inner">
            <a href="dashboard.php" class="brand">
                <span class="brand-mark">SGA</span>
                <span class="brand-text">Gestion des auditoires</span>
            </a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu">☰</button>
            <nav class="main-nav" id="mainNav">
                <a href="dashboard.php">Tableau de bord</a>
                <a href="salles.php">Salles</a>
                <a href="promotions.php">Promotions</a>
                <a href="options.php">Options</a>
                <a href="cours.php">Cours</a>
                <a href="planning_generate.php">Génération</a>
                <a href="planning_view.php">Planning</a>
                <a href="planning_edit.php">Modifier planning</a>
                <a href="rapport.php">Occupation</a>
                <a href="logout.php" class="nav-logout">Déconnexion</a>
            </nav>
        </div>
    </header>
    <?php endif; ?>
    <main class="main-content">
