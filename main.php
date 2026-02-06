<?php
// --- CONFIGURATION & DONNÉES ---
$fichierJson = 'data.json';

// Initialisation du fichier JSON (Structure des données)
// Le fichier stockera : id, titre, description, priorité, statut, date_creation, date_limite
if (!file_exists($fichierJson)) {
    file_put_contents($fichierJson, json_encode([]));
}

// Fonction de lecture
function lireTaches() {
    global $fichierJson;
    $contenu = file_get_contents($fichierJson);
    return json_decode($contenu, true) ?? [];
}

// Fonction d'écriture
function sauvegarderTaches($taches) {
    global $fichierJson;
    file_put_contents($fichierJson, json_encode($taches, JSON_PRETTY_PRINT));
}

$taches = lireTaches();

// --- TRAITEMENT DES FORMULAIRES (POST/GET) ---

// 1. AJOUT D'UNE TÂCHE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nouvelleTache = [
        'id' => uniqid(), // Identifiant unique
        'titre' => htmlspecialchars($_POST['titre']),
        'description' => htmlspecialchars($_POST['description']),
        'priorite' => $_POST['priorite'],
        'statut' => 'à faire', // Statut initial requis "à faire"
        'date_creation' => date('Y-m-d'), // Date générée automatiquement
        'date_limite' => $_POST['date_limite']
    ];
    $taches[] = $nouvelleTache;
    sauvegarderTaches($taches);
    header("Location: index.php");
    exit;
}

// 2. SUPPRESSION
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $taches = array_filter($taches, function($t) {
        return $t['id'] !== $_GET['id'];
    });
    sauvegarderTaches(array_values($taches)); // Réindexation
    header("Location: index.php");
    exit;
}

// 3. CHANGEMENT DE STATUT (Cycle: à faire -> en cours -> terminée)
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    foreach ($taches as &$t) {
        if ($t['id'] === $_GET['id']) {
            if ($t['statut'] === 'à faire') $t['statut'] = 'en cours';
            elseif ($t['statut'] === 'en cours') $t['statut'] = 'terminée';
            // Optionnel : boucler ou s'arrêter à terminée. Ici on s'arrête souvent à terminée, 
            // mais pour l'exemple on peut remettre à faire si on clique encore, ou bloquer.
        }
    }
    unset($t); // Rupture référence
    sauvegarderTaches($taches);
    header("Location: index.php");
    exit;
}

// --- LOGIQUE DE RECHERCHE, FILTRAGE ET STATS ---

