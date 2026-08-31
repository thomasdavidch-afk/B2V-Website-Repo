# B2V Website

Site web du club de beach-volley B2V (Beach Volley Vibes) à Toulouse.

## À propos

B2V est un club de beach-volley situé à Toulouse. Le site présente le club, ses installations, ses activités et les informations utiles pour prendre contact avec l'équipe.

## Contenu du site

- Le beach-volley à Toulouse
- Le club et ses installations
- Les activités proposées par B2V
- Les informations de contact
- Les partenaires et sponsors
- Le règlement intérieur

## Architecture du projet

Le projet est organisé autour d'une partie frontend et d'une partie backend :

```text
B2V-Website-Repo/
├── backend/              # API et application Symfony
├── frontend/             # Interface du site
│   ├── assets/
│   ├── css/
│   ├── fonts/
│   ├── js/
│   ├── pages/
│   └── ressources/
├── docker/               # Configuration des services web
├── compose.yaml          # Orchestration des conteneurs
└── README.md
```

## Frontend

Le frontend contient notamment :

- des pages HTML pour les différentes rubriques du site ;
- des scripts JavaScript pour le routage et le chargement des pages ;
- des feuilles de style CSS/SCSS ;
- les ressources graphiques et les polices utilisées par l'interface.

Les routes et pages visibles dans le projet comprennent notamment l'accueil, le contact, les pages du club et des installations, ainsi que les pages d'erreur et de règlement intérieur.

## Backend

Le backend est une application PHP basée sur Symfony 7.4. Il contient notamment :

- la configuration Symfony ;
- les assets et migrations ;
- les tests ;
- les fichiers de configuration de l'environnement ;
- les dépendances PHP gérées par Composer.

## Lancer le projet

Le projet est prévu pour être exécuté avec Docker Compose. Les services utilisés comprennent notamment :

- le frontend servi par Nginx ;
- le backend PHP/Symfony ;
- une base de données ;
- un service phpMyAdmin pour administrer la base de données.

Depuis la racine du projet, construisez puis démarrez les services avec Docker Compose :

```bash
docker compose up --build -d
```

Une fois les conteneurs démarrés, vérifiez les ports indiqués par Docker Compose pour accéder aux différents services. Selon la configuration locale, le site frontend et l'application Symfony peuvent être exposés sur des ports distincts.

## Routage frontend

Le frontend utilise un routeur JavaScript pour associer l'URL courante à une page et charger le contenu correspondant. Lors d'une modification du routeur, vérifiez que :

1. chaque route est bien déclarée dans la liste des routes ;
2. le chemin de la page correspond au nom du fichier HTML ;
3. le fichier demandé existe dans le dossier des pages ;
4. les fonctions utilisées par le routeur sont bien importées ou définies ;
5. la console du navigateur ne signale aucune erreur JavaScript.

## Dépannage

### Erreur `Route is not defined`

Cette erreur indique qu'une variable, une fonction ou un objet `Route` est utilisé avant d'être défini. Vérifiez les imports, l'ordre de chargement des scripts et les noms employés dans le routeur.

### Erreur `502 Bad Gateway`

Une erreur 502 indique généralement que Nginx ne parvient pas à joindre le service en amont. Vérifiez l'état des conteneurs, les logs Nginx et PHP, ainsi que les ports et noms de services déclarés dans `compose.yaml`.

### La commande Symfony ne trouve pas `bin/console`

Exécutez les commandes Symfony depuis le répertoire du backend, ou vérifiez que le volume du projet est correctement monté dans le conteneur PHP.

## Charte graphique

La maquette utilise notamment les couleurs suivantes :

| Usage | Couleur |
| --- | --- |
| Fond clair | `#fffaf7` |
| Couleur primaire | `#f9ae81` |
| Texte foncé | `#292f51` |
| Couleur complémentaire | `#265ca4` |

La typographie de la maquette s'appuie sur une police sans-serif avec des graisses régulière, medium et bold.

## Git

Pour initialiser le dépôt et utiliser `main` comme branche par défaut :

```bash
git init
git branch -M main
git status
```

Avant le premier commit, contrôlez les fichiers non suivis et vérifiez qu'aucun fichier sensible, notamment les fichiers d'environnement, n'est ajouté au dépôt.

## Licence

Aucune licence open source n'est indiquée dans les éléments disponibles du projet.
