# R3.1 — Parcours commun Studio / Séries

28 octobre 2026. **Cadrage validé par le propriétaire**, qui conserve la possibilité d'ajuster le parcours après essai dans l'application. R3.1 terminé ; implémentation progressive à partir de R3.2. La description ci-dessous est une cible, pas la déclaration de livraison de tous les points R3.

## Objectif et point de départ

Permettre de commencer par son idée, relire ses scènes et comprendre son devis avant toute génération. Conserver les six destinations : Vidéos, Studio, Séries, Calendrier, Publications, Connexions ; Mon compte reste accessible.

Lecture des sources à `cce6428` : `templates/ai/studio.html.twig` présente source, format, références, idée, durée, enchaînement, modèles, personnages, proposition locale et plans. `templates/ai/_preparation_plans.html.twig` expose les plans ouverts avec leurs détails. `AiEpisodeController::show()` réutilise déjà le Studio par `forward()` pour la préparation des épisodes, et affiche le devis via `review=1`. `templates/series/configure.html.twig` conserve le cadre commun de la série ; ce formulaire ne crée pas un devis groupé.

Les constats UX-03 à UX-05 de [l'audit UX](audit-global-20261027-ux.md) motivent ce cadrage. Ce travail repose sur les sources, pas sur une nouvelle recette de compréhension utilisateur.

## Proposition à valider

Trois groupes lisibles, avec retour libre : **Idée → Scènes → Récapitulatif**. Idée et Scènes restent sur l'écran commun de préparation ; Récapitulatif réutilise le devis existant. Les trois groupes ne deviennent pas trois pages d'assistant obligatoires. Aucun nouvel onglet « À valider ».

| Groupe | Ce que le créateur voit directement | Détails accessibles | Action suivante |
| --- | --- | --- | --- |
| Idée | Sujet/contexte, durée totale, façon de filmer, format, modèle et état de connexion ; références requises selon le mode | Choix guidé du modèle, résolution, connexion/facturation, gestion des références et nom | « Proposer mon scénario » ou « Écrire mes scènes » |
| Scènes | Nombre et total des plans ; carte par plan avec résumé français, durée et rôle de référence ; avertissements | Prompt exact, images/consignes, ordre, ajout/retrait, son des clips | « Voir le récapitulatif » ; enregistrement accessible |
| Récapitulatif | Modèle/fournisseur/méthode de connexion, nombre de plans et durées, références effectivement utilisées, audio/montage, coût total, limites et confirmation | Prompts exacts de chaque appel et détail des références | « Modifier les scènes » ou « Confirmer et générer la vidéo » |

Les détails peuvent être repliés, les erreurs et décisions nécessaires restent visibles. Les références requises s'ouvrent dans Idée ; les cadres propres à un plan restent dans sa carte. Le devis distingue référence commune, cadre propre, cadre issu du clip précédent et absence de référence, sans promettre une identité parfaite.

## Parcours concrets

1. **Nouvelle vidéo, avec proposition locale.** Saisir le contexte et la durée ; consulter le modèle proposé et ses contraintes. Demander un scénario, suivre attente/rédaction puis relire la proposition et ses alertes. L'application reste volontaire et annonce les textes/durées remplacés. Après application, montrer les scènes concernées et permettre leur correction avant le récapitulatif. La rédaction locale reste distincte de la connexion nécessaire à la vidéo.
2. **Écriture manuelle.** Choisir « Écrire mes scènes » ouvre les cartes même sans proposition. Résumé français et prompt envoyé restent distincts. Un ancien plan sans résumé indique « Résumé à compléter » et laisse son prompt accessible ; aucune inférence automatique pour remplir ce manque. Après édition du prompt, le résumé est signalé « À revérifier » jusqu'à une action explicite de relecture à définir en R3.2.
3. **Épisode d'une série.** Depuis la série, ouvrir le même parcours avec contexte, réglages et références enregistrés. Garder nom de série et lien de retour ; les changements ne réécrivent pas silencieusement les autres épisodes. La création/configuration de série reste le cadre commun, avec choix de modèle harmonisé en R3.4 ; chaque épisode a ses scènes et son devis.
4. **Préparation automatique récurrente.** Retrouver l'épisode dans la série et le calendrier existants, relire les scènes, puis confirmer son propre devis. La préparation automatique des scénarios ne vaut jamais confirmation vidéo ou publication. Aucun nouveau centre de validation.
5. **Reprise.** Retrouver les données enregistrées et le suivi local existant sans renvoi automatique. Distinguer saisie non enregistrée, enregistrement réussi/échoué, proposition prête, devis et génération. Conserver les parcours actuels de pause, récupération des demandes transmises, remplacement d'un plan et validation de la vidéo finale.

## Décisions et cas difficiles

- **Pas de clé vidéo :** préparer/enregistrer et demander du texte local si disponible ; afficher « À connecter » pour la vidéo et proposer la connexion en conservant le travail. Ne pas laisser croire que le texte local exige une clé vidéo. Une connexion seule ne génère rien.
- **Moteur local indisponible :** diagnostic et reprise volontaire, écriture manuelle accessible. Aucune nouvelle tentative automatique.
- **Plan unique avec plusieurs plans :** montrer le nombre actuel et l'action de transformation près du mode, avant tout effet. R3.3 doit préserver les textes et références ou refuser explicitement la conversion ; aucune durée réduite ni modèle remplacé silencieusement.
- **Erreurs et imports :** ouvrir la section/carte concernée, associer le message au champ et y rendre le focus accessible. Conserver les gardes d'import en cours et les validations serveur ; un panneau fermé ne masque pas une erreur.
- **Modifications après devis :** conserver les contrôles existants de révision/empreinte et recalculer avant confirmation ; aucun devis périmé ne donne le droit d'envoyer.
- **Anciennes préparations :** conservation des prompts, résumés éventuels, images, durées, modèles, méthodes de connexion, textes sociaux et validations. Aucun remplissage IA ni migration de données proposé ici.
- **Sans JavaScript :** les champs manuels, liens, erreurs et soumissions serveur restent utilisables ; l'indisponibilité de la proposition automatique et les limites d'import propres aux plans restent expliquées. Ne pas annoncer un autosave hors session ou sans JS.
- **Après génération :** conserver suivi, validation de la version vidéo, montage et préparation de publication ; le devis vidéo ne confirme aucun envoi social.

## Répartition et recette future

| Point | Livraison prévue | Contrôle attendu |
| --- | --- | --- |
| R3.2 | Cartes compactes et détails progressifs | Ancien plan sans résumé, prompt modifié, manuel/local, erreurs ouvertes |
| R3.3 | Mode unique et transformation rapprochés | Trois plans rédigés, images affectées, durée incompatible, aucune perte silencieuse |
| R3.4 | Choix guidé modèle/connexion commun | Vidéo et série sans clé, texte local disponible/indisponible, réglages conservés |
| R3.5 | Action suivante et récapitulatif lisibles | Références réellement envoyées, compte/méthode, son, coût et confirmation |
| R3.6 | Navigation, libellés et explications locales | Contexte série après forward, personnages/objets, comptes par vidéo, créneau manqué |
| R3.7 | Recette et revue globale indépendante | Ancien/nouveau, brouillon, clavier, mobile/desktop, pause/remplacement, protections |

La recette devra aussi vérifier une saisie longue, huit plans, une erreur dans un détail fermé, un retour depuis le devis, un fournisseur déconnecté et une préparation automatique à relire. Les contrôles seront simulés, sans POST fournisseur ou social réel. Aucune qualité vidéo/audio ne sera déduite de cette recette.

## Repères Symfony et pédagogie

Le regroupement visuel se fera dans Twig (`templates/ai/studio.html.twig`, les partiels `templates/ai/_*.html.twig` et `templates/series/episode.html.twig`). Stimulus gérera ouverture des détails, annonces et focus ; les formulaires Symfony et les services resteront responsables des règles. `AiStudioController` reçoit la préparation ; `AiEpisodeController` retrouve l'épisode propriétaire et présente le devis ; `AiEpisodeFlow` conserve préparation et contrôles métier. La configuration de série reste dans son propre formulaire.

Flux à conserver : saisie → proposition locale facultative → application volontaire ou écriture manuelle → validation serveur/enregistrement → devis → confirmation explicite → traitement asynchrone → validation de la vidéo → préparation de publication. Le `forward()` est un appel interne, pas une navigation du navigateur : son contexte devra être transmis explicitement pour garder Séries active. Replier un élément HTML ne désactive ni ses champs ni les contrôles serveur.

Pour tester ce cadrage aujourd'hui : lire les cinq parcours et vérifier que chaque décision a un emplacement et une action ; la recette fonctionnelle et visuelle appartient à l'implémentation R3.2–R3.7. Aucun test applicatif ou rendu de la future interface exécuté pour ce document.

## Validation et suite

Le propriétaire a validé ce parcours avant R3.2, conformément à R3.1 de [la roadmap](roadmap.md), puis demandé son implémentation. Les retours d'usage pourront conduire à des ajustements. Cette validation n'autorise ni coût fournisseur ni activation réelle. La revue globale indépendante de R3 reste à faire en R3.7.
