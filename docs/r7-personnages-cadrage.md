# R7.3a — cadrage des personnages importés

4 novembre 2026 : le propriétaire autorise explicitement à anticiper **la préparation du fonctionnement et de l’intégration** de R7.3a pendant les essais humains. R7.2 et R7.3 restent ouverts. Ce document livre ce cadrage ; il ne constitue pas une bibliothèque implémentée et R7.3a reste décoché. Aucun schéma, formulaire ou service applicatif modifié.

## Parcours retenu pour la première implémentation

Une fiche privée contient un nom, une description, un portrait importé et, si nécessaire, des vues supplémentaires. Exemple : « Milo », « Chat roux, museau blanc, veste bleue », portrait de face et vues disponibles. Le texte décrit l’apparence ; les actions appartiennent aux scènes. Aucun portrait généré depuis du texte dans ce lot : cela relève de R7.3b et d’un devis propre.

1. Depuis le choix des références dans Studio ou la configuration d’une série, ouvrir « Mes personnages ». L’écran privé possède une liste paginée et une action « Ajouter un personnage ». Les six destinations de navigation actuelles restent conservées, sans septième entrée permanente.
2. Importer le portrait et remplir la fiche. Prévisualiser, corriger et enregistrer ne lance aucune génération. Un import invalide garde les textes saisis et affiche son erreur près du champ.
3. Dans une série, sélectionner les personnages disponibles pour ses épisodes. L’association est un réservoir de références, pas une instruction d’envoyer tous les personnages dans chaque plan.
4. Dans chaque carte de scène, choisir explicitement les personnages présents. Montrer leurs noms et miniatures dans le résumé, sans obliger à ouvrir les détails des prompts.
5. Au récapitulatif, afficher pour chaque plan les personnages et les images qui seront réellement transmis, ainsi que les éventuelles incompatibilités. Le devis et la confirmation existants restent obligatoires.

Exemple concret : série avec Milo et Léa ; scène 1 = Milo, scène 2 = Léa, scène 3 = Milo et Léa. Les entrées du fournisseur ne doivent pas inclure Léa dans la scène 1. Si le modèle ne permet pas les références requises pour la scène 3, refuser clairement avant toute soumission ; ne pas en retirer une silencieusement.

## Comportements à préserver

| Action | Résultat attendu |
| --- | --- |
| Changer le nom ou le portrait dans la bibliothèque | Nouvelle révision de la fiche ; aucun épisode ou devis ancien réécrit automatiquement |
| Ajouter un personnage à une série | Disponible pour les prochaines préparations ; présence à choisir par scène, sans modifier une publication déjà programmée |
| Actualiser volontairement une référence dans un brouillon | Révision du brouillon et ancien devis invalidés ; nouveau récapitulatif requis |
| Modifier une série après préparation d’un épisode | Les entrées déjà figées dans l’épisode restent conservées ; actualisation éventuelle explicite, jamais pendant un traitement |
| Archiver une fiche | Retirée des nouveaux choix, toujours lisible dans les préparations existantes qui la référencent |
| Image manquante, fiche étrangère ou devenue inaccessible | Erreur récupérable avant le devis ; aucune substitution par une autre image ou un autre propriétaire |
| Modèle incompatible avec plusieurs personnages ou des vues | Aide locale et refus explicite ; sélection et textes gardés pour correction |

La première version privilégie l’archivage. La suppression physique des images utilisées ne doit pas être exposée avant que tous les usages soient protégés ; les procédures de suppression des données du compte restent un sujet distinct à préserver.

## État du code et raccords

- `AiReferenceStorage` stocke déjà les images hors de public, dans le répertoire du propriétaire et de l’environnement ; contrôle du fichier, normalisation JPG et retrait des métadonnées. Réutiliser ces validations : JPG/PNG/WebP, 10 Mo maximum, dimensions et proportions existantes, stockage existant de références. Ne pas inventer de nouveau quota commercial.
- `AiSubjectType` et `AiPlanInputs::subjectDetails()` connaissent déjà référence, nom, rôle, présence et vues. **La présence actuelle décrit le premier cadre** ; elle ne sélectionne pas à elle seule les personnages d’une scène. La nouvelle sélection par scène doit donc être une donnée distincte.
- `AiSeries::recipe` conserve déjà `subjects` et `subjectDetails`. `AiShot::inputs` contient les références de plans ; `AiEpisodeFlow` construit le devis et ses empreintes, puis les entrées de génération.
- `AiCatalog` centralise les capacités des modèles. Les vues groupées sont actuellement traitées de façon spécifique pour Kling 3 image dans `AiPlanInputs` ; ne pas étendre artificiellement leur support à tous les modèles.
- `AiReferenceMaintenance` calcule les images encore utilisées avant nettoyage. Les portraits et vues des fiches, ainsi que les copies figées dans les anciennes préparations, devront être inclus dans cet inventaire avant de livrer la bibliothèque.

Ces éléments sont des constats du code local, pas une vérification nouvelle de la disponibilité des API des fournisseurs.

Conserver également la garde de stockage existante de **100 Mo de références par compte**, portraits et vues compris. La bibliothèque ne crée pas un second stockage qui contournerait cette limite.

## Données proposées

Ajouter une entité simple `AiCharacter` : propriétaire, nom, description, portrait, vues, révision, état archivé et dates. Les noms de fichiers demeurent internes, validés par le stockage ; le navigateur ne peut pas désigner librement le fichier d’un autre propriétaire.

