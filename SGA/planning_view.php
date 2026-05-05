<?php
/**
 * Affichage du planning : tableau + grille hebdomadaire + détection de conflits.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planning.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$data = sga_planning_load();
$entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
$conflicts = sga_planning_detect_conflicts($entries);
$salles = sga_json_read('salles.json');
$promotions = sga_json_read('promotions.json');
$effectifs = sga_planning_effectifs_par_niveau($promotions);
$capWarn = sga_planning_check_capacities($entries, $salles, $effectifs);
$byCreneau = sga_planning_map_by_creneau($entries);

$jours = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi');
$slotsPerDay = 4;
$defs = sga_planning_creneaux_definitions();

$pageTitle = 'Planning';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Planning hebdomadaire</h1>
        <p class="page-sub">Données chargées depuis <code>data/planning.json</code> — rafraîchissement automatique à chaque chargement de page.</p>

        <?php if (!empty($data['generated_at'])) : ?>
            <p class="badge">Généré le : <?php echo sga_e($data['generated_at']); ?></p>
        <?php endif; ?>
        <?php if (!empty($data['last_manual_edit'])) : ?>
            <p class="badge">Dernière modification manuelle : <?php echo sga_e($data['last_manual_edit']); ?></p>
        <?php endif; ?>

        <?php if (count($conflicts) > 0) : ?>
            <div class="alert alert-error">
                <strong>Conflits détectés</strong>
                <ul style="margin:0.5rem 0 0;padding-left:1.2rem">
                    <?php foreach ($conflicts as $c) : ?>
                        <li><?php echo sga_e($c); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else : ?>
            <div class="alert alert-success">Aucun conflit de salle ou de promotion sur un même créneau.</div>
        <?php endif; ?>

        <?php if (count($capWarn) > 0) : ?>
            <div class="alert alert-info">
                <strong>Capacité</strong>
                <ul style="margin:0.5rem 0 0;padding-left:1.2rem">
                    <?php foreach ($capWarn as $w) : ?>
                        <li><?php echo sga_e($w); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Tableau détaillé</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cours</th>
                            <th>Promotion</th>
                            <th>Salle</th>
                            <th>Créneau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($entries) === 0) : ?>
                            <tr><td colspan="4">Aucune entrée. Générez un planning ou importez des données.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($entries as $e) : ?>
                        <tr>
                            <td><?php echo sga_e($e['cours_intitule']); ?></td>
                            <td><span class="badge"><?php echo sga_e($e['promotion_niveau']); ?></span></td>
                            <td><?php echo sga_e($e['salle_nom']); ?> <small style="color:var(--text-muted)">(<?php echo sga_e($e['salle_id']); ?>)</small></td>
                            <td><?php echo sga_e($e['creneau_label']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Vue grille (interactif visuel)</h2>
            <div class="table-wrap" style="overflow-x:auto">
                <div class="planning-grid">
                    <div class="pg-head"></div>
                    <?php foreach ($jours as $j) : ?>
                        <div class="pg-head"><?php echo sga_e($j); ?></div>
                    <?php endforeach; ?>

                    <?php
                    for ($si = 0; $si < $slotsPerDay; $si++) :
                        $sampleId = $si; // first day same slot
                        $slotLabel = $defs[$sampleId]['debut'] . '-' . $defs[$sampleId]['fin'];
                        ?>
                        <div class="pg-slot-label"><?php echo sga_e($slotLabel); ?></div>
                        <?php
                        for ($ji = 0; $ji < 5; $ji++) :
                            $cid = $ji * $slotsPerDay + $si;
                            $list = isset($byCreneau[$cid]) ? $byCreneau[$cid] : array();
                            ?>
                            <div class="pg-cell">
                                <?php foreach ($list as $entry) : ?>
                                    <div class="pg-course" title="<?php echo sga_e($entry['salle_nom']); ?>">
                                        <?php echo sga_e($entry['cours_intitule']); ?>
                                        <div style="font-size:0.65rem;opacity:0.85"><?php echo sga_e($entry['salle_nom']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <p class="inline-actions">
            <a class="btn btn-ghost" href="planning_edit.php">Modifier le planning</a>
            <a class="btn btn-primary" href="planning_generate.php">Regénérer</a>
        </p>

<?php require __DIR__ . '/includes/footer.php'; ?>
