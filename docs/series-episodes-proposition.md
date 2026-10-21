# Séries et épisodes — proposition du 21 octobre 2026

Objectif exprimé : « chaque mardi à 17 h, une vidéo IA de 45 s composée de plusieurs plans, montée avec sous-titres, dans un thème constant et avec des épisodes qui se suivent ».

Le mini éditeur livré est la recette de montage de ce futur parcours. Cette note cadre la suite ; aucun ordonnanceur, génération payante, service externe ou publication autonome ajouté dans ce lot.

## Parcours proposé

1. **Créer une série** : nom, thème/prompt général, public visé, ton, style visuel, langue. Choisir « épisodes indépendants » ou « histoire à suivre ».
2. **Fixer les références** : fiche des personnages et du décor, images autorisées, éléments à préserver et à éviter. Un prompt seul ne garantit pas le même personnage d’une génération à l’autre. Le mode image vers vidéo et les références compatibles du modèle aideront, sans garantie parfaite.
3. **Préparer un épisode** : idée, numéro, résumé des précédents, objectif et fin ouverte si souhaitée. Produire un scénario et une liste de plans modifiables, puis les générer après récapitulatif du coût.
4. **Monter** : ordre des plans, durées, format, voix, sous-titres facultatifs, musique autorisée et volumes. Réutiliser le projet de montage et son rendu actuel. Les sous-titres transcrivent une voix : des plans muets nécessiteront une narration ou des textes saisis.
5. **Planifier** : destinations, jour/heure et fuseau, préparation suffisamment en avance, validation par défaut, plafonds par épisode/semaine, pause. Un épisode non prêt ne se publie pas tardivement sans choix explicite.

## Durée et cohérence

45 secondes n’implique pas systématiquement 3 plans : 3 × 15 s si le modèle le permet, sinon 5 × 9 s, 6 plans plus courts ou des clips importés. Vérifier les durées natives, l’audio, les références, le prix et la disponibilité réelle pour le modèle sélectionné. Chaque plan devient un résultat conservé ; reprendre seulement le plan échoué, sans régénérer tout l’épisode ni republier une destination réussie.

Une « bible de série » garde les règles stables. Une mémoire courte contient les résumés des épisodes validés et la situation à la fin du dernier épisode. On conserve séparément le scénario envisagé et ce qui a effectivement été validé : un échec ne doit pas faire avancer l’histoire. Le propriétaire peut corriger cette mémoire. Les références d’image améliorent la continuité visuelle ; voix constante et synchronisation labiale sont des lots distincts selon les modèles.

Pour un premier lot, proposer des épisodes indépendants sur un thème avec validation. Ajouter ensuite la mémoire narrative et les épisodes qui se suivent. Le récit cohérent exige plus de contrôle que la simple récurrence.

## Ordre de construction proposé

- **Prochain lot : épisode manuel de plusieurs plans.** Script, liste de plans, références, coût total avant lancement, suivi par plan et assemblage des résultats. Réutiliser les adaptateurs BYOK et le montage existants. Tester une première vidéo de 30–45 s avant toute récurrence.
- **Puis recette de série enregistrée.** Réutiliser thème, personnages et réglages de montage ; démarrer l’épisode suivant manuellement, avec contrôle de la continuité.
- **Enfin récurrence.** Générer en avance, attendre la validation ou appliquer le mode automatique explicitement choisi, contrôler budgets/crédits/rate limits et publier via le moteur existant. Prévoir les changements d’heure, notifications, pause, révocation de clé, échecs partiels et créneaux manqués.

Rester dans le monolithe Symfony : entités seulement lors de leur lot, services métier et Messenger pour les étapes longues. Aucun moteur générique de workflows ou classes futures créés d’avance. La clé BYOK finance directement le fournisseur ; l’abonnement VydFlow finance le logiciel. Aucun wallet introduit.

La génération automatique de scénario et de mémoire demandera un modèle de texte, distinct du modèle vidéo. Commencer avec un scénario saisi/corrigé manuellement, puis choisir ensemble un accès texte local ou BYOK, ses coûts et sa qualité. Aucune clé supplémentaire exigée par le mini éditeur actuel.
