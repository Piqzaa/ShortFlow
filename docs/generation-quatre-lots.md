# Génération IA : réalisation des quatre lots

Autorisation du propriétaire le 24 octobre 2026, après l’audit `audit-generation-studio-series-2026-10-24.md`. Réalisation successive, tests simulés, commits locaux sans push. Aucune génération payante ni publication réelle pendant ce chantier.

## Suivi

- [x] Lot 1 : mêmes rôles Studio/Séries, sélection explicite, retrait programmé de Pika, libellés honnêtes, format final explicite, tests existants réparés sans purge de file.
- [x] Lot 2 : références de sujets et cadres propres aux plans, conservation des anciennes préparations, récapitulatif des entrées et empreinte d’exécution.
- [x] Lot 3 : plan unique explicite, prototype multi-plan natif simulé et extension vidéo native avec devis du contexte.
- [ ] Lot 4 : prompts adaptés, aperçus/reprise, audio et entretien sûr des références.

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
