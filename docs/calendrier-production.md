# Calendrier de production et de publication

## Objectif

Le calendrier existant affiche désormais les étapes de production en plus des publications. Aucun nouvel onglet n’est ajouté. Les cartes d’épisodes indiquent un **créneau visé** ; une publication `Programmée` conserve son sens de date d’envoi confirmée. Consulter le calendrier ne crée ni occurrence, ni brouillon, ni génération, ni message d’envoi.

## Fichiers et composants

- `src/Service/ProductionCalendar.php` sélectionne les épisodes et créneaux du propriétaire, calcule leur étape et élimine les doublons avec la version approuvée déjà préparée pour publication. Il fournit les entrées datées et une liste paginée de douze épisodes sans date.
- `src/Controller/PublicationDraftController.php` conserve les règles existantes de calendrier et ajoute ces entrées aux jours calculés dans le fuseau de consultation. Les nouveaux filtres d’état complètent ceux de publication.
- `templates/draft/calendar.html.twig` affiche la grille, l’agenda mobile et la section Sans date ; le choix de vidéos depuis un jour reste inchangé.
- `templates/draft/_production_entry.html.twig` partage le rendu des cartes, leurs noms accessibles et leurs liens vers épisode, série ou préparation existante.
- `assets/styles/publication.css` distingue les créneaux de production par des bordures pointillées et garde leurs états lisibles sur ordinateur. L’agenda mobile présente les détails sous la grille.
- `tests/Functional/ProductionCalendarTest.php` vérifie les étapes, les créneaux, les propriétaires, les versions, les fuseaux, les filtres et la pagination.

## Flux d’exécution

1. Security contrôle l’utilisateur confirmé. Le contrôleur vérifie mois, fuseau, réseau et état.
2. Le calendrier lit les brouillons de publication comme auparavant, avec leurs états calculés.
3. Le service ajoute les occurrences de série déjà enregistrées et le prochain créneau d’une récurrence active, dans la limite autorisée. Ce dernier n’est qu’un aperçu : aucune occurrence n’est créée ou étendue.
4. L’état actuel de l’épisode précise si la préparation est prévue, en cours, en pause, si la vidéo attend validation ou si sa publication reste à préparer.
5. Une préparation non annulée, datée ou déjà envoyée, correspondant à la **vidéo approuvée actuelle** remplace la carte de production. Un ancien brouillon lié à une autre version ne masque pas la nouvelle.
6. Les projets sans créneau apparaissent dans Sans date, jamais dans un jour inventé. Les liens ouvrent les écrans existants avec leurs validations habituelles.

## Règles importantes

- Une date d’épisode est un objectif, pas une publication armée. Une occurrence passée n’est pas automatiquement une publication manquée.
- Une récurrence suspendue ne propose pas de prochain créneau ; ses occurrences déjà produites restent consultables. Les épisodes arrêtés volontairement sont exclus.
- Le prochain créneau n’est affiché qu’une fois et seulement si l’autorisation n’est pas épuisée. Les créneaux suivants ne sont pas extrapolés.
- Une préparation sans date peut être ouverte depuis l’épisode ; sa carte de production reste visible jusqu’au choix d’une date. Un brouillon daté non confirmé reste un brouillon dans le calendrier de publication.
- Les épisodes ponctuels et de série sans occurrence sont listés sans date. Les générations historiques déjà disponibles, qui ne disposent pas de l’étape de validation du parcours commun, restent dans la bibliothèque et l’historique existants.
- Les réseaux d’une occurrence restent ceux de son instantané. Les filtres sont appliqués avant la pagination ; un projet sans destination définie n’est pas attribué arbitrairement à un réseau.
- Le stockage est lu en UTC ; la grille et ses heures utilisent le fuseau de consultation. Changer ce fuseau ne déplace aucune programmation.

## Vérification manuelle

Dans une base de test, préparer un créneau de série et consulter son mois : l’épisode doit être présenté comme visé. Passer son état de production à terminé, puis valider sa vidéo : le libellé et l’action doivent évoluer. Préparer une publication correspondant à cette version, lui choisir une date : la préparation remplace la carte de production. Une nouvelle version approuvée doit réapparaître comme à préparer si l’ancien brouillon couvre une autre source.

Contrôler aussi un créneau proche de minuit dans deux fuseaux, les filtres réseau/état, un projet sans date et une récurrence suspendue. Sur mobile, vérifier l’agenda détaillé, le menu commun et l’ouverture du sélecteur de vidéos depuis un jour.

## À comprendre en Symfony

Ce lot est une lecture des états existants : il ne nécessite aucune nouvelle entité ni migration. Le service porte les requêtes ; le contrôleur coordonne les filtres et les jours ; Twig présente les informations avec échappement des titres et des noms de série. Le filtrage propriétaire est répété dans les branches SQL, y compris sur les brouillons et les sources. La date affichée ne constitue jamais un consentement : les contrôleurs de génération et de publication gardent leurs confirmations, coûts et autorisations.

Les tests utilisent exclusivement `shortflow_test` après migrations et des clients HTTP simulés. Aucun essai IA payant, activation réelle de récurrence ou envoi social n’est requis. Les résultats de recette sont consignés en fin de lot.

## Résultats du 27 octobre 2026

- **75 tests fonctionnels / 1 022 assertions réussis**, sur le nouveau calendrier, programmation, épisodes/publications, récurrence supervisée, vue d’ensemble, navigation, compositeur et sélection par vidéo. Migrations de test appliquées avant PHPUnit ; schéma et conteneur valides. La suite globale n’a pas été relancée dans ce lot.
- **136 tests frontend réussis**, syntaxe JavaScript et build valides. Avertissements existants de polices statiques et de taille Three.js. PHPStan sans erreur ; 102 templates Twig valides. CS-Fixer a ordonné les imports du contrôleur.
- Deux revues indépendantes en lecture seule ont vérifié l’isolation, les versions, les dates, limites, filtres et la présentation. Les états initialement masqués par les anciens styles ont été rendus visibles ; les cartes ont une disposition adaptée plutôt que la grille de publication à deux colonnes. L’origine est incluse dans les noms accessibles et la légende distingue les intentions des dates confirmées.
- Recette sur vues réelles synthétiques : ordinateur, 390 × 844 et 320 × 740, sans débordement horizontal ni erreur JavaScript. Quatre étapes de production, une publication programmée, un brouillon daté et un projet sans date ont été présentés ensemble. Filtre Vidéo à valider, agenda mobile, menu, navigation clavier, ouverture/fermeture du sélecteur depuis un jour vérifiés.
- Fuseaux : le même créneau est affiché le 31 octobre à 00:30 en Europe/Paris et le 30 octobre à 19:30 à New York, sans modification des données. Le propriétaire fictif est supprimé après rendu et le serveur de recette est fermé après contrôle.
- Cache local actualisé. Aucun message d’envoi, activation réelle de série ou appel IA payant créé par la consultation.
