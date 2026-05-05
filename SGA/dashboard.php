<?php
/**
 * Tableau de bord administrateur.
 */
require_once __DIR__ . '/includes/auth.php';

sga_auth_require_full();

$pageTitle = 'Tableau de bord';
$showNav = true;
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/security.php';
?>

        <h1 class="page-title">Tableau de bord</h1>
        <p class="page-sub">Bienvenue dans le SGA. Gérez les salles, promotions, cours et le planning hebdomadaire.</p>

        <div class="grid-dash">
            <a class="dash-tile glass" href="salles.php">
                <h3>Salles</h3>
                <p>Capacités et identifiants des auditoires</p>
            </a>
            <a class="dash-tile glass" href="promotions.php">
                <h3>Promotions</h3>
                <p>L1 à L4 et effectifs</p>
            </a>
            <a class="dash-tile glass" href="options.php">
                <h3>Options</h3>
                <p>Parcours L3 / L4</p>
            </a>
            <a class="dash-tile glass" href="cours.php">
                <h3>Cours</h3>
                <p>Tronc commun et options</p>
            </a>
            <a class="dash-tile glass" href="planning_generate.php">
                <h3>Génération</h3>
                <p>Créer le planning sans conflit</p>
            </a>
            <a class="dash-tile glass" href="planning_view.php">
                <h3>Planning</h3>
                <p>Vue tableau et grille</p>
            </a>
            <a class="dash-tile glass" href="planning_edit.php">
                <h3>Modifier</h3>
                <p>Ajustements manuels contrôlés</p>
            </a>
            <a class="dash-tile glass" href="rapport.php">
                <h3>Occupation</h3>
                <p>Taux d’utilisation des salles</p>
            </a>
        </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