La série enregistre les personnages choisis avec leurs références figées. La préparation d’un épisode conserve à son tour les valeurs réellement utilisées : identifiant de fiche, révision, nom, description et références d’images. Une génération confirmée doit pouvoir retrouver ses images même si la fiche évolue ensuite.

Une scène ajoute une sélection explicite d’identifiants de personnages parmi ceux de sa préparation. Résoudre ces identifiants côté serveur en références compatibles avec le modèle ; ne jamais transmettre les identifiants bruts au fournisseur.

Distinction de compatibilité essentielle :

- **Champ absent dans une ancienne préparation** : conserver le comportement historique et ses références. Ne pas inventer une présence par scène à partir des noms du prompt.
- **Liste vide explicitement enregistrée dans une nouvelle préparation** : aucun personnage sélectionné pour cette scène. Ne pas réinjecter toute la distribution de la série par défaut.

Pour les modèles qui exigent une référence principale, ce choix vide peut être incompatible : expliquer le refus et conserver la scène, plutôt que supprimer sa référence principale historique ou convertir implicitement le modèle. Les images de début/fin et les raccords entre plans restent des usages distincts du portrait de personnage.

Les noms, descriptions, révisions, références et sélections qui affectent les entrées doivent être inclus dans la logique de révision et d’empreinte du devis. Un changement de texte utile au fournisseur doit invalider l’autorisation autant qu’un changement d’image.

## Intégration Symfony proposée

- Entité/migration pour les fiches et service de bibliothèque, avec recherche systématiquement limitée au propriétaire.
- Formulaire de fiche et contrôleur sous `/studio/personnages`, accès `ROLE_VERIFIED`, CSRF sur les écritures et accès aux portraits via route privée.
- Sélecteur commun Studio/Séries, puis choix de présence dans les cartes de scènes. Éviter deux bibliothèques parallèles ou un nouveau parcours de génération.
- Adaptation centrale de `AiPlanInputs`, des recettes et du calcul du devis ; conserver les variantes anciennes des données.
- Extension de l’inventaire de maintenance avant toute nouvelle règle d’effacement. Remplacer un portrait crée une nouvelle image ; les anciens fichiers utilisés restent épinglés.

Le contrôleur ne doit pas construire lui-même les entrées Fal ni contourner les confirmations. Aucun appel externe à l’ouverture d’une fiche, d’une série, d’un épisode ou d’un récapitulatif.

## Découpage d’implémentation

1. **Fiches et protection des fichiers** : persistance, import privé, modification, archivage, pagination, isolation, maintenance et tests de propriété. Migration préparée et validée en base test uniquement ; aucune migration dev/production implicite.
2. **Sélection commune** : bibliothèque accessible depuis Studio/Séries, conservation des anciennes saisies, sélection de personnages pour les nouvelles préparations.
3. **Présence par scène et devis** : résolution des seules références sélectionnées, capacités des modèles, invalidation des devis, préservation des générations et programmations déjà confirmées.
4. **Recette et revue** : simulations multi-épisodes, un puis deux personnages, anciennes données, clavier/mobile/sans JS, contrôles qualité et revue indépendante. R7.3a n’est terminé qu’après ces étapes ; R7.3c et les essais audiovisuels réels restent distincts.

## Critères de recette

- Deux propriétaires ne peuvent ni sélectionner, lire, modifier ni archiver les fiches/images de l’autre ; les refus directs et les CSRF manquants sont testés.
- Une image invalide n’abandonne pas les textes ; aucun fichier orphelin après refus d’enregistrement.
- Les portraits archivés ou remplacés restent disponibles pour les usages figés ; la maintenance ne les efface pas.
- Milo seul, Léa seule et les deux ensemble produisent des entrées simulées différentes et exactes. La liste vide ne réintroduit aucun personnage.
- Les vues absentes/insuffisantes et dépassements des capacités provoquent un refus avant appel, sans modifier silencieusement le modèle.
- Un ancien épisode conserve le comportement antérieur ; modifier la bibliothèque ne réécrit ni génération, ni vidéo approuvée, ni préparation programmée.
- Changer une référence ou une sélection impose une nouvelle confirmation, y compris après retour arrière ou soumission d’un ancien formulaire.
- Sans JS, les fiches et les choix restent utilisables par formulaires et liens natifs. Les états vide, erreur, archivé et incompatible sont lisibles au clavier et à largeur étroite.
- Zéro HTTP réel, coût ou publication pour la recette simulée. Les résultats de simulation ne prouvent ni identité visuelle parfaite ni qualité du fournisseur.

## Décision et reprise

Cadrage préparé, **R7.3a non implémenté et non terminé**. Le prochain travail technique concret est le point 1 ci-dessus, en conservant la priorité aux corrections bloquantes qui remonteraient des essais R7.2. L’anticipation de cette préparation est explicitement demandée par le propriétaire ; elle ne clôture pas R7.2 ni R7.3 et n’autorise pas R8.

Aucun test applicatif rejoué pour ce document seul ; pas de nouveau résultat de suite, build, revue visuelle, migration, génération, email, activation, déploiement ou push revendiqué.

Revue indépendante en lecture seule du cadrage et des composants cités : aucun blocage identifié. Complément sur la limite existante de stockage intégré. Le relecteur n’a exécuté aucun test ni accès DB ; contrôle documentaire de cohérence et `git diff --check` uniquement pour cette phase.
