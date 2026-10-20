# Suivi visible des générations IA

## Objectif et parcours

Pendant une génération, la page affiche un anneau animé et quatre étapes : Préparation, Génération, Récupération, Prête. Une demande acceptée peut encore attendre dans la file du fournisseur ; ce cas est indiqué distinctement. Aucun pourcentage ni temps restant inventé. Le résultat s’affiche automatiquement une fois importé, ou la page montre le problème si une vérification est nécessaire. L’utilisateur peut toujours partir puis revenir depuis son historique.

## Composants et flux Symfony

- `AiGeneration` conserve `providerStage` et `providerCheckedAt`. Migration additive `Version20261020030000`, sans modification des anciens jobs. Une observation ne modifie pas `updatedAt` : le délai de récupération de 45 minutes ne doit pas être prolongé indéfiniment par les sondages.
- `FalVideoApi`, `FalMcpVideoApi`, `ReplicateVideoApi` et `LtxVideoApi` traduisent leurs états déjà reconnus en `queued` ou `running`. Ni logs, pourcentages libres, URL distante ni identifiant de compte ne sont copiés dans le suivi.
- `AiGenerations` enregistre l’observation pendant le travail Messenger, puis `importing` avant le téléchargement et la validation du fichier. L’import et le statut final restent transactionnels et idempotents. Aucun nouveau POST de génération ajouté.
- `AiStudioController::status` répond uniquement pour le propriétaire, avec état local, révision, étape et date du dernier retour. GET privé sans cache : aucun accès au fournisseur depuis la page ni nouvel envoi.
- `generation_watch_controller.js` lit ce GET toutes les 4 secondes pendant que la page est visible. Le worker consulte le fournisseur environ toutes les 20 secondes : c’est un suivi périodique, pas un flux vidéo ou un pourcentage en continu. Les étapes changent sur place ; seul un état final recharge la page pour afficher le résultat ou la récupération nécessaire.
- `ai/_progress.html.twig`, `ai/generation.html.twig` et `ai-studio.css` rendent les étapes dès le serveur, puis Stimulus les actualise. Le loader est indéterminé (`progressbar` sans `aria-valuenow`), l’étape porte `aria-current`, les changements utiles sont annoncés poliment et reduced-motion désactive la rotation. Les messages identiques ne sont pas réannoncés à chaque sondage.

À comprendre en Symfony : Messenger travaille même sans navigateur. Doctrine rend son dernier résultat visible dans une requête HTTP indépendante. Stimulus améliore cette lecture, mais ne déclenche aucune génération. Garder les identifiants fournisseur dans le serveur permet un suivi privé et évite qu’ouvrir plusieurs onglets multiplie les appels distants.

## États difficiles

Onglet caché : requête abandonnée, aucun sondage tant qu’il est caché. Retour : contrôle immédiat. Requête limitée à 10 secondes ; panne temporaire : nouvelle tentative espacée de 20 secondes ; après trois échecs : anneau figé et lien « Actualiser le suivi » conservé. Le message précise que le travail peut continuer. Sans JS, le rendu initial et ce lien restent utilisables. Aucun redémarrage automatique payant.

## Comment tester

1. Confirmer soi-même un essai autorisé puis rester sur la page : les étapes et la date changent, le lecteur apparaît à la fin.
2. Quitter puis revenir par l’historique : étape persistée ; aucun second envoi.
3. Tester les retours file/running/importing avec HTTP simulé, puis une erreur de récupération : le même job reste récupérable.
4. Vérifier à 390 px, clavier, reduced-motion et panne réseau ; aucun pourcentage fabriqué ni débordement horizontal.
5. Autre propriétaire : endpoint en 404 ; le JSON ne contient ni reçu, clé, prompt ni URL fournisseur.

Tests automatisés : génération complète sur shortflow_test avec APIs simulées ; états normalisés des adaptateurs, échéance inchangée, propriétaire/CSRF et absence de renvoi ; tests JS de transitions sans rechargement, données invalides, pause et panne. Voir `docs/verification.md` pour les résultats exécutés. Aucun essai payant lancé par Codex pour ce lot. Le propriétaire a confirmé le succès de son essai image vers vidéo avant ces changements.
