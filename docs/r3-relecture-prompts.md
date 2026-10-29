# Correction de relecture des scènes — 29 octobre 2026

Complément après essai du propriétaire, avant R3.4. Grand R3 reste en cours.

## Ce qui change

Le résumé français devient un repère en lecture seule. Les anciens textes restent conservés, affichés et transportés dans le formulaire ; aucune donnée existante n'est supprimée. Le champ à modifier porte désormais le nom « Prompt vidéo — consignes envoyées au modèle ». L'aide demande les directions en anglais pour ce parcours et conserve les paroles/textes demandés dans leur langue. Ce choix ne constitue pas une affirmation que tous les fournisseurs imposent universellement l'anglais.

Modifier le prompt ne réécrit ni ne retraduit le résumé. La carte signale ce décalage possible ; son repère de relecture n'atteste aucune fidélité. Les anciens plans sans résumé restent éditables par leur prompt, sans résumé inventé. Cette décision du propriétaire remplace l'édition du résumé introduite en R3.2.

Deux contrôles lexicaux donnaient auparavant des résultats contradictoires : la garde de traduction reconnaissait certains textes anglais descriptifs, tandis que l'alerte du scénario utilisait une liste plus limitée. `StoryTextChecks` partage désormais la même classification entre ces deux chemins. La garde avant devis/envoi conserve exactement ses règles antérieures, y compris les exemptions de citations exactes demandées. Les contrôles de répliques restent distincts. Ce sont des heuristiques, pas une certification de traduction ou de vérité narrative.

Les alertes de proposition n'empêchent pas la copie d'un brouillon pour correction. L'interface l'explique et annonce le nombre de scènes copiées, ouvre les cartes et dirige le focus vers le premier prompt. Un import d'image en cours garde la proposition intacte et demande d'attendre. Les protections d'empreinte et de références sont conservées. Le devis et sa confirmation restent nécessaires pour générer.

## Fichiers et flux Symfony

- `AiShotType` garde le résumé dans un `HiddenType` validé à 300 caractères. Un champ caché sert au transport, pas à la sécurité : les validations serveur et la propriété restent applicables.
- `_shot_card.html.twig` et `_shot_description.html.twig` affichent le résumé échappé et expliquent le rôle du prompt. Le même composant sert au Studio et aux épisodes.
- `shot_editor_controller.js` conserve les textes et place le focus dans le prompt visible ; `story_writer_controller.js` copie volontairement les scènes sans appel vidéo, puis annonce le résultat.
- `StoryTextChecks.php` centralise uniquement la décision lexicale de langue. Aucun fournisseur, délai, prix ou modèle n'est modifié.

Flux : résultat de rédaction enregistré → aperçu et alertes → clic volontaire → copie dans le formulaire courant → édition du prompt → validation serveur et récapitulatif → confirmation distincte du coût. Le résumé affiché n'est pas envoyé comme prompt vidéo.

## Preuves et limites

Diagnostic en lecture seule : la dernière proposition signalée contenait quatre scènes et `translationReady=true`, avec l'alerte de langue indéterminée. Aucun texte privé n'est recopié dans ce document. Les propositions déjà enregistrées ne sont pas réévaluées ni relancées automatiquement : une ancienne alerte peut donc rester visible.

Avant les dernières modifications, une reproduction synthétique avec HTML Twig réel, assets compilés et réponse locale simulée a copié les quatre scènes malgré cette alerte, sans erreur console. Le refus exact dans l'onglet réel du propriétaire n'a pas été reproduit. L'accès à Edge a ensuite été arrêté par l'outil, qui ne pouvait pas vérifier son URL avec assez de certitude. Aucune nouvelle recette navigateur n'est revendiquée après cet arrêt ; les derniers changements sont contrôlés par les tests et la revue de sources.

Revue indépendante en lecture seule : pas de blocage identifié dans la classification commune, les citations, les répliques, le transport du résumé, le focus et la protection pendant import.

Vérifications exécutées :

- Premier passage ciblé des trois classes unitaires et de `AiEpisodeFlowTest`/`AiStoryProposalTest` : **102 tests / 917 assertions, deux échecs de rendu**, dus aux anciens Twig compilés du cache test sans debug. Les diagnostics montraient les deux anciens libellés. Aucun autre échec.
- Cache test régénéré puis rejeu des trois classes unitaires et des deux cas fonctionnels concernés, sans modifier les attentes : **59 tests / 261 assertions réussis**. Pas de nouvelle suite PHP complète ; les 780 / 8 926 du complément précédent restent historiques.
- **164 tests frontend réussis**, syntaxe `npm run check` et build Vite réussis. Avertissements de chemins de médias/polices et de taille du chunk Three.js conservés.
- PHPStan sur les deux fichiers PHP applicatifs modifiés, CS-Fixer sur les quatre fichiers PHP modifiés, lint des **29 Twig** du répertoire `templates/ai` et lint conteneur dev réussis. Première commande CS-Fixer multi-chemins refusée faute de paramètre de configuration ; relance avec `.php-cs-fixer.dist.php` réussie.
- Cache dev actualisé et quatre workers précédemment actifs relancés après garde à zéro des messages, rédactions, occurrences, générations et montages en traitement. Workers vérifiés actifs. Aucun schéma ajouté.

Commit local : `fix: clarify scene prompt editing and language review`, aucun push.

## Comment essayer

Recharger le Studio ou l'épisode. Appliquer une proposition : vérifier le message indiquant le nombre de scènes copiées et les prompts présents dans les cartes. Le résumé doit être lisible sans champ d'édition. Modifier le prompt, constater l'avertissement de décalage du résumé, puis relire le texte exact au récapitulatif. Une proposition signalée reste copiable pour correction ; une consigne française ou indéterminée reste contrôlée avant génération. Aucun essai payant n'est nécessaire.

R3.4 reste le prochain point ; le propriétaire souhaite régler ces détails avant sa reprise. Aucun appel modèle réel, vidéo, coût, publication, activation, migration ou push.
