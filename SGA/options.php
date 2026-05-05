<?php
/**
 * Gestion des options (L3 / L4).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$file = 'options.json';
$message = '';
$error = '';
$items = sga_json_read($file);
$niveauxOpt = array('L3', 'L4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'add') {
            $lib = sga_sanitize_string(isset($_POST['libelle']) ? $_POST['libelle'] : '');
            $niv = isset($_POST['niveau']) ? $_POST['niveau'] : '';
            if (!in_array($niv, $niveauxOpt, true)) {
                $error = 'Niveau option invalide (L3 ou L4).';
            } elseif ($lib === '') {
                $error = 'Libellé obligatoire.';
            } else {
                $items[] = array(
                    'id' => sga_generate_id('opt_'),
                    'niveau' => $niv,
                    'libelle' => $lib,
                );
                if (sga_json_write($file, $items)) {
                    $message = 'Option ajoutée.';
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
                $message = 'Option supprimée.';
            } else {
                $error = 'Échec d\'écriture du fichier.';
            }
        } elseif ($action === 'update') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $lib = sga_sanitize_string(isset($_POST['libelle']) ? $_POST['libelle'] : '');
            $niv = isset($_POST['niveau']) ? $_POST['niveau'] : '';
            if (!in_array($niv, $niveauxOpt, true) || $lib === '') {
                $error = 'Données invalides.';
            } else {
                foreach ($items as &$o) {
                    if (isset($o['id']) && $o['id'] === $id) {
                        $o['niveau'] = $niv;
                        $o['libelle'] = $lib;
                        break;
                    }
                }
                unset($o);
                if (sga_json_write($file, $items)) {
                    $message = 'Option mise à jour.';
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

$pageTitle = 'Options';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Gestion des options</h1>
        <p class="page-sub">Options de troisième et quatrième année (rattachées aux cours de type « option »).</p>

        <?php if ($message !== '') : ?><div class="alert alert-success"><?php echo sga_e($message); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Ajouter une option</h2>
            <form method="post" action="options.php" class="stack" style="max-width:480px">
                <?php echo sga_csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label class="label" for="niveau">Niveau</label>
                    <select class="select" id="niveau" name="niveau" required>
                        <?php foreach ($niveauxOpt as $nv) : ?>
                            <option value="<?php echo sga_e($nv); ?>"><?php echo sga_e($nv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label class="label" for="libelle">Libellé</label>
                    <input class="input" id="libelle" name="libelle" required maxlength="120">
                </div>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Liste des options</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Édition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $o) : ?>
                        <tr>
                            <td><code><?php echo sga_e($o['id']); ?></code></td>
                            <td>
                                <form method="post" action="options.php" class="inline-actions" style="flex-wrap:wrap">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sga_e($o['id']); ?>">
                                    <select class="select" name="niveau" style="max-width:100px">
                                        <?php foreach ($niveauxOpt as $nv) : ?>
                                            <option value="<?php echo sga_e($nv); ?>" <?php echo (isset($o['niveau']) && $o['niveau'] === $nv) ? 'selected' : ''; ?>><?php echo sga_e($nv); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input class="input" name="libelle" value="<?php echo sga_e($o['libelle']); ?>" required style="min-width:160px">
                                    <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.6rem">Enregistrer</button>
                                </form>
                                <form method="post" action="options.php" style="display:inline-block;margin-left:0.25rem" onsubmit="return confirm('Supprimer cette option ?');">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sga_e($o['id']); ?>">
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
