<?php

declare(strict_types=1);

$titrePage = $titrePage ?? 'SGA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $titrePage, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <div class="hero-copy">
                <p class="eyebrow">SGA</p>
                <h1>Systeme de gestion automatique du planning</h1>
                <p class="hero-text">
                    Cette interface organise les cours de tronc commun pour L1 a L4 et les cours
                    d option de L3 et L4, avec six salles disponibles sur des creneaux de 4 heures.
                </p>
            </div>
            <div class="hero-panel">
                <span class="panel-label">Salles disponibles</span>
                <strong>6</strong>
                <span class="panel-note">4 auditoires, 1 salle machine, 1 salle management</span>
            </div>
        </header>
        <main class="contenu">
