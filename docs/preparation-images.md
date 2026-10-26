# Préparer les images des plans

Lot local du 26 octobre 2026. Objectif : aider à préparer une image de début par plan avec les textes et références déjà choisis, sans ajouter d’API image ni de dépense automatique.

## Parcours

Dans le Studio ou la préparation d’un épisode de série, ouvrir « Personnaliser les scènes et les durées », puis « Préparer l’image de ce plan ».

1. Écrire la scène et cliquer sur « Proposer des consignes ».
2. Relire ou modifier les consignes. Si un texte existe déjà, comparer la proposition puis choisir explicitement de le remplacer.
3. Copier le texte dans ChatGPT ou un autre outil d’images et y joindre les références visuelles nécessaires. L’accès, les quotas et les éventuels coûts dépendent de cet outil.
4. Créer l’image dans cet outil. Choisir un mode image et un modèle compatible dans VydFlow, puis importer le résultat dans « Début de ce plan ».
5. Vérifier les scènes, les références et le coût vidéo avant la confirmation habituelle.

En mode texte, ces images ne sont pas transmises au modèle vidéo. Le mode personnage utilise des références communes ; il ne reçoit pas de cadre propre à chaque plan. En raccord d’image, les plans suivants reprennent la dernière image du clip précédent. Les images aident à fixer l’apparence et la composition, sans garantir les mouvements, les voix ou la continuité finale.

## Composants et flux

- `assets/image-preparation.js` compose localement les instructions à partir de l’idée, de la scène, du format, du style et des personnages. Il décrit un instant avant l’action, sans sous-titres ajoutés, et refuse un texte dépassant 3 000 caractères plutôt que le tronquer.
- `assets/controllers/image_preparation_controller.js` gère la proposition, la comparaison, le remplacement explicite et la copie avec sélection manuelle de secours. Il ne fait aucun appel réseau. Une modification invalide une proposition en attente.
- `templates/ai/_shot_references.html.twig`, partagé par les préparations, rend le champ et les imports privés existants. Les textes restent échappés par Twig. Le champ reste utilisable manuellement sans JavaScript.
- `src/Form/AiShotType.php` définit le champ facultatif `imagePrompt`, avec la même limite serveur, y compris lors de l’autosauvegarde. `src/Service/AiPlanInputs.php` le valide et le conserve dans les données JSON du plan, mais l’exclut des entrées fournisseur.
- `src/Entity/AiShot.php` documente la métadonnée JSON ; aucune migration n’est nécessaire. Les brouillons de Studio utilisent aussi leur autosauvegarde existante.
- Les contrôleurs de redistribution et de rédaction conservent les consignes lorsque le nombre de plans ne change pas. S’il change alors que des consignes ou des cadres existent, ils demandent de les déplacer ou de les retirer explicitement avant de continuer. La route serveur de redistribution applique la même protection.

Flux : saisie → composition locale → relecture humaine → événement `input` → sauvegarde habituelle → champ rechargé avec le plan. La génération vidéo continue d’utiliser son propre prompt. Les consignes d’image ne sont pas une entrée du rédacteur local et restent hors de son empreinte de requête.

À comprendre en Symfony : le FormType porte la validation des données reçues ; le service métier contrôle leur conservation ; Doctrine les stocke dans le JSON existant ; Twig affiche les valeurs échappées. Stimulus améliore l’interface sans remplacer la validation serveur.

## Vérifications

Migrations de test vérifiées avant PHPUnit, exclusivement sur `shortflow_test`.

- PHPUnit ciblé : **3 tests, 28 assertions**, réussis (persistance, échappement, séparation du prompt vidéo, autosauvegarde et rejet de longueur excessive).
- Tests frontend : **135 tests réussis**, y compris propositions, protection des textes personnels, modification de scène, modes incompatibles, copie de secours et ancien formulaire.
- `npm run check`, `npm run build`, PHPStan niveau 5, CS-Fixer en vérification et lint des 98 fichiers Twig : réussis. Le build conserve ses avertissements existants de ressources statiques et taille du bloc Three.js.
- Interface réelle rendue avec un propriétaire synthétique de la base de test, puis supprimé : ouverture, comparaison et remplacement explicite vérifiés. Scène vidéo inchangée. Contrôle à 390 × 844 sans débordement horizontal ; aucune erreur JavaScript observée. La vue de contrôle refuse tout POST.
- Revue entre agents en lecture seule effectuée ; bouton sans effet de l’ancien formulaire corrigé.

Commandes ciblées :

```sh
npm run check
npm run test:ui
npm run build
docker compose exec -T -e APP_ENV=test php php bin/phpunit --filter 'testImageInstructionsSurviveSavingAndStaySeparateFromVideoPrompts|testImageInstructionsAreBoundedDraftMetadata|testIdeaAutosaveKeepsIncompleteInputsWithoutCreatingGeneration'
```

Aucun appel image ou vidéo payant, aucune activation de récurrence, publication ou connexion supplémentaire. La suite PHP complète n’a pas été relancée pour ce lot ; les résultats ci-dessus concernent les contrôles ciblés.
