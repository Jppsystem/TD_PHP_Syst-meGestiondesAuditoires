<?php
/**
 * Gestion des promotions L1–L4 et effectifs.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$file = 'promotions.json';
$message = '';
$error = '';
$items = sga_json_read($file);
$niveaux = array('L1', 'L2', 'L3', 'L4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'add') {
            $lib = sga_sanitize_string(isset($_POST['libelle']) ? $_POST['libelle'] : '');
            $niv = isset($_POST['niveau']) ? $_POST['niveau'] : '';
            $eff = sga_validate_int_range(isset($_POST['effectif']) ? $_POST['effectif'] : null, 1, 2000);
            if (!sga_validate_niveau($niv)) {
                $error = 'Niveau invalide.';
            } elseif ($lib === '') {
                $error = 'Libellé obligatoire.';
            } elseif ($eff === null) {
                $error = 'Effectif invalide.';
            } else {
                $items[] = array(
                    'id' => sga_generate_id('p_'),
                    'niveau' => $niv,
                    'libelle' => $lib,
                    'effectif' => $eff,
                );
                if (sga_json_write($file, $items)) {
                    $message = 'Promotion ajoutée.';
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
                $message = 'Promotion supprimée.';
            } else {
                $error = 'Échec d\'écriture du fichier.';
            }
        } elseif ($action === 'update') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $lib = sga_sanitize_string(isset($_POST['libelle']) ? $_POST['libelle'] : '');
            $niv = isset($_POST['niveau']) ? $_POST['niveau'] : '';
            $eff = sga_validate_int_range(isset($_POST['effectif']) ? $_POST['effectif'] : null, 1, 2000);
            if (!sga_validate_niveau($niv) || $lib === '' || $eff === null) {
                $error = 'Données invalides.';
            } else {
                foreach ($items as &$p) {
                    if (isset($p['id']) && $p['id'] === $id) {
                        $p['niveau'] = $niv;
                        $p['libelle'] = $lib;
                        $p['effectif'] = $eff;
                        break;
                    }
                }
                unset($p);
                if (sga_json_write($file, $items)) {
                    $message = 'Promotion mise à jour.';
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

$pageTitle = 'Promotions';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Gestion des promotions</h1>
        <p class="page-sub">Niveaux L1 à L4 et effectifs pour le calcul des capacités du planning.</p>

        <?php if ($message !== '') : ?><div class="alert alert-success"><?php echo sga_e($message); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Ajouter une promotion</h2>
            <form method="post" action="promotions.php" class="stack" style="max-width:520px">
                <?php echo sga_csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label class="label" for="niveau">Niveau</label>
                    <select class="select" id="niveau" name="niveau" required>
                        <?php foreach ($niveaux as $nv) : ?>
                            <option value="<?php echo sga_e($nv); ?>"><?php echo sga_e($nv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label class="label" for="libelle">Libellé</label>
                    <input class="input" id="libelle" name="libelle" required maxlength="120">
                </div>
                <div class="form-row">
                    <label class="label" for="effectif">Effectif</label>
                    <input class="input" type="number" id="effectif" name="effectif" min="1" max="2000" required>
                </div>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Liste</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Édition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $p) : ?>
                        <tr>
                            <td><code><?php echo sga_e($p['id']); ?></code></td>
                            <td>
                                <form method="post" action="promotions.php" class="inline-actions" style="flex-wrap:wrap;align-items:flex-end">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sga_e($p['id']); ?>">
                                    <div>
                                        <label class="label">Niveau</label>
                                        <select class="select" name="niveau" style="max-width:100px">
                                            <?php foreach ($niveaux as $nv) : ?>
                                                <option value="<?php echo sga_e($nv); ?>" <?php echo (isset($p['niveau']) && $p['niveau'] === $nv) ? 'selected' : ''; ?>><?php echo sga_e($nv); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label">Libellé</label>
                                        <input class="input" name="libelle" value="<?php echo sga_e($p['libelle']); ?>" required style="min-width:140px">
                                    </div>
                                    <div>
                                        <label class="label">Effectif</label>
                                        <input class="input" type="number" name="effectif" value="<?php echo sga_e($p['effectif']); ?>" min="1" max="2000" required style="width:90px">
                                    </div>
                                    <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.6rem">Enregistrer</button>
                                </form>
                                <form method="post" action="promotions.php" style="display:inline-block;margin-top:0.5rem" onsubmit="return confirm('Supprimer ?');">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sga_e($p['id']); ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:0.35rem 0.6rem">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
