<?php
/**
 * Déconnexion sécurisée.
 */
require_once __DIR__ . '/includes/auth.php';

sga_auth_logout();
header('Location: login.php');
exit;
