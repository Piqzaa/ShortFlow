# R7.3a — sélection commune et présence par scène

4 novembre 2026. Le propriétaire autorise la suite des fiches pendant qu’il reporte les premiers essais humains. **R7.2 et R7.3 restent ouverts, aucun retour humain n’est déduit des tests.** Ce sous-lot raccorde la bibliothèque au Studio, aux séries et aux scènes ; R7.3a reste ouvert pour la recette finale de l’ensemble, notamment les reprises multi-épisodes avec transport fournisseur simulé. Aucun essai audiovisuel réel n’est revendiqué.

## Objectif et utilisation

Dans le Studio ou la configuration d’une série, « Mes personnages » propose les fiches du compte. Choisir jusqu’à sept fiches pour la réserve commune, puis choisir les personnages présents dans chaque scène. Le plafond de sept organise cette réserve ; il n’augmente pas les capacités d’un modèle. La sélection ne déclenche aucune génération.

La liste distingue une référence déjà conservée de la version actuelle de la fiche. Une seule version du même personnage est acceptée. Les noms identiques restent distingués dans les choix. Le lien de gestion ouvre la bibliothèque dans un autre onglet pour préserver les textes de la préparation.

Exemple : Milo et Lea sont disponibles ; scène 1 = Milo, scène 2 = Lea, scène 3 = les deux. Le récapitulatif montre les noms, les portraits et, dans le détail, les images et le prompt exact. Les références de Lea ne sont pas ajoutées à la scène 1.

Le choix natif multiple fonctionne sans scripts ; sur ordinateur, Ctrl/Cmd permet de sélectionner plusieurs lignes. Les actions de répartition avec et sans scripts protègent les scènes contenant une sélection. Réunir plusieurs scènes avec des personnages exige une réaffectation manuelle de leur présence, au lieu de choisir arbitrairement celle du premier plan. Une proposition de scénario conservant le nombre de plans conserve les présences par position ; si ce nombre change, leurs choix doivent être réaffectés avant application. Le récapitulatif reste nécessaire.

## Compatibilité et refus

- Vidu référence : le premier portrait sélectionné devient la référence principale et les suivants la liste supplémentaire. Une scène vide est refusée, ce mode exige une référence ; elle ne récupère jamais toute la réserve. Les vues groupées d’une fiche sont refusées pour ce modèle, sans suppression silencieuse.
- Kling 3 image : le cadre de début reste distinct ; seules les identités choisies et leurs vues groupées sont résolues. Les limites existantes et la présence de vues sont vérifiées. Une liste de personnages vide peut conserver son cadre de début sans ajouter d’identité.
- Un modèle texte ou incompatible ne reçoit pas implicitement les portraits : une présence incompatible est refusée avant confirmation.
- Les anciennes références communes et la bibliothèque ne sont pas fusionnées silencieusement. Le devis demande de retirer les références communes incompatibles ou de repartir d’une préparation distincte. Aucun ancien épisode n’est converti automatiquement.
- Une image manquante, une fiche étrangère, une version actuelle devenue obsolète ou un personnage de scène absent de la réserve produit une erreur récupérable. Aucune substitution automatique.

Une fiche archivée reste utilisable uniquement depuis une copie déjà enregistrée dans une préparation ou une série. Elle disparaît des nouveaux choix. Modifier sa fiche ne modifie pas les images ni le devis d’une préparation déjà figée. La maintenance existante inventorie les recettes et conserve leurs fichiers.

## Composants Symfony et flux d’exécution

`AiCharacterSelection` est le service commun : il présente les choix privés, résout les identifiants/révisions depuis la bibliothèque du propriétaire et réutilise uniquement les copies précédentes issues de la base ou de la session serveur. Il prend le verrou du propriétaire avant résolution et vérifie les fichiers privés. Le navigateur ne transmet pas de snapshot complet faisant autorité.

`AiCharacterPoolType` réutilise le champ Choice Symfony. `AiIdeaType` et `AiSeriesType` l’intègrent ; `AiShotType` ajoute les présences par scène. Les marqueurs distinguent un ancien formulaire qui ignore ces champs d’un formulaire moderne qui envoie volontairement une liste vide. L’événement SUBMIT supprime la clé normalisée par Symfony lorsque le marqueur est absent ; les sauvegardes conservent alors la valeur serveur précédente.

`AiStudioController` construit les choix, traite leurs erreurs avant l’action et conserve les snapshots dans la session d’idée. L’autosave conserve aussi les présences si une ancienne page omet les nouveaux champs. Les contrôleurs Série/Épisode réutilisent le même service pour leurs formulaires ; autorisations et CSRF existants restent actifs. Le parcours individuel historique refuse de contourner les scènes lorsqu’une réserve est choisie.

