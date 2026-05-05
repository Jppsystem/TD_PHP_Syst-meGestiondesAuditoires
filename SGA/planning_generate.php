<?php
/**
 * Génération automatique du planning et sauvegarde dans data/planning.json
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/planning.php';
require_once __DIR__ . '/includes/security.php';

sga_auth_require_full();

$message = '';
$error = '';
$resultMeta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sga_csrf_verify()) {
        $error = 'Jeton CSRF invalide.';
    } else {
        $result = sga_planning_generer();
        if (sga_planning_save($result)) {
            $resultMeta = $result;
            $message = $result['message'];
            if (!$result['success']) {
                $error = 'Planning partiel : certains cours n\'ont pas pu être placés.';
            }
        } else {
            $error = 'Impossible d\'écrire data/planning.json.';
        }
    }
}

$pageTitle = 'Génération du planning';
$showNav = true;
require __DIR__ . '/includes/header.php';
?>

        <h1 class="page-title">Génération du planning</h1>
        <p class="page-sub">Algorithme glouton : pas de conflit de salle sur un même créneau, capacité suffisante, une promotion ne peut pas avoir deux cours en même temps.</p>

        <?php if ($message !== '') : ?>
            <div class="alert <?php echo ($error !== '') ? 'alert-info' : 'alert-success'; ?>"><?php echo sga_e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo sga_e($error); ?></div><?php endif; ?>

        <div class="card glass">
            <form method="post" action="planning_generate.php" class="stack" style="max-width:420px">
                <?php echo sga_csrf_field(); ?>
                <p style="margin:0;color:var(--text-muted);font-size:0.9rem">La génération remplace le planning actuel dans <code>data/planning.json</code>.</p>
                <button type="submit" class="btn btn-primary">Lancer la génération</button>
            </form>
        </div>

        <?php if ($resultMeta !== null && !empty($resultMeta['unplaced'])) : ?>
        <div class="card glass">
            <h2 style="margin-top:0;font-size:1.1rem">Cours non placés</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Cours</th><th>Motif</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultMeta['unplaced'] as $u) : ?>
                        <tr>
                            <td><?php echo sga_e($u['intitule']); ?></td>
                            <td><?php echo sga_e($u['raison']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <p><a class="btn btn-ghost" href="planning_view.php">Voir le planning</a></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
