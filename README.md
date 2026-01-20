# CA Sion - Statistiques

Ce projet est une application web de gestion et de consultation des statistiques de performance pour le Club d'Athlétisme (CA) Sion. Il permet de centraliser et d'analyser les résultats des athlètes sur différentes disciplines à travers les années (de 1959 à juin 2017).

## 📜 Historique des Données

Ce module a été créé le **12 décembre 2014 par Michael Ravedoni**. Il repose sur une architecture de données alimentée et consolidée par plusieurs contributeurs au fil des ans :

- **Période 1997 - 2012** : Base de données initialement alimentée par **René de Voogd**.
- **Période 2000 - 2025** : Base de données alimentée par Swiss Athletics depuis LaNet.
- **Archives 1962 - 2010** : Reprise d'anciennes bases de résultats. Notez que ces données historiques peuvent contenir des approximations ou des erreurs (résultats, disciplines, noms ou dates).
- **État des lieux actuel** : Les résultats sont globalement complets jusqu'en **décembre 2025**.

### ⚠️ Lacunes et Trous de données
L'analyse de la base de données révèle quelques zones d'ombre importantes :
- **2021** (indoor) : Absence totale de résultats (trou de données).

> [!TIP]
> Si vous possédez des archives pour combler ces trous ou si vous constatez une erreur, vos réclamations et annonces sont les bienvenues pour améliorer la précision des statistiques du club !

## 🚀 Fonctionnalités

- **Consultation des performances** : Visualisation des résultats par discipline.
- **Filtrage multicritères avancé** :
    - Par **discipline** avec recherche instantanée (dropdown searchable).
    - Par **catégorie d'athlète** (U18, Elite, etc.).
    - Par **genre** (Homme/Femme).
    - **Filtrage Inclusif** : Option permettant d'inclure toutes les catégories plus jeunes lors de la sélection d'une catégorie parent (ex: U16 affiche U16, U14, U12).
- **Classement automatique** : Les résultats sont triés selon la logique de performance propre à chaque discipline.
- **Meilleure performance unique** : Par défaut, le système ne conserve que le meilleur résultat par athlète pour garantir un classement propre.
- **Hub de Diagnostic & Correction ("Fix")** : Un outil complet pour la maintenance des données (accessible en `APP_ENV=local`) :
    - **Détection automatique d'anomalies** (Genre, Âge athlétique, Doublons, Formats suspect, Catégories sous-optimales).
    - **Actions en Un Clic** : Synchronisation du genre, changement de catégorie, suppression de doublons.
    - **Correction en Masse (Bulk Fix)** : Application groupée de toutes les corrections automatiques avec résumé de confirmation.
    - **Assistance SQL** : Requêtes `UPDATE/DELETE` prêtes à l'emploi.

## 🛠 Stack Technique

- **Framework** : Laravel 10+
- **Frontend** : Livewire 3+ (pour la réactivité sans rechargement), Blade, Tailwind CSS, DaisyUI
- **Build Tool** : Vite
- **Base de données** : MySQL / PostgreSQL (via Eloquent ORM)

## 📊 Architecture de la Base de Données

Le schéma de données est structuré pour refléter la complexité des compétitions d'athlétisme :

### Tables Principales

- **`athletes`** : Identité des sportifs.
    - `first_name`, `last_name`, `birthdate`, `genre`.
- **`athlete_categories`** : Définition des catégories d'âge et de genre.
    - `name`, `age_limit`, `genre`, `order`.
- **`disciplines`** : Types d'épreuves.
    - `name`, `sorting` (définit l'ordre de tri : ASC ou DESC), `seltec_id`, `alabus_id`.
- **`events`** : Compétitions et meetings.
    - `name`, `location`, `date`, `event_category_id`, `link`.
- **`event_categories`** : Groupement des événements (ex: Championnats, Meetings locaux).
- **`results`** : La table pivot centrale contenant les performances.
    - `athlete_id`, `discipline_id`, `event_id`, `athlete_category_id`.
    - `performance` (ex: "10.50", "7.15"), `rank`, `wind`, `metadata`.

### Relations
- Un **Athlète** a plusieurs **Résultats**.
- Un **Résultat** appartient à une **Discipline**, un **Athlète**, un **Événement** et une **Catégorie**.
- Un **Événement** appartient à une **Catégorie d'événement**.

## 🧠 Logique & Processus

### Traitement des Résultats
Le coeur de l'application réside dans la récupération et le tri des données via le `HomeController` :

1. **Extraction** : Récupération des résultats via le composant Livewire `StatsTable`.
2. **Filtrage & Inclusion** : Application des filtres de catégorie (stricts ou inclusifs) et de genre.
3. **Tri de Performance** : Basé sur `performance_normalized` pour assurer un tri mathématique fiable quel que soit le format d'affichage.
4. **Déduplication** : Application de `unique('athlete_id')` pour ne montrer que la performance de pointe (sauf en mode diagnostic où toutes les erreurs peuvent être visibles).

### Validation des Données
Le mode **Fix** ajoute une couche de contrôle qualité directement dans la vue, permettant d'identifier visuellement les données qui nécessitent une correction manuelle dans la base de données.

## 💻 Installation

1. Cloner le projet.
2. Installer les dépendances :
   ```bash
   composer install
   npm install
   ```
3. Configurer le fichier `.env` (BDD, etc.).
4. Lancer les migrations :
   ```bash
   php artisan migrate
   ```
5. Compiler les assets :
   ```bash
   npm run dev
   ```
6. Servir l'application :
   ```bash
   php artisan serve
   ```
