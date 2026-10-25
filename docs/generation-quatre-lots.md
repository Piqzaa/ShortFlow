# Génération IA : réalisation des quatre lots

Autorisation du propriétaire le 24 octobre 2026, après l’audit `audit-generation-studio-series-2026-10-24.md`. Réalisation successive, tests simulés, commits locaux sans push. Aucune génération payante ni publication réelle pendant ce chantier.

## Suivi

- [x] Lot 1 : mêmes rôles Studio/Séries, sélection explicite, retrait programmé de Pika, libellés honnêtes, format final explicite, tests existants réparés sans purge de file.
- [x] Lot 2 : références de sujets et cadres propres aux plans, conservation des anciennes préparations, récapitulatif des entrées et empreinte d’exécution.
- [x] Lot 3 : plan unique explicite, prototype multi-plan natif simulé et extension vidéo native avec devis du contexte.
- [x] Lot 4 : prompts adaptés, aperçus/reprise, audio et entretien sûr des références.

Les détails des fichiers, du flux Symfony, des vérifications et des limites seront ajoutés à chaque lot terminé.

## Lot 1 : harmoniser la préparation

`AiCatalog::referenceMode` distingue texte, premier cadre et personnage. `AiIdeaType`, le contrôleur Studio et le catalogue Twig reprennent ces rôles. Le contrôleur Stimulus ne remplace plus un endpoint lors d’un changement de source ; l’import d’image ne choisit plus Kling côté serveur. Les références restent contrôlées par propriétaire, y compris à l’enregistrement d’un brouillon personnage.

Pika conserve ses identifiants et ses résultats, mais sort des raccourcis recommandés. Ses cartes affichent sa date de retrait ; une validation serveur interdit les nouveaux envois à compter du 29 octobre 2026. LTX 2.3 Fast 720p texte expose les mêmes durées natives à 25 FPS que la variante image.

Un montage exige maintenant 9:16, 16:9 ou 1:1 avant devis. Les anciennes générations simples au format de l’image restent lisibles. La préparation demande un choix explicite plutôt que de transformer un 4:5 en 9:16 sans information. Le raccord d’image est nommé comme tel, sans promesse de continuité native.

Vérifications : 112 tests frontend réussis, build Vite réussi, PHPStan et 88 templates Twig valides. Quatre tests PHP ciblés, 113 assertions réussies : rôles/retrait, propriété des images, recommandations et scénario privé. Les deux échecs de l’audit ont été corrigés par mise à jour du sélecteur pertinent et vérification du nombre de nouveaux messages, sans supprimer les messages existants.

À tester sans coût : changer texte/premier cadre/personnage, constater l’absence de modèle sélectionné par substitution, choisir explicitement le bon modèle et le format final, ouvrir le récapitulatif. Aucune génération ne part à ces étapes. En Symfony, le rôle du modèle est une règle serveur ; Stimulus représente cette règle et ne confirme jamais un coût.

## Lot 2 : personnages communs, cadres par plan

La migration `Version20261024180500` ajoute les entrées JSON des plans et les paramètres figés des générations. Elle est appliquée en dev et sur `shortflow_test`. `AiPlanInputs` valide les fichiers privés et résout les références effectives. Pour les nouvelles préparations, le raccourci début concerne seulement le premier plan et le raccourci fin seulement le dernier. Un autre plan image sans cadre doit être complété avant devis. Les anciennes recettes conservent leur interprétation jusqu’à modification explicite.

`AiShotType`, les collections Twig et les contrôleurs Stimulus permettent l’import, le retrait et l’aperçu cadré de chaque plan. Les personnages supplémentaires restent communs : six en plus du principal pour Vidu Q1, deux éléments pour Kling 3 image. Les contraintes serveur priment sur les filtres visuels. `FalVideoApi` transmet les images entières de personnages à Vidu et les éléments séparés aux cadres de Kling. Le rédacteur reste textuel : importer une photo ne lui permet pas de la voir.

