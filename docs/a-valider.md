# Navigation mobile et étapes dans l’Activité

## Objectif

Conserver six rubriques et rendre leurs accès visibles sur mobile. La proposition initiale d’un onglet « À valider » séparé a été retirée à la demande du propriétaire : les validations restent dans leurs parcours habituels et le suivi global apparaît dans l’Activité existante.

Dans Vidéos → Activité, le bloc « Étapes à terminer » est replié à l’arrivée. Il regroupe les vidéos à regarder, les publications à préparer, les brouillons à confirmer et les blocages. Il s’ouvre après un filtre ou une pagination. L’ancienne adresse `/studio/a-valider` redirige vers ce bloc ; elle ne présente plus un écran indépendant.

## Menu partagé

Le bouton « Menu » apparaît dans l’en-tête mobile commun. Il contient Vidéos, Studio, Séries, Calendrier, Publications, Connexions, Mon compte et Se déconnecter. La barre inférieure à six destinations est conservée. Aucun septième onglet n’est ajouté.

Le menu utilise `details`/`summary` natifs : ouverture et liens fonctionnent sans JavaScript. Le contrôleur Stimulus `options` déjà présent ferme le menu au clic extérieur ou avec Échap, en restaurant le focus sur le bouton. Le panneau est limité à la hauteur de l’écran et défile si nécessaire. Le montage retrouve lui aussi l’en-tête sur mobile.

## Fichiers et responsabilités

- `templates/studio/shell.html.twig` : six entrées partagées, bouton Menu mobile, liens de compte et formulaire de déconnexion avec CSRF.
- `assets/styles/workspace-responsive.css` : affichage mobile du menu, panneau et cibles de 44 px ; navigation ordinateur conservée.
- `src/Controller/DashboardController.php` : charge le suivi uniquement dans l’Activité. Aucune requête de compteur n’est ajoutée aux autres pages.
- `src/Service/ValidationQueue.php` : sélection par propriétaire, catégories, déduplication, compteurs et pagination de vingt éléments.
- `templates/studio/index.html.twig` et `templates/studio/validation.html.twig` : intègrent le bloc dans l’Activité, avec filtres et liens vers les actions existantes.
- `src/Controller/ValidationQueueController.php` : redirection de l’ancienne adresse.
- `tests/Functional/ValidationQueueTest.php` : isolation, états, versions, navigation, redirection et maintien de l’ouverture après pagination.

## Flux et sécurité

Security vérifie le compte connecté et confirmé. L’Activité lit les états existants ; Twig affiche les liens, avec échappement des titres et des noms de séries. Ouvrir le menu ou le suivi ne valide, ne génère et ne publie rien. Les écrans destinataires conservent leurs contrôles de propriétaire, versions, CSRF, coûts fournisseur et confirmations.

Une version approuvée disparaît des publications à préparer dès qu’un brouillon couvre cette même source. Un ancien brouillon d’une autre version ne masque pas la nouvelle. Les créneaux dépassés de plus de 120 secondes et les publications incomplètes/partielles renvoient vers leur suivi ; les destinations déjà réussies ne sont pas republiées. Les arrêts d’épisodes et préparations annulées sont exclus. Les horaires sont lus en UTC puis affichés dans le fuseau enregistré.

## Comment tester

1. Sur mobile, ouvrir Menu depuis chacune des six rubriques et rejoindre une autre rubrique ; vérifier Mon compte et la déconnexion.
2. Utiliser Tab et Échap ; vérifier aussi l’ouverture et les liens sans JavaScript.
3. Depuis Vidéos → Activité, développer Étapes à terminer. Filtrer, revenir à Tout et parcourir les pages : les résultats demandés restent ouverts.
4. Vérifier avec deux propriétaires : chacun voit ses éléments uniquement. Une consultation ne doit créer aucun message d’envoi ni lancer de fournisseur.

## À comprendre en Symfony

Le shell Twig commun sert toutes les pages : modifier sa navigation évite des menus différents selon l’écran. Stimulus améliore un élément HTML natif, sans devenir indispensable pour l’accès aux rubriques. Un état d’affichage replié ne remplace pas les états métiers ; le service continue à lire les sources existantes. La redirection Symfony conserve les anciens liens sans maintenir un deuxième écran.

## Vérifications du 27 octobre 2026

Les migrations de `shortflow_test` sont à jour ; aucune migration de schéma dans ce lot. **73 tests fonctionnels / 1 070 assertions réussis**, dont navigation des six pages, absence d’onglet supplémentaire, redirection de l’ancienne adresse et maintien de l’ouverture après pagination/retour à Tout. Les parcours bibliothèque, compositeur, programmation et publications d’épisodes restent couverts dans ce passage. La suite globale n’a pas été relancée.

**136 tests frontend**, contrôle syntaxique JavaScript et build réussis ; avertissements statiques/Three.js existants. PHPStan sans erreur ; conteneur, schéma Doctrine et 101 templates Twig valides. CS-Fixer a formaté les imports du contrôleur de l’Activité.

Recette navigateur sur vues réelles rendues avec un propriétaire fictif supprimé après rendu : les six destinations ont été rejointes depuis le menu à 390 px ; contrôles à 320 px, tablette et ordinateur. Pas de débordement horizontal sur les vues examinées. Tab rejoint le premier lien ; Échap ferme et rend le focus au bouton. Le menu et ses liens fonctionnent avec zéro script chargé. Le bloc de suivi est replié à l’arrivée et reste ouvert après les filtres. Revue indépendante du menu et du suivi ; le repli après pagination signalé pendant la revue a été corrigé et testé.

Cache local actualisé et serveur de recette temporaire fermé après vérification. Aucun essai social, appel IA payant ou activation de récurrence.
