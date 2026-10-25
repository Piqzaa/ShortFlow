# Suite de l’audit Studio/Séries — 25 octobre 2026

Le propriétaire a autorisé l’action après le rapport `audit-studio-series-global-2026-10-25.md`. Réalisation successive, revue entre agents, tests simulés et commits locaux. Aucun essai vidéo payant, publication réelle, activation de récurrence réelle ni push.

- [x] Lot 1 : prompts/contexte figés, cadres sans priorité cachée, empreinte récurrente complète.
- [ ] Lot 2 : références nommées, capacités communes, transitions, limites et prévol cohérents.
- [ ] Lot 3 : profil éditorial et propositions locales par réseau, liés à la vidéo validée.
- [ ] Lot 4 : reprise des occurrences interrompues, états et action suivante.
- [ ] Lot 5 : recette de montage limitée aux fonctions existantes et finition UX.
- [ ] Recette finale simulée, navigateur et remise en route des workers.

Les résultats, fichiers, flux Symfony et tests seront consignés à la fin de chaque lot. Les essais réels restent séparément confirmés.

## Lot 1 — intégrité de la préparation acceptée

Objectif : un devis accepté conserve ses personnages, son style et chaque prompt, même si la série change ensuite. `AiEpisodeFlow` prépare une recette v4 avec `visualContext` figé ; la confirmation conserve le prompt effectif dans `AiShot.inputs.confirmedPrompt`. L’envoi utilise ce texte. Les cadres propres aux plans priment sur les raccourcis globaux encore renseignés.

`SeriesAuthorization` construit une empreinte commune de l’autorisation récurrente : recette, brief, durées, coût, accès BYOK, contrat complet du modèle et version des consignes. Les anciennes autorisations incompatibles exigent une nouvelle confirmation ; elles ne sont pas renouvelées implicitement.

Une ancienne recette v3 confirmée sans prompt figé ne permet plus de nouvel envoi. Le garde-fou intervient avant le prévol fournisseur, et encore sous verrou avant POST. Les demandes déjà transmises restent récupérables. Depuis une pause, « Revoir les plans restants » conserve les clips terminés, abandonne uniquement les demandes préparées/en attente jamais transmises, puis affiche un devis des plans restant à produire. Une demande incertaine n’est jamais considérée comme non envoyée.

Composants : `AiEpisode` conserve la recette et l’état de révision ; `AiShot` conserve prompt accepté et historique ; `AiGenerations` protège la transition payante ; `AiEpisodeController` et le template épisode donnent le chemin de relecture avec CSRF/révision. Les colonnes JSON existantes suffisent, sans migration propre à ce lot.

Vérifications : trois tests fonctionnels ciblés, 20 assertions réussies, exclusivement sur `shortflow_test`, avec fournisseur simulé ; un test unitaire d’autorisation, quatre assertions réussies. Cas couverts : série modifiée après préparation, cadres A/B, ancien job en file bloqué avant POST puis remplacé après nouveau devis. La revue de l’agent modèles a ajouté le garde-fou avant prévol pour éviter une impasse en cas de contrat catalogue modifié. Les contrôles globaux seront consolidés après les lots suivants.

Flux Symfony : formulaire → service sous verrou propriétaire → instantané Doctrine → devis et consentement → message Messenger → prompt accepté vers adaptateur. À tester sans coût : préparer des plans, modifier les valeurs par défaut de la série dans un autre onglet, puis vérifier que le devis de cet épisode reste identique ; aucune génération ne part sans confirmation.