Le devis présente les entrées de chaque appel ; son empreinte comprend les références résolues et le contrat du catalogue. La confirmation d’une génération fige aussi ses personnages et son contrat. Changer une référence ou un contrat invalide donc le consentement au lieu de modifier silencieusement un envoi. Les récurrences image à plusieurs scènes indépendantes demandent une préparation adaptée plutôt que de dupliquer leurs cadres.

Vérifications : trois tests unitaires ciblés, 15 assertions ; trois tests fonctionnels ciblés, 34 assertions, tous réussis. Ils couvrent l’ancien comportement, les cadres distincts, le blocage d’un plan incomplet, les payloads Kling/Vidu, le consentement et le récapitulatif HTTP sans soumission. PHPStan réussi ; 92 templates Twig valides ; 112 tests frontend, contrôle syntaxique et build réussis. Le build conserve l’avertissement existant sur la taille du chunk Three.js.

Pour tester : préparer deux plans Kling image avec des cadres différents, ajouter un personnage, ouvrir le devis puis revenir modifier un cadre. Avec Vidu référence, préparer deux scènes avec le même personnage sans cadres début/fin. En Symfony, les champs de collection sont transformés par le formulaire ; le service vérifie ensuite la propriété et la compatibilité avant toute orchestration Messenger. Retirer une image du formulaire retire son affectation, pas encore son fichier historique.

## Lot 3 : plan unique et continuité native

Le bouton « Préparer un plan unique » conserve le modèle choisi et exige une durée native en un seul appel. Le formulaire, le devis et le rédacteur reconnaissent ce mode. Le prompt demande une prise continue ; cela ne garantit pas l’absence de coupe produite par le modèle. Les préparations à plusieurs cadres ne sont pas fusionnées silencieusement.

Depuis la fiche d’une vidéo, « Prolonger l’action avec LTX » ouvre une préparation privée. `AiExtensionController` valide le formulaire et le CSRF ; `NativeExtensions` vérifie la propriété, le fichier réel avec `VideoProbe`, le format explicite et le contexte. La source et son empreinte sont figées dans `AiGeneration.inputSettings`, sans migration supplémentaire. Un MP4 H.264 de 11 Mo maximum est transmis en vidéo, jamais en JPG. Le choix est séparé du catalogue texte/image pour éviter les substitutions.

`NativeVideoPreview` centralise le contrat et le calcul. L’extension utilise uniquement LTX 2.3 Pro, `/v2/extend`, avec contexte explicite, limite de 505 images et coût de la durée ajoutée **plus** le contexte. `LtxVideoApi` utilise le suivi `/v2/extend/{id}`. `AiGenerations` garde la transition durable avant POST et la récupération du même résultat ; `VideoLibrary` vérifie la durée complète attendue et protège la source tant que sa préparation reste active. L’abandon est possible uniquement avant envoi. Une demande incertaine n’est jamais renvoyée automatiquement.

Le multi-plan natif Kling est un **prototype de contrat simulé**, sans bouton de génération ni activation : un appel `multi_prompt` avec durées détaillées, sans champ `prompt` concurrent, et des coupes possibles. L’extension supervisée est intégrée, mais aucun appel réel n’a été fait et sa qualité visuelle reste à évaluer lors d’un essai séparément confirmé.

Vérifications finales : cinq tests unitaires, 15 assertions ; trois tests fonctionnels, 36 assertions, réussis. Ils vérifient plan unique, source privée, CSRF, abandon, vidéo native, facturation du contexte, résultat complet simulé et un seul POST malgré les reprises. PHPStan, 93 templates Twig, 112 tests frontend, syntaxe et build réussis. Les premières tentatives de test ont corrigé un sélecteur incluant le prototype HTML, une réutilisation d’entité détachée et une comparaison entier/flottant du contexte. Le refus temporaire du contrôle d’approbation pendant l’interruption venait d’une limite d’usage ; aucune commande n’avait été exécutée ni contournée.