// Récupération des filtres
$recherche = $_GET['recherche'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';
$filtrePriorite = $_GET['priorite'] ?? '';

// Filtrage des tâches pour l'affichage
$tachesAffichees = array_filter($taches, function($t) use ($recherche, $filtreStatut, $filtrePriorite) {
    // Recherche mot-clé (titre ou description)
    $matchRecherche = empty($recherche) || 
                      stripos($t['titre'], $recherche) !== false || 
                      stripos($t['description'], $recherche) !== false;
    
    // Filtre statut
    $matchStatut = empty($filtreStatut) || $t['statut'] === $filtreStatut;
    
    // Filtre priorité
    $matchPriorite = empty($filtrePriorite) || $t['priorite'] === $filtrePriorite;

    return $matchRecherche && $matchStatut && $matchPriorite;
});

// Calcul des Statistiques
$total = count($taches);
$terminees = count(array_filter($taches, fn($t) => $t['statut'] === 'terminée'));
$pourcentage = $total > 0 ? round(($terminees / $total) * 100) : 0;

// Calcul des retards (Date limite dépassée ET non terminée)
$enRetard = count(array_filter($taches, function($t) {
    return $t['statut'] !== 'terminée' && $t['date_limite'] < date('Y-m-d');
}));

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Projet PHP - Gestion Tâches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-task { transition: transform 0.2s; }
        .card-task:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        /* Style spécifique pour l'alerte de retard */
        .border-retard { border-left: 5px solid #dc3545 !important; background-color: #fff8f8; }
        .statut-badge { min-width: 80px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <h1 class="text-center mb-4">Gestion de Tâches</h1>

    <div class="row text-center mb-4">
        <div class="col-md-3">
            <div class="card p-3 shadow-sm text-primary">
                <h5>Total</h5>
                <p class="h2 fw-bold"><?= $total ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 shadow-sm text-success">
                <h5>Terminées</h5>
                <p class="h2 fw-bold"><?= $terminees ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 shadow-sm text-info">
                <h5>Progression</h5>
                <p class="h2 fw-bold"><?= $pourcentage ?>%</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 shadow-sm text-danger">
                <h5>En retard</h5>
                <p class="h2 fw-bold"><?= $enRetard ?></p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Nouvelle Tâche</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="ajouter">
                        <div class="mb-3">
                            <label class="form-label">Titre</label>
                            <input type="text" name="titre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priorité</label>
                            <select name="priorite" class="form-select">
                                <option value="basse">Basse</option>
                                <option value="moyenne">Moyenne</option>
                                <option value="haute">Haute</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Limite</label>
                            <input type="date" name="date_limite" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <form method="GET" class="card p-3 mb-4 shadow-sm">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="recherche" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($recherche) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="statut" class="form-select">
                            <option value="">Tous statuts</option>
                            <option value="à faire" <?= $filtreStatut=='à faire'?'selected':'' ?>>À faire</option>
                            <option value="en cours" <?= $filtreStatut=='en cours'?'selected':'' ?>>En cours</option>
                            <option value="terminée" <?= $filtreStatut=='terminée'?'selected':'' ?>>Terminée</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="priorite" class="form-select">
                            <option value="">Toutes priorités</option>
                            <option value="basse" <?= $filtrePriorite=='basse'?'selected':'' ?>>Basse</option>
                            <option value="moyenne" <?= $filtrePriorite=='moyenne'?'selected':'' ?>>Moyenne</option>
                            <option value="haute" <?= $filtrePriorite=='haute'?'selected':'' ?>>Haute</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100">Filtrer</button>
                    </div>
                </div>
            </form>

            <?php if (empty($tachesAffichees)): ?>
                <div class="alert alert-info text-center">Aucune tâche trouvée.</div>
            <?php else: ?>
                <?php foreach ($tachesAffichees as $task): 
                    // LOGIQUE DE RETARD & COULEURS
                    // Une tâche est en retard si elle n'est pas terminée ET date limite dépassée
                    $estEnRetard = ($task['statut'] !== 'terminée' && $task['date_limite'] < date('Y-m-d'));
                    
                    // Classes CSS dynamiques
                    $classeBordure = $estEnRetard ? 'border-retard' : '';
                    $couleurBadgeStatut = match($task['statut']) {
                        'terminée' => 'bg-success',
                        'en cours' => 'bg-warning text-dark',
                        default => 'bg-secondary'
                    };
                    $couleurPriorite = match($task['priorite']) {
                        'haute' => 'text-danger fw-bold',
                        'moyenne' => 'text-warning fw-bold',
                        default => 'text-primary fw-bold'
                    };
                ?>
                <div class="card card-task mb-3 <?= $classeBordure ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="card-title mb-1"><?= $task['titre'] ?></h5>
                                <small class="text-muted">Créé le : <?= $task['date_creation'] ?></small>
                            </div>
                            <a href="?action=toggle&id=<?= $task['id'] ?>" class="badge <?= $couleurBadgeStatut ?> text-decoration-none p-2 statut-badge">
                                <?= ucfirst($task['statut']) ?>
                            </a>
                        </div>
                        
                        <p class="card-text mt-2"><?= $task['description'] ?></p>
                        
                        <?php if ($estEnRetard): ?>
                            <div class="alert alert-danger py-1 px-2 d-inline-block small mb-2">
                                ⚠️ <strong>Retard !</strong> Date limite dépassée.
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <div>
                                <span class="me-3">Priorité : <span class="<?= $couleurPriorite ?>"><?= ucfirst($task['priorite']) ?></span></span>
                                <span>Date limite : <strong><?= $task['date_limite'] ?></strong></span>
                            </div>
                            <a href="?action=supprimer&id=<?= $task['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Confirmer la suppression ?')">Supprimer</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>