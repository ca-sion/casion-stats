# CA Sion - Statistiques

Ce projet est une application web de gestion et de consultation des statistiques de performance pour le Club d'Athlétisme (CA) Sion. Il permet de centraliser et d'analyser les résultats des athlètes sur différentes disciplines à travers les années (jusqu'en 2016).

## 🚀 Fonctionnalités

- **Consultation des performances** : Visualisation des résultats par discipline.
- **Filtrage multicritères** :
    - Par **discipline** (ex: 100m, Longueur, Poids).
    - Par **catégorie d'athlète** (U18, Elite, etc.).
    - Par **genre** (Homme/Femme).
- **Classement automatique** : Les résultats sont triés selon la logique propre à chaque discipline (temps le plus bas pour les courses, distance la plus élevée pour les lancers/sauts).
- **Meilleure performance unique** : Pour une sélection donnée, le système ne conserve que le meilleur résultat par athlète.
- **Mode Diagnostic ("Fix")** : Un mode administrateur permettant de :
    - Visualiser les IDs internes des données.
    - Détecter les incohérences de genre (ex: un homme dans une catégorie femme).
    - Identifier les erreurs de catégorie basées sur l'âge (athlète trop vieux pour sa catégorie lors de l'événement).

## 🛠 Stack Technique

- **Framework** : Laravel 10+
- **Frontend** : Blade, Tailwind CSS, DaisyUI
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

1. **Extraction** : Récupération des résultats liés à une discipline spécifique.
2. **Filtrage** : Application dynamique des filtres de catégorie et de genre.
3. **Tri Intelligent** : Utilisation de la colonne `sorting` de la table `disciplines` pour effectuer un `orderByRaw` sur la performance castée en `UNSIGNED` (pour gérer les temps et distances stockés en chaînes de caractères).
4. **Déduplication** : Application de `unique('athlete_id')` pour ne montrer que la performance de pointe (top rank) de chaque athlète dans les résultats affichés.

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
