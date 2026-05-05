<?php
/**
 * Gestion des cours (tronc commun / options).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$file = 'cours.json';
$optsFile = 'options.json';
$message = '';
$error = '';
$items = sga_json_read($file);
$options = sga_json_read($optsFile);
$niveaux = array('L1', 'L2', 'L3', 'L4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'add') {
            $intitule = sga_sanitize_string(isset($_POST['intitule']) ? $_POST['intitule'] : '');
            $type = isset($_POST['type']) ? $_POST['type'] : 'tronc_commun';
            $niv = isset($_POST['promotion_niveau']) ? $_POST['promotion_niveau'] : '';
            $optId = sga_sanitize_string(isset($_POST['option_id']) ? $_POST['option_id'] : '', 64);
            $duree = sga_validate_int_range(isset($_POST['duree_creneaux']) ? $_POST['duree_creneaux'] : null, 1, 2);
            if ($intitule === '') {
                $error = 'Intitulé obligatoire.';
            } elseif (!sga_validate_niveau($niv)) {
                $error = 'Promotion invalide.';
            } elseif ($duree === null) {
                $error = 'Durée (créneaux) invalide (1 ou 2).';
            } elseif (!in_array($type, array('tronc_commun', 'option'), true)) {
                $error = 'Type de cours invalide.';
            } elseif ($type === 'option' && $optId === '') {
                $error = 'Une option doit être sélectionnée pour un cours de type option.';
            } else {
                if ($type === 'tronc_commun') {
                    $optId = '';
                }
                $items[] = array(
                    'id' => sga_generate_id('c_'),
                    'intitule' => $intitule,
                    'type' => $type,
                    'promotion_niveau' => $niv,
                    'option_id' => $optId,
                    'duree_creneaux' => $duree,
                );
                if (sga_json_write($file, $items)) {
                    $message = 'Cours ajouté.';
                } else {
                    $error = 'Échec d\'écriture du fichier.';
                }
            }
        } elseif ($action === 'delete') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $items = array_values(array_filter($items, function ($row) use ($id) {
                return !(isset($row['id']) && $row['id'] === $id);
            }));
            if (sga_json_write($file, $items)) {
                $message = 'Cours supprimé.';
            } else {
                $error = 'Échec d\'écriture du fichier.';
            }
        } elseif ($action === 'update') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $intitule = sga_sanitize_string(isset($_POST['intitule']) ? $_POST['intitule'] : '');
            $type = isset($_POST['type']) ? $_POST['type'] : 'tronc_commun';
            $niv = isset($_POST['promotion_niveau']) ? $_POST['promotion_niveau'] : '';
            $optId = sga_sanitize_string(isset($_POST['option_id']) ? $_POST['option_id'] : '', 64);
            $duree = sga_validate_int_range(isset($_POST['duree_creneaux']) ? $_POST['duree_creneaux'] : null, 1, 2);
            if ($intitule === '' || !sga_validate_niveau($niv) || $duree === null) {
                $error = 'Données invalides.';
            } elseif ($type === 'option' && $optId === '') {
                $error = 'Option requise pour ce type de cours.';
            } else {
                if ($type === 'tronc_commun') {
                    $optId = '';
                }
                foreach ($items as &$c) {
                    if (isset($c['id']) && $c['id'] === $id) {
                        $c['intitule'] = $intitule;
                        $c['type'] = $type;
                        $c['promotion_niveau'] = $niv;
                        $c['option_id'] = $optId;
                        $c['duree_creneaux'] = $duree;
                        break;
                    }
                }
                unset($c);
                if (sga_json_write($file, $items)) {
                    $message = 'Cours mis à jour.';
                } else {
                    $error = 'Échec d\'écriture du fichier.';
                }
            }
        }
    }
    if ($error === '') {
        $items = sga_json_read($file);
    }
}

function sga_option_libelle($options, $oid)
{
    if ($oid === '') {
        return '—';
    }
    foreach ($options as $o) {
        if (isset($o['id']) && $o['id'] === $oid) {
            return isset($o['libelle']) ? $o['libelle'] : $oid;
        }
    }
    return $oid;
}

$pageTitle = 'Cours';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Gestion des cours</h1>
        <p class="page-sub">Tronc commun et cours d’option ; la durée est exprimée en nombre de créneaux de 4 h (1 ou 2).</p>

        <?php if ($message !== '') : ?><div class="alert alert-success"><?php echo sga_e($message); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Ajouter un cours</h2>
            <form method="post" action="cours.php" class="stack" style="max-width:560px">
                <?php echo sga_csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label class="label" for="intitule">Intitulé</label>
                    <input class="input" id="intitule" name="intitule" required maxlength="200">
                </div>
                <div class="form-row">
                    <label class="label" for="type">Type</label>
                    <select class="select" id="type" name="type" required>
                        <option value="tronc_commun">Tronc commun</option>
                        <option value="option">Option</option>
                    </select>
                </div>
                <div class="form-row">
                    <label class="label" for="promotion_niveau">Promotion</label>
                    <select class="select" id="promotion_niveau" name="promotion_niveau" required>
                        <?php foreach ($niveaux as $nv) : ?>
                            <option value="<?php echo sga_e($nv); ?>"><?php echo sga_e($nv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label class="label" for="option_id">Option (si type = option)</label>
                    <select class="select" id="option_id" name="option_id">
                        <option value="">—</option>
                        <?php foreach ($options as $o) : ?>
                            <option value="<?php echo sga_e($o['id']); ?>">
                                <?php echo sga_e($o['id'] . ' — ' . (isset($o['libelle']) ? $o['libelle'] : '') . ' (' . (isset($o['niveau']) ? $o['niveau'] : '') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label class="label" for="duree_creneaux">Durée (créneaux)</label>
                    <select class="select" id="duree_creneaux" name="duree_creneaux" required>
                        <option value="1">1 créneau (4 h)</option>
                        <option value="2">2 créneaux (8 h)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Liste des cours</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Édition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $c) : ?>
                        <tr>
                            <td><code><?php echo sga_e($c['id']); ?></code></td>
                            <td>
                                <form method="post" action="cours.php" class="stack" style="gap:0.65rem;max-width:100%">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sga_e($c['id']); ?>">
                                    <input class="input" name="intitule" value="<?php echo sga_e($c['intitule']); ?>" required style="max-width:100%">
                                    <div class="inline-actions" style="flex-wrap:wrap">
                                        <select class="select" name="type" style="max-width:160px">
                                            <option value="tronc_commun" <?php echo (isset($c['type']) && $c['type'] === 'tronc_commun') ? 'selected' : ''; ?>>Tronc commun</option>
                                            <option value="option" <?php echo (isset($c['type']) && $c['type'] === 'option') ? 'selected' : ''; ?>>Option</option>
                                        </select>
                                        <select class="select" name="promotion_niveau" style="max-width:90px">
                                            <?php foreach ($niveaux as $nv) : ?>
                                                <option value="<?php echo sga_e($nv); ?>" <?php echo (isset($c['promotion_niveau']) && $c['promotion_niveau'] === $nv) ? 'selected' : ''; ?>><?php echo sga_e($nv); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="select" name="option_id" style="max-width:200px">
                                            <option value="">—</option>
                                            <?php foreach ($options as $o) : ?>
                                                <option value="<?php echo sga_e($o['id']); ?>" <?php echo (isset($c['option_id']) && $c['option_id'] === $o['id']) ? 'selected' : ''; ?>>
                                                    <?php echo sga_e($o['libelle'] . ' (' . $o['niveau'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="select" name="duree_creneaux" style="max-width:140px">
                                            <option value="1" <?php echo (!isset($c['duree_creneaux']) || (int) $c['duree_creneaux'] === 1) ? 'selected' : ''; ?>>1 créneau</option>
                                            <option value="2" <?php echo (isset($c['duree_creneaux']) && (int) $c['duree_creneaux'] === 2) ? 'selected' : ''; ?>>2 créneaux</option>
                                        </select>
                                        <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.6rem">Enregistrer</button>
                                    </div>
                                </form>
                                <form method="post" action="cours.php" style="margin-top:0.5rem" onsubmit="return confirm('Supprimer ce cours ?');">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sga_e($c['id']); ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:0.35rem 0.6rem">Supprimer</button>
                                </form>
                                <p style="margin:0.35rem 0 0;font-size:0.8rem;color:var(--text-muted)">
                                    Option affichée : <?php echo sga_e(sga_option_libelle($options, isset($c['option_id']) ? $c['option_id'] : '')); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
