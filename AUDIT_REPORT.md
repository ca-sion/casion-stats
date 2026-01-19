# Rapport d'Audit et Précomisations - CA Sion Stats

Après avoir migré le projet vers Laravel 12 et analysé sa structure, voici un rapport détaillé sur les axes d'amélioration identifiés, du niveau technique au macro-projet.

---

## 🛠 1. Architecture Technique

### Découplage de la Logique (Controller)
Actuellement, le [HomeController](file:///Users/michael/Sites/clients/casion-stats/app/Http/Controllers/HomeController.php#11-61) porte toute la responsabilité : filtrage, tri, dédoublage et affichage.
- **Action** : Extraire la logique de récupération des statistiques dans une classe de service (`StatsService`) ou utiliser des **Eloquent Scopes** sur le modèle [Result](file:///Users/michael/Sites/clients/casion-stats/app/Models/Result.php#9-52).
- **Bénéfice** : Code plus lisible, réutilisable (ex: pour une future API) et plus facile à tester.

### Gestion des Performances (Sorting)
Le tri utilise un `CAST` SQL brut car les performances sont stockées en `string`. C'est fragile et peu performant.
- **Action** : 
    1. Ajouter une colonne `performance_value` (integer/decimal) pour stocker une valeur "normalisée" (ex: millisecondes pour le temps, centimètres pour les sauts).
    2. Utiliser un **Attribute Wrapper** ou une **Cast Class** Laravel pour transformer cette valeur en string lisible dans la vue.
- **Bénéfice** : Tris ultra-rapides, simplification des requêtes et élimination des erreurs de tri SQL.

### Tests Automatisés
Le projet manque de couverture de tests.
- **Action** : Créer des tests PEST pour :
    - Vérifier que les filtres (catégorie/genre) retournent les bons résultats.
    - S'assurer que le dédoublage (`unique('athlete_id')`) garde bien la meilleure performance.
- **Bénéfice** : Éviter les régressions lors de l'ajout de nouvelles fonctionnalités.

---

## ✨ 2. Niveau Fonctionnel

### Expérience Utilisateur (Frontend)
Le système actuel recharge la page entière à chaque changement de filtre.
- **Action** : Migrer vers **Laravel Livewire** ou **Inertia.js**.
- **Bénéfice** : Filtrage instantané sans rechargement, sensation d'application "Premium" et fluide.

### Fiches Athlètes
L'application se concentre sur les épreuves.
- **Action** : Créer une page de profil par athlète listant sa progression historique, ses records personnels (PB) par discipline et ses médailles.
- **Bénéfice** : Valorisation des sportifs du club.

### Outils d'Exportation
Les statistiques sont souvent utilisées pour des rapports officiels ou des archives.
- **Action** : Ajouter un bouton d'exportation vers **CSV/Excel** ou **PDF** généré proprement.
- **Bénéfice** : Utilité pratique accrue pour les entraîneurs et le comité du club.

---

## 📊 3. Gestion des Données

### Nettoyage & Validation
Le mode "Fix" identifie des erreurs que la base de données ne devrait pas permettre.
- **Action** : 
    1. Ajouter des **Database Constraints** (ex: types de genre limités).
    2. Créer une commande artisan `stats:validate` qui scanne la base et génère un rapport d'erreurs au lieu de le faire dans la vue.
- **Bénéfice** : Base de données saine et intègre.

### Normalisation des Catégories
La logique d'âge est calculée dynamiquement dans la vue.
- **Action** : Définir plus précisément les règles de catégories (U18, U20, etc.) dans une table de configuration ou un fichier de config dédié.

---

## 🌐 4. Vision Macroscopique (Stratégie)

### Accessibilité & Mobilité
Même si les données s'arrêtent en 2016, l'outil est une archive précieuse.
- **Action** : Transformer le projet en **PWA (Progressive Web App)**.
- **Bénéfice** : Consultation hors-ligne (si mise en cache) et raccourci sur l'écran d'accueil pour les membres du club.

### Importation Automatisée
Le projet semble avoir des IDs liés à des systèmes externes (`seltec`, `alabus`).
- **Action** : Créer un moteur d'importation (via fichier CSV ou API) pour automatiser l'alimentation des données si de nouvelles archives sont retrouvées.
