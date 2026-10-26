# Vue « À valider »

## Objectif et périmètre

La page `/studio/a-valider` rassemble les prochaines interventions humaines : regarder une vidéo terminée, préparer la publication d’une version approuvée, compléter/confirmer un brouillon et vérifier une préparation ou un envoi bloqué. Elle est accessible dans la navigation ordinateur et par un lien en haut de page sur mobile. Les six onglets inférieurs restent conservés.

La vue ne valide, ne génère et ne publie rien. Ses liens ouvrent les écrans existants avec leurs contrôles de propriété, récapitulatifs, CSRF et consentements. Aucun appel de fournisseur n’est effectué pour afficher la liste. Le calendrier de production reste un lot suivant.

## Fichiers et rôles

- `src/Service/ValidationQueue.php` : sélection SQL limitée au propriétaire, catégories, compteurs, tri et pagination de vingt éléments. Les blocages passent en premier, puis les vidéos à regarder et les préparations à terminer. Les compteurs ne sont calculés que sur cette page, pas à chaque navigation du studio.
- `src/Controller/ValidationQueueController.php` : route GET protégée par le contrôle `ROLE_VERIFIED` existant, lecture des filtres/page et réponse privée sans cache.
- `templates/studio/validation.html.twig` : filtres, liste des interventions, liens vers leurs écrans, créneaux dans leur fuseau enregistré et états vides. Twig échappe titres et noms de séries.
- `templates/studio/shell.html.twig` : accès et état actif de la navigation. La navigation mobile inférieure conserve ses six entrées.
- `assets/styles/publication.css` : cartes et filtres adaptatifs, cibles tactiles et accès mobile.
- `tests/Functional/ValidationQueueTest.php` : classification, isolation, consultation sans effet de bord, sources révisées, déduplication, dates, pagination et accès.

## Flux et règles de sélection

Le navigateur demande la page ; Security vérifie la connexion et la confirmation du compte. Le contrôleur appelle le service, qui interroge les états enregistrés dans PostgreSQL. Twig affiche uniquement des liens ; les validations ont ensuite lieu dans les parcours habituels.

Les créations ponctuelles et les épisodes de série sont inclus. Une vidéo `ready` non approuvée demande une validation ; une version approuvée sans préparation correspondant à cette source demande une préparation. L’existence d’un ancien brouillon lié à une autre version ne masque pas la nouvelle. Les imports n’imposent pas une nouvelle validation vidéo : leurs brouillons existants apparaissent directement dans les publications à confirmer.

Les brouillons enregistrés restent à compléter et confirmer, même s’ils possèdent une date proposée. Les préparations bloquées, les créneaux dépassés de plus de 120 secondes et les envois incomplets ou partiellement réussis demandent une vérification. Les jobs associés à une préparation ne créent pas une carte supplémentaire par destination. Les anciens envois sans préparation durable restent visibles via leur suivi réseau. Les épisodes en pause sont proposés à la reprise ; les épisodes arrêtés et les préparations au statut annulé sont exclus de la file.

Le filtre limite la liste mais garde les compteurs des autres catégories. La pagination est stable à état identique, avec l’identifiant comme départage. Les horaires SQL sont lus en UTC puis affichés dans le fuseau conservé par la préparation.

## Comment vérifier

1. Ouvrir « À valider » avec un compte vérifié : seuls ses éléments sont visibles.
2. Terminer une vidéo puis ouvrir la page : « Regarder et valider » ouvre l’épisode. Après validation de sa version, l’étape devient « Préparer la publication ».
3. Enregistrer sa préparation : la carte de version validée laisse place au brouillon. Une modification ultérieure de la version approuvée doit réintroduire une préparation pour cette nouvelle source.
4. Vérifier les textes et confirmer via le parcours habituel. Les éléments effectivement programmés/en cours/réussis quittent la file ; un blocage ou un créneau manqué y apparaît.
5. Tester les filtres, un compte sans éléments et le mobile/clavier. Aucun essai social ou IA payant n’est requis ; les tests utilisent uniquement `shortflow_test` et des clients simulés.

## À comprendre en Symfony

Une page de suivi peut réutiliser les états métiers existants sans nouvelle table ni migration. Le contrôleur coordonne la requête, le service sélectionne les données et Twig présente les actions. Le filtre du propriétaire doit exister dans toutes les branches SQL, y compris les unions entre réseaux. Un compteur est un calcul d’affichage ; il ne constitue pas une autorisation. Les écrans destinataires revérifient toujours l’identité, la version et les consentements avant toute mutation.

## Vérifications du 27 octobre 2026

- Migrations de `shortflow_test` à jour avant PHPUnit ; aucun changement de schéma dans ce lot. Schéma Doctrine, conteneur Symfony et **101 templates Twig** valides.
- **71 tests fonctionnels réussis**, couvrant la nouvelle file, la vue d’ensemble, la bibliothèque, le compositeur, la préparation par vidéo, la programmation et les publications d’épisodes. Le premier contrôle élargi a été interrompu sans bilan ; le rejeu avec suivi test par test a terminé avec un code de sortie zéro. La suite globale n’a pas été relancée dans ce lot.
- Le premier passage des quatre nouveaux tests a trouvé une attente d’ordre trop stricte entre une date métier simulée et l’heure réelle d’un job. La vérification de déduplication compare désormais les ressources présentes, tandis que la stabilité de pagination est vérifiée séparément. Les horaires, la borne de 120 secondes, l’absence d’écriture/envoi et les titres échappés sont contrôlés.
- **136 tests frontend**, contrôle syntaxique JavaScript et build réussis. Avertissements de build existants sur les polices statiques et la taille du module Three.js.
- PHPStan sans erreur ; CS-Fixer sans correction sur les 301 fichiers examinés.
- Revues indépendantes en lecture seule des règles de sélection et de la navigation. Recette sur données synthétiques : quatre catégories, filtrage, clavier, états actifs et six onglets mobiles ; pas de débordement horizontal à 390 × 844 et 320 × 740. Cibles tactiles de 44 px minimum ; console sans erreur JavaScript.
- Cache local actualisé ; propriétaire de recette supprimé après rendu. Aucune génération payante, publication réelle ou activation de récurrence.