`AiSeriesStudio` enregistre une copie des fiches dans `recipe.characterPool`. Un épisode encore non préparé retrouve la recette de sa série ; un épisode déjà préparé conserve sa propre copie. `AiEpisodeFlow` sauvegarde ces copies et les présences normalisées dans `AiShot.inputs.characterIds`, en incrémentant la révision du brouillon.

`AiPlanInputs::resolve()` construit uniquement les références sélectionnées pour le plan. Il réutilise les validations et capacités déjà centralisées. `AiShotPrompts` inclut les noms et descriptions réellement sélectionnés dans le prompt exact ; les notes d’apparence ne sont pas tronquées silencieusement pour passer la limite du modèle. Un texte trop long est refusé. Le devis inclut recette, références résolues et prompt ; changer de présence invalide son empreinte et l’ancienne confirmation.

Les scripts `preparation_controller` et `story_writer_controller` conservent les présences lors des reconstructions de cartes, bloquent la suppression implicite et incluent ces choix dans l’identification d’une proposition devenue obsolète. Les templates communs affichent les sélecteurs et les portraits du récapitulatif ; le CSS ajouté est limité à leur largeur et aux miniatures.

**À comprendre en Symfony :** le formulaire contrôle la forme de la saisie, le service contrôle sa signification et la propriété, puis Doctrine enregistre la recette. Les marqueurs ne sont pas une autorisation : ils servent à la compatibilité des formulaires. L’autorisation reste le propriétaire, le CSRF et la révision. Le JSON est ajouté aux recettes existantes, sans nouvelle colonne ni migration.

## Vérification et limites

Les nouveaux scénarios couvrent trois scènes avec références exactes, copie inchangée après modification de fiche, refus d’une ancienne empreinte, listes vides, anciens formulaires, autosave, propriétaire, versions obsolètes, modèles incompatibles, vues et fichier absent. Les propriétaires, images et textes sont synthétiques. La classe `AiCharactersTest` travaille exclusivement dans `shortflow_test`, compare les 25 tables publiques après rollback et remplace cinq clients HTTP par des refus.

Les régressions existantes ont leurs propres conventions de préparation et nettoyage de la base test ; les garanties transactionnelles de `AiCharactersTest` ne leur sont pas attribuées. Les relectures navigateur rejouent des réponses BrowserKit : elles ne constituent pas un essai humain ni une soumission réelle au fournisseur. L’autosave du banc interactif est simulé dans le navigateur ; son comportement applicatif est vérifié séparément en PHP.

Les résultats finaux, empreintes et limites sont consignés dans [le manifeste](fixtures/personnages-selection-20261104.json). Aucun passage global PHP, essai iPhone, lecteur d’écran ou qualité audiovisuelle fournisseur n’est déduit de ces contrôles.

Pour rejouer : `docker compose exec -T php php vendor/bin/phpunit tests/Functional/AiCharactersTest.php`. Pour essayer manuellement sans coût : créer deux fiches, choisir leurs présences dans trois scènes, ouvrir le récapitulatif et comparer les références ; ne pas confirmer de génération réelle.

Prochain point technique : recette finale de R7.3a sur les reprises et plusieurs épisodes avec fournisseurs simulés, puis bilan du périmètre avant de cocher la case. Les premiers essais humains R7.2 restent reportés. Aucun push, appel fournisseur réel, coût, email réel, activation, publication, migration ou déploiement dans ce sous-lot ; aucun worker redémarré.

Résultats finaux : **76 PHP de régression / 1 136 assertions**, puis **18 ciblés / 167 assertions après les dernières finitions autosave/libellés**. **196 frontend/check/build**, 123 Twig, 19 YAML, conteneur/PHPStan/style et schémas dev/test verts. Le build conserve l’avertissement Three.js existant. **36 vues** sans débordement, image réellement chargée cassée, erreur JS ou réponse HTTP en échec ; les aperçus sans source sont distingués des images chargées. Clavier sans JS : lien d’évitement, réserve atteinte par Tab et sélection multiple native dans la réserve et une scène. Répartition JS : présences conservées ; réduction supprimant une scène renseignée refusée. Neuf rapports transactionnels / 25 compteurs comparés après rollback ; 12 empreintes HTML vérifiées, zéro HTTP fournisseur dans cette classe. Revue indépendante en lecture seule : les deux risques de perte de saisie ont été corrigés et couverts, aucun blocage restant identifié. Le relecteur n’a pas exécuté les tests DB. Cache web dev actualisé, aucun worker redémarré.
