<?php
/**
 * Gestion des salles (CRUD) — traitement puis affichage.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$file = 'salles.json';
$message = '';
$error = '';
$items = sga_json_read($file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'add') {
            $nom = sga_sanitize_string(isset($_POST['nom']) ? $_POST['nom'] : '');
            $cap = sga_validate_int_range(isset($_POST['capacite']) ? $_POST['capacite'] : null, 1, 5000);
            if ($nom === '') {
                $error = 'Le nom de la salle est obligatoire.';
            } elseif ($cap === null) {
                $error = 'Capacité invalide (1 à 5000).';
            } else {
                $items[] = array(
                    'id' => sga_generate_id('s_'),
                    'nom' => $nom,
                    'capacite' => $cap,
                );
                if (sga_json_write($file, $items)) {
                    $message = 'Salle ajoutée.';
                } else {
                    $error = 'Échec d\'écriture du fichier.';
                }
            }
        } elseif ($action === 'delete') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $new = array();
            foreach ($items as $s) {
                if (isset($s['id']) && $s['id'] === $id) {
                    continue;
                }
                $new[] = $s;
            }
            $items = $new;
            if (sga_json_write($file, $items)) {
                $message = 'Salle supprimée.';
            } else {
                $error = 'Échec d\'écriture du fichier.';
            }
        } elseif ($action === 'update') {
            $id = sga_sanitize_string(isset($_POST['id']) ? $_POST['id'] : '', 64);
            $nom = sga_sanitize_string(isset($_POST['nom']) ? $_POST['nom'] : '');
            $cap = sga_validate_int_range(isset($_POST['capacite']) ? $_POST['capacite'] : null, 1, 5000);
            if ($nom === '' || $cap === null) {
                $error = 'Données invalides.';
            } else {
                foreach ($items as &$s) {
                    if (isset($s['id']) && $s['id'] === $id) {
                        $s['nom'] = $nom;
                        $s['capacite'] = $cap;
                        break;
                    }
                }
                unset($s);
                if (sga_json_write($file, $items)) {
                    $message = 'Salle mise à jour.';
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

$pageTitle = 'Salles';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Gestion des salles</h1>
        <p class="page-sub">Identifiant, nom et capacité pour chaque auditoire.</p>

        <?php if ($message !== '') : ?><div class="alert alert-success"><?php echo sga_e($message); ?></div><?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Ajouter une salle</h2>
            <form method="post" action="salles.php" class="stack" style="max-width:480px">
                <?php echo sga_csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label class="label" for="nom">Nom</label>
                    <input class="input" id="nom" name="nom" required maxlength="120">
                </div>
                <div class="form-row">
                    <label class="label" for="capacite">Capacité</label>
                    <input class="input" type="number" id="capacite" name="capacite" min="1" max="5000" required>
                </div>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>
        </div>

        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Liste des salles</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom, capacité et actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $s) : ?>
                        <tr>
                            <td><code><?php echo sga_e($s['id']); ?></code></td>
                            <td>
                                <form method="post" action="salles.php" class="inline-actions" style="flex-wrap:wrap">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo sga_e($s['id']); ?>">
                                    <input class="input" name="nom" value="<?php echo sga_e($s['nom']); ?>" required style="min-width:140px;max-width:220px">
                                    <input class="input" type="number" name="capacite" value="<?php echo sga_e($s['capacite']); ?>" min="1" max="5000" required style="width:90px">
                                    <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.6rem">Enregistrer</button>
                                </form>
                                <form method="post" action="salles.php" style="display:inline-block;margin-left:0.35rem" onsubmit="return confirm('Supprimer cette salle ?');">
                                    <?php echo sga_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo sga_e($s['id']); ?>">
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