À tester sans coût : ouvrir « Prolonger » sur une vidéo privée et vérifier le récapitulatif, puis abandonner avant envoi. Le test automatisé utilise une vidéo synthétique de 10 s et simule une extension à 15 s, 5 s nouvelles et 3 s de contexte, pour 0,80 $ US estimé. En Symfony, la préparation persiste l’intention et son empreinte ; la confirmation seule planifie Messenger ; l’adaptateur traduit ensuite cette intention en contrat fournisseur.

Contrats revérifiés en lecture seule le 24/10/2026 : [LTX Extend V2](https://docs.ltx.io/api-documentation/api-reference/async-video-generation/submit-extend), [tarifs LTX](https://docs.ltx.io/pricing/), [formats d’entrée LTX](https://docs.ltx.io/input-formats), [Kling 3 multi-prompt](https://fal.ai/models/fal-ai/kling-video/v3/pro/text-to-video/api). Le plafond de 11 Mo local laisse la vidéo encodée sous la limite Data URI fournisseur ; les autres méthodes d’upload ne sont pas ajoutées.

## Lot 4 : qualité, correction et exploitation

Objectif : expliquer et figer ce qui sera envoyé, permettre de corriger un plan sans perdre son résultat, et éviter les références privées abandonnées sans supprimer une préparation utile. Aucun appel vidéo réel ni nettoyage des images du propriétaire n’a été exécuté.

`AiShotPrompts` construit les nouvelles consignes selon le mode : action simple terminée avant la coupe, prise unique sans coupe demandée, ou continuation depuis le premier cadre. Les sujets guident l’identité sans imposer la pose. Les éléments Kling sont nommés séparément. Le contexte ajouté est borné et les limites en caractères ou octets sont respectées sans tronquer une scène utilisateur ; une scène trop longue doit être corrigée. Les nouvelles préparations utilisent `inputVersion: 3`, incluent le prompt effectif dans le devis et la version des consignes dans le consentement. Les anciennes préparations gardent leur construction antérieure jusqu’à modification explicite.

`AiStoryProposalController`, `SeriesRecurrences` et `LocalStoryWriter` transmettent les capacités du modèle au rédacteur local. Ils précisent que les photos ne sont pas vues par ce moteur textuel. Le scénario reste un aperçu à appliquer ; sa durée et sa taille sont contrôlées. Le navigateur invalide une proposition ancienne après changement des personnages ou des cadres.

`AiShot.inputs.repairPrompt` conserve une correction en attente. `AiEpisodeFlow::stageRepair` vérifie la révision et la compatibilité, sans retirer la vidéo ou son approbation. La confirmation du **nouveau** devis applique ensuite le texte, conserve l’ancienne génération et invalide l’ancien assemblage. Le contrôleur protège la route par propriété et CSRF ; `_repair_prompt.html.twig` propose l’édition. Un plan raccordé antérieur au dernier est bloqué si le modèle ne permet pas d’imposer sa fin. Avec une fin compatible, la dernière image du clip remplacé est réutilisée et annoncée dans le devis. Il faut encore vérifier visuellement le raccord : aucun plan suivant n’est relancé implicitement.

`reference_picker_controller.js` empêche qu’une réponse tardive restaure une image retirée et reconstruit l’aperçu d’une référence réaffectée. `subject_upload_controller.js` montre les nouvelles photos entières et permet de les retirer avant l’enregistrement de la série ; les URL temporaires sont libérées. Sans l’API de sélection partielle du navigateur, le bouton efface les nouvelles sélections. Les cadres et leur recadrage restent propres à chaque plan. Les vues de préparation et de devis expliquent que l’audio est généré par clip ; le couper au montage ne réduit pas le coût fournisseur et un raccord JPEG ne transporte aucun son.

`InspectAiReferencesCommand`, `AiReferenceMaintenance` et `AiReferenceStorage::sweep` ajoutent un entretien manuel : `app:ai:references IDENTIFIANT` produit seulement un rapport. L’option explicite `--apply` retire uniquement des JPG normalisés de plus de 30 jours sans marque dans les générations, séries, épisodes, plans, récurrences, propositions ou sessions locales. Le service verrouille le propriétaire comme les imports et refuse une lecture de session impossible. Les liens symboliques et autres noms sont ignorés. Aucun entretien automatique n’est activé. Une référence historique est volontairement conservée ; retirer son affectation dans un formulaire ne garantit pas sa suppression physique.

Flux Symfony à comprendre : formulaire → contrôleur et CSRF → service métier sous transaction/verrou → devis avec empreinte → consentement → message Messenger → adaptateur fournisseur → import vérifié → assemblage et validation. Stimulus fournit l’aperçu et les états d’attente ; il ne remplace jamais la validation serveur ni l’accord de dépense.

Recette sans coût : préparer deux cadres distincts avec un personnage commun, changer recadrage/bandes, retirer puis réaffecter une image, ouvrir le prompt exact du devis. Sur un résultat terminé simulé, corriger un plan, vérifier la conservation du résultat avant confirmation et le refus de l’ancien devis. Pour l’entretien, utiliser uniquement les fichiers synthétiques du test automatisé ; aucune suppression réelle n’est nécessaire à la recette.

Validation du 25/10/2026 : suite élargie **205 tests PHP, 3 104 assertions réussis** en 10 min 24 s, exclusivement sur `shortflow_test`. Périmètre : Studio, Séries, épisodes multi-plans, récurrences, rédaction asynchrone, entrées images, catalogue, continuité native et rédacteur local. Après ajout du cas de réparation raccordée sans cadre final, trois tests ciblés supplémentaires réussissent avec 56 assertions ; ils revérifient aussi la correction du prompt et les cadres par plan. Les erreurs HTTP 403/404 présentes dans la sortie sont les refus intentionnels des tests de sécurité.

**117 tests frontend réussis**, contrôle syntaxique et build Vite réussis. PHPStan niveau 5 sans erreur ; CS-Fixer ne propose aucune correction ; 94 templates Twig et 19 fichiers YAML valides ; conteneur Symfony valide ; schémas dev/test synchronisés. Le build signale toujours le chunk Three.js supérieur à 500 ko et les URL publiques statiques résolues à l’exécution. Aucun essai fournisseur réel n’a été ajouté.

Recette navigateur sur pages fictives rendues avec le vrai formulaire et les vrais assets : référence supplémentaire sélectionnée puis retirée avant sauvegarde, détail du prompt exact, deux appels distincts, ouverture des cadres au clavier, passage du recadrage à l’image entière avec bandes. Affichage bureau et mobile 390 × 844 vérifié, sans débordement horizontal sur les vues contrôlées ; aucune erreur console sur l’éditeur contrôlé. Les routes de mutation de ce serveur de démonstration sont toutes bloquées ; sa route de récupération du rédacteur n’est pas simulée, ce qui affiche son message de session expirée. Les parcours serveur correspondants sont couverts par les tests fonctionnels. Captures locales ignorées : `artifacts/generation-lot4-desktop.png` et `artifacts/generation-lot4-mobile.png`. Le serveur temporaire est retiré après la recette et le viewport est réinitialisé.

La fidélité du personnage, la fin d’une action et l’absence de coupe restent des propriétés du résultat fournisseur à évaluer lors d’un essai réel séparément confirmé. Un contrat simulé ne les garantit pas. Le multi-plan natif Kling demeure un prototype simulé ; l’extension LTX intégrée n’a pas reçu d’essai réel dans ces lots. Les quatre lots ne déclenchent ni vidéo payante, ni publication, ni nouvelle activation de récurrence, ni push.
