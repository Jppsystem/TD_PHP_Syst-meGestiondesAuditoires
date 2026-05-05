<?php
/**
 * Modification manuelle du planning (validation anti-conflit).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planning.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$salles = sga_json_read('salles.json');
$data = sga_planning_load();
$entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $eid = sga_sanitize_string(isset($_POST['entry_id']) ? $_POST['entry_id'] : '', 80);
        $sid = sga_sanitize_string(isset($_POST['salle_id']) ? $_POST['salle_id'] : '', 64);
        $defs = sga_planning_creneaux_definitions();
        $maxCreneauId = max(0, count($defs) - 1);
        $start = sga_validate_int_range(
            isset($_POST['creneau_start']) ? $_POST['creneau_start'] : null,
            0,
            $maxCreneauId
        );
        if ($eid === '' || $sid === '' || $start === null) {
            $error = 'Paramètres invalides.';
        } else {
            $duree = 1;
            foreach ($entries as $ent) {
                if (isset($ent['id']) && $ent['id'] === $eid) {
                    if (isset($ent['duree_creneaux'])) {
                        $duree = max(1, min(2, (int) $ent['duree_creneaux']));
                    } elseif (isset($ent['creneau_ids']) && is_array($ent['creneau_ids'])) {
                        $duree = max(1, min(2, count($ent['creneau_ids'])));
                    }
                    break;
                }
            }
            $seq = sga_planning_creneau_sequence($start, $duree);
            if ($seq === null) {
                $error = 'Créneau de départ incompatible avec la durée du cours (2 créneaux doivent être consécutifs le même jour).';
            } else {
                $res = sga_planning_update_entry($eid, $sid, $seq);
                if ($res['error'] !== '') {
                    $error = $res['error'];
                } else {
                    $message = 'Modification enregistrée.';
                    $data = sga_planning_load();
                    $entries = isset($data['entries']) ? $data['entries'] : array();
                }
            }
        }
    }
}

$defs = sga_planning_creneaux_definitions();
$starts1 = array();
$starts2 = sga_planning_valid_starts(2);
foreach ($defs as $d) {
    $starts1[] = $d['id'];
}

$pageTitle = 'Modifier le planning';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Modification manuelle</h1>
        <p class="page-sub">Chaque ligne vérifie les conflits de salle, de promotion et la capacité avant enregistrement.</p>

        <?php if ($message !== '') : ?><div class="alert alert-success"><?php echo sga_e($message); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <?php if (count($entries) === 0) : ?>
            <div class="alert alert-info">Aucun planning à modifier. <a href="planning_generate.php">Générez-en un</a> d’abord.</div>
        <?php endif; ?>

        <div class="stack">
            <?php foreach ($entries as $e) :
                $duree = 1;
                if (isset($e['duree_creneaux'])) {
                    $duree = max(1, min(2, (int) $e['duree_creneaux']));
                } elseif (isset($e['creneau_ids']) && is_array($e['creneau_ids'])) {
                    $duree = max(1, min(2, count($e['creneau_ids'])));
                }
                $startOpts = ($duree === 2) ? $starts2 : $starts1;
                $currentStart = isset($e['creneau_ids'][0]) ? (int) $e['creneau_ids'][0] : 0;
                ?>
                <div class="card glass">
                    <h3 style="margin:0 0 0.75rem;font-size:1rem"><?php echo sga_e($e['cours_intitule']); ?></h3>
                    <p style="margin:0 0 0.75rem;font-size:0.85rem;color:var(--text-muted)">
                        Promotion <?php echo sga_e($e['promotion_niveau']); ?> · Durée : <?php echo (int) $duree; ?> créneau(x)
                    </p>
                    <form method="post" action="planning_edit.php" class="inline-actions" style="flex-wrap:wrap;align-items:flex-end">
                        <?php echo sga_csrf_field(); ?>
                        <input type="hidden" name="entry_id" value="<?php echo sga_e($e['id']); ?>">
                        <div>
                            <label class="label">Salle</label>
                            <select class="select" name="salle_id" required style="min-width:200px">
                                <?php foreach ($salles as $s) : ?>
                                    <option value="<?php echo sga_e($s['id']); ?>" <?php echo ($e['salle_id'] === $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo sga_e($s['nom'] . ' (' . $s['capacite'] . ' pl.)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="label">Créneau de départ</label>
                            <select class="select" name="creneau_start" required style="min-width:280px">
                                <?php foreach ($startOpts as $cid) :
                                    $lab = isset($defs[$cid]) ? $defs[$cid]['label'] : $cid;
                                    ?>
                                    <option value="<?php echo (int) $cid; ?>" <?php echo ($currentStart === (int) $cid) ? 'selected' : ''; ?>>
                                        <?php echo sga_e($lab); ?><?php echo $duree === 2 ? ' (bloc 8 h)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding:0.45rem 0.9rem">Appliquer</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
