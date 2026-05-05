<?php
/**
 * Rapport d'occupation des salles (%).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planning.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$salles = sga_json_read('salles.json');
$data = sga_planning_load();
$entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
$rapport = sga_planning_rapport_occupation($entries, $salles);

$pageTitle = 'Occupation des salles';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Rapport d’occupation</h1>
        <p class="page-sub">Taux basé sur les créneaux occupés (10 par semaine par salle), selon le planning actuel.</p>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Synthèse globale</h2>
            <p style="font-size:1.25rem;margin:0">
                <strong><?php echo sga_e((string) $rapport['taux_global_pct']); ?> %</strong>
                <span style="color:var(--text-muted);font-size:0.9rem"> du temps-salle total</span>
            </p>
            <p style="color:var(--text-muted);font-size:0.9rem;margin:0.5rem 0 0">
                <?php echo (int) $rapport['creneaux_occupes']; ?> créneaux occupés sur
                <?php echo (int) $rapport['creneaux_totaux']; ?> (toutes salles confondues).
            </p>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Par salle</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Salle</th>
                            <th>Créneaux occupés</th>
                            <th>Total semaine</th>
                            <th>Taux</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rapport['par_salle'] as $sid => $row) : ?>
                        <tr>
                            <td><?php echo sga_e($row['nom']); ?></td>
                            <td><?php echo (int) $row['occupes']; ?></td>
                            <td><?php echo (int) $row['total_creneaux']; ?></td>
                            <td><strong><?php echo sga_e((string) $row['taux_pct']); ?> %</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p><a class="btn btn-ghost" href="planning_view.php">Retour au planning</a></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
