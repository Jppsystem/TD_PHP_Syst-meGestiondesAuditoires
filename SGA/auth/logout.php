<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && sga_csrf_valider(texte_depuis_valeur($_POST['csrf_token'] ?? ''))) {
    sga_deconnexion();
}

header('Location: login.php', true, 303);
exit;
