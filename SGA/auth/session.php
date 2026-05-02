<?php

declare(strict_types=1);

/**
 * Initialise la session SGA pour les pages sous auth/ ou la racine SGA protégée.
 * Exemple : require_once __DIR__ . '/auth/session.php'; (depuis index.php à la racine SGA).
 */
require_once dirname(__DIR__) . '/includes/fonctions_auth.php';

sga_demarrer_session_auth();
