<?php
session_start();

$fichier = __DIR__ . '/taches.json';
$taches = file_exists($fichier) ? json_decode(file_get_contents($fichier), true) : [];

if (!is_array($taches)) {
    $taches = [];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'ajouter':
            if (!empty(trim($_POST['titre'] ?? ''))) {
                $nouveauId = empty($taches) ? 1 : max(array_column($taches, 'id')) + 1;
                $taches[] = [
                    'id' => $nouveauId,
                    'titre' => htmlspecialchars(trim($_POST['titre'])),
                    'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
                    'priorite' => in_array($_POST['priorite'] ?? '', ['basse', 'moyenne', 'haute']) ? $_POST['priorite'] : 'moyenne',
                    'statut' => 'à faire',
                    'date_creation' => date('Y-m-d H:i:s'),
                    'date_limite' => !empty($_POST['date_limite']) ? $_POST['date_limite'] : null
                ];
                file_put_contents($fichier, json_encode($taches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        case 'changer_statut':
            $id = (int) ($_POST['id'] ?? 0);
            $transitions = ['à faire' => 'en cours', 'en cours' => 'terminée', 'terminée' => 'terminée'];
            foreach ($taches as &$t) {
                if ($t['id'] === $id) {
                    $t['statut'] = $transitions[$t['statut']] ?? $t['statut'];
                    break;
                }
            }
            file_put_contents($fichier, json_encode($taches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $params = array_filter([
                'recherche' => $_POST['recherche'] ?? $_GET['recherche'] ?? '',
                'statut' => $_POST['statut'] ?? $_GET['statut'] ?? '',
                'priorite' => $_POST['priorite'] ?? $_GET['priorite'] ?? ''
            ]);
            $query = $params ? '?' . http_build_query($params) : '';
            header('Location: ' . $_SERVER['PHP_SELF'] . $query);
            exit;

        case 'supprimer':
            $id = (int) ($_POST['id'] ?? 0);
            $taches = array_values(array_filter($taches, fn($t) => $t['id'] !== $id));
            file_put_contents($fichier, json_encode($taches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}


$recherche = trim($_GET['recherche'] ?? '');
$filtreStatut = $_GET['statut'] ?? '';
$filtrePriorite = $_GET['priorite'] ?? '';

$tachesFiltrees = $taches;
if (!empty($recherche)) {
    $mot = mb_strtolower($recherche);
    $tachesFiltrees = array_filter($tachesFiltrees, function($t) use ($mot) {
        return str_contains(mb_strtolower($t['titre'] ?? ''), $mot) || str_contains(mb_strtolower($t['description'] ?? ''), $mot);
    });
}
if (!empty($filtreStatut)) {
    $tachesFiltrees = array_filter($tachesFiltrees, fn($t) => ($t['statut'] ?? '') === $filtreStatut);
}
if (!empty($filtrePriorite)) {
    $tachesFiltrees = array_filter($tachesFiltrees, fn($t) => ($t['priorite'] ?? '') === $filtrePriorite);
}


$dateAujourdhui = date('Y-m-d');
$estEnRetard = function($t) use ($dateAujourdhui) {
    if (($t['statut'] ?? '') === 'terminée') return false;
    $limite = $t['date_limite'] ?? null;
    return $limite && $limite < $dateAujourdhui;
};


$total = count($taches);
$terminees = count(array_filter($taches, fn($t) => ($t['statut'] ?? '') === 'terminée'));
$enRetard = count(array_filter($taches, $estEnRetard));
$pourcentageTerminees = $total > 0 ? round(($terminees / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Tâches - L2 IAGE-GDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); min-height: 100vh; padding: 2rem 0; }
        .card { border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border-radius: 16px; overflow: hidden; }
        .card-header { background: linear-gradient(90deg, #2563eb, #1d4ed8); color: white; font-weight: 600; padding: 1rem 1.5rem; }
        .btn-primary { background: #2563eb; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
        h1 { color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3); margin-bottom: 2rem; }
        .tache-card { transition: transform 0.2s; border-radius: 12px; border-left: 4px solid #6b7280; }
        .tache-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .statut-a-faire { border-left-color: #f59e0b !important; }
        .statut-en-cours { border-left-color: #3b82f6 !important; }
        .statut-terminee { border-left-color: #22c55e !important; }
        .priorite-basse { --priorite: #6b7280; }
        .priorite-moyenne { --priorite: #f59e0b; }
        .priorite-haute { --priorite: #ef4444; }
        .badge-priorite-basse { background: #6b7280; }
        .badge-priorite-moyenne { background: #f59e0b; }
        .badge-priorite-haute { background: #ef4444; }
        .badge-statut-a-faire { background: #f59e0b; }
        .badge-statut-en-cours { background: #3b82f6; }
        .badge-statut-terminee { background: #22c55e; }
        .alerte-retard { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; }
        .stats-card { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center"><i class="bi bi-check2-square"></i> Gestionnaire de Tâches</h1>
        <p class="text-center text-white-50 mb-4">Mini Projet PHP L2 IAGE-GDA 2026 - Semestre 1</p>

    
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Ajouter une tâche</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="col-md-6">
                        <label class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="titre" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priorité</label>
                        <select name="priorite" class="form-select">
                            <option value="basse">Basse</option>
                            <option value="moyenne" selected>Moyenne</option>
                            <option value="haute">Haute</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date limite</label>
                        <input type="date" name="date_limite" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Ajouter</button>
                    </div>
                </form>
            </div>
        </div>

   
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h4 class="text-primary mb-0"><?= $total ?></h4>
                    <small class="text-muted">Total</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h4 class="text-success mb-0"><?= $terminees ?></h4>
                    <small class="text-muted">Terminées</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h4 class="text-info mb-0"><?= $pourcentageTerminees ?>%</h4>
                    <small class="text-muted">% terminées</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h4 class="<?= $enRetard > 0 ? 'text-danger' : 'text-secondary' ?> mb-0"><?= $enRetard ?></h4>
                    <small class="text-muted">En retard</small>
                </div>
            </div>
        </div>

   
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-funnel"></i> Recherche et filtres</div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Mot-clé (titre ou description)</label>
                        <input type="text" name="recherche" class="form-control" placeholder="Rechercher..."
                               value="<?= htmlspecialchars($recherche) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-select">
                            <option value="">Tous</option>
                            <option value="à faire" <?= $filtreStatut === 'à faire' ? 'selected' : '' ?>>À faire</option>
                            <option value="en cours" <?= $filtreStatut === 'en cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="terminée" <?= $filtreStatut === 'terminée' ? 'selected' : '' ?>>Terminée</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priorité</label>
                        <select name="priorite" class="form-select">
                            <option value="">Toutes</option>
                            <option value="basse" <?= $filtrePriorite === 'basse' ? 'selected' : '' ?>>Basse</option>
                            <option value="moyenne" <?= $filtrePriorite === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>
                            <option value="haute" <?= $filtrePriorite === 'haute' ? 'selected' : '' ?>>Haute</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i></button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

 
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-task"></i> Liste des tâches (<?= count($tachesFiltrees) ?>)
            </div>
            <div class="card-body">
                <?php if (empty($tachesFiltrees)): ?>
                    <p class="text-muted text-center py-4">Aucune tâche. Ajoutez-en une ci-dessus !</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($tachesFiltrees as $t): ?>
                            <?php $retard = $estEnRetard($t); ?>
                            <div class="card tache-card statut-<?= str_replace(' ', '-', $t['statut'] ?? '') ?> <?= $retard ? 'alerte-retard' : '' ?>">
                                <div class="card-body">
                                    <?php if ($retard): ?>
                                        <div class="alert alert-danger py-2 mb-2">
                                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Tâche en retard !</strong> Date limite dépassée (<?= htmlspecialchars($t['date_limite']) ?>)
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($t['titre'] ?? '') ?></h5>
                                            <?php if (!empty($t['description'])): ?>
                                                <p class="card-text text-muted small mb-2"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge badge-priorite-<?= $t['priorite'] ?? 'moyenne' ?>"><?= ucfirst($t['priorite'] ?? '') ?></span>
                                                <span class="badge badge-statut-<?= str_replace(' ', '-', $t['statut'] ?? '') ?>"><?= ucfirst($t['statut'] ?? '') ?></span>
                                                <?php if (!empty($t['date_limite'])): ?>
                                                    <span class="text-muted small"><i class="bi bi-calendar-event"></i> Limite : <?= htmlspecialchars($t['date_limite']) ?></span>
                                                <?php endif; ?>
                                                <span class="text-muted small"><i class="bi bi-clock"></i> Créée : <?= htmlspecialchars($t['date_creation'] ?? '') ?></span>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <?php if (($t['statut'] ?? '') !== 'terminée'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="changer_statut">
                                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                    <?php if ($recherche): ?><input type="hidden" name="recherche" value="<?= htmlspecialchars($recherche) ?>"><?php endif; ?>
                                                    <?php if ($filtreStatut): ?><input type="hidden" name="statut" value="<?= htmlspecialchars($filtreStatut) ?>"><?php endif; ?>
                                                    <?php if ($filtrePriorite): ?><input type="hidden" name="priorite" value="<?= htmlspecialchars($filtrePriorite) ?>"><?php endif; ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="<?= $t['statut'] === 'à faire' ? 'Marquer en cours' : 'Marquer terminée' ?>">
                                                        <i class="bi bi-arrow-right-circle"></i> <?= $t['statut'] === 'à faire' ? 'En cours' : 'Terminer' ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-check-all"></i> Terminée</span>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette tâche ?');">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

