# Textes de publication supervisés — lot 3, 25 octobre 2026

## Objectif et périmètre

Depuis une vidéo validée du Studio ou d’un épisode, proposer localement un titre YouTube, une description et des légendes Instagram/TikTok avec hashtags, puis laisser le créateur choisir les champs à appliquer. Aucune nouvelle génération vidéo, publication, activation de récurrence ou API texte payante n’est nécessaire. Le profil éditorial ne change pas la recette vidéo ni son consentement.

Le moteur textuel ne regarde pas la vidéo. Les textes reposent sur les scènes finales connues, le titre de la version approuvée et une précision factuelle facultative donnée par le créateur. Le résumé initial de récurrence n’est plus utilisé comme preuve de ce que montre une version réparée ou montée. Les textes restent à relire.

## Parcours

1. Valider la version vidéo puis ouvrir sa préparation de publication existante.
2. Sélectionner les réseaux. Dans « Des textes adaptés à chaque réseau », ouvrir éventuellement le profil de la série : langue, ton, public, mots-clés, marque/CTA facultatifs et listes de hashtags.
3. Préciser si besoin ce que montre cette version. « Enregistrer et proposer mes textes » conserve d’abord les saisies actuelles puis met une rédaction locale en file.
4. L’état est récupérable après navigation. Un résultat prêt recharge seulement un formulaire resté intact. Si des textes ont été saisis pendant l’attente, une invitation à enregistrer les conserve avant affichage de l’aperçu.
5. Cocher les champs à appliquer. Les textes existants ou modifiés par le créateur sont protégés par défaut. Un changement depuis la demande exige une autorisation explicite de remplacement ; le serveur le vérifie aussi.
6. Relire les champs finaux puis utiliser la confirmation de publication habituelle. Proposer ou appliquer des textes ne programme aucun envoi et ne change ni visibilité, ni audience, ni interactions ou déclarations.

Une nouvelle vidéo validée, une modification des scènes, du profil ou des réseaux invalide la proposition. Les brouillons d’une ancienne version et les programmations confirmées conservent leur source et leurs textes. Une modification du profil signale les textes appliqués comme à relire, sans les réécrire.

## Fichiers et responsabilités

- `AiSeries.editorialProfile`, `SeriesEditorialType`, `PublicationEditorialController` et `series/editorial.html.twig` : profil privé avec CSRF, propriété et révision. Route dédiée `/studio/series/{id}/textes`, indépendante de la configuration vidéo.
- `StoryProposal.kind` : séparation des scénarios (`story`, défaut historique) et des textes (`editorial`). `StoryProposals` conserve la file Messenger `story_writing`, l’expiration et la récupération ; les routes scénario continuent de ne lire que leurs propres jobs.
- `LocalStoryWriter::publication` et `PublicationTexts` : modèle Ollama configuré et mutex commun, réponse JSON structurée, listes de hashtags et limites contrôlées. Aucun fallback externe. Les hashtags sont assemblés dans les champs texte existants ; les réglages sociaux ne font pas partie du schéma du modèle.
- `PublicationEditorial` : instantané de la vidéo approuvée, des scènes, du profil et des réseaux ; empreinte liée à la version des consignes ; vérification de l’origine des champs et application sous verrou.
- `PublicationDraft.editorial`, `PublicationDrafts::save` : provenance durable des champs appliqués et des changements humains, y compris un champ volontairement vidé. Les anciens textes sans provenance sont conservés comme textes existants protégés.
- `PublicationComposerController`, `publication/_editorial.html.twig`, `publication_writer_controller.js` : sauvegarde préalable, aperçu, choix explicites, suivi sans perte des saisies et repli sans JavaScript.
- `Version20261025190000` : trois colonnes additives et un index des jobs par propriétaire/type/périmètre. Les jobs existants gardent le type `story` ; aucun brouillon existant n’est réécrit. Le retour arrière refuse de jeter un profil ou une provenance déjà enregistrés.

Une correction de `PublicationDraft::videoIds` réindexe la liste après les suppressions/ajouts d’éléments Doctrine. Sans `array_values`, des sauvegardes successives dans le même EntityManager pouvaient conserver des clés numériques discontinues, casser une comparaison de source ou perdre l’accès à l’élément d’indice zéro.

## Flux Symfony

Formulaire du préparateur et CSRF → sauvegarde durable des textes actuels → service d’instantané sous verrou → job typé et message Messenger → appel local hors de la requête web → résultat structuré → aperçu → application explicite sous contrôle de version et de contenu → même brouillon de publication. Doctrine conserve la source et la provenance ; Stimulus ne décide jamais seul d’écraser un texte ou d’autoriser un envoi.

## Vérifications de développement

Tests unitaires du lot et régression du rédacteur : 19 tests, 65 assertions réussies, clients HTTP simulés uniquement. Trois tests frontend ciblés réussis : formulaire modifié pendant l’attente, affichage d’un aperçu sur formulaire intact et déconnexion avec réponse tardive. PHPStan global réussi à ce stade.

Les cinq parcours fonctionnels de `PublicationEditorialTest` couvrent isolation des jobs, application partielle sans toucher les réglages, préservation des éditions humaines, profil périmé et brouillon programmé, aperçu privé et parcours HTTP de sauvegarde/proposition/application. Ils ont été exécutés par segments sur `shortflow_test` ; le premier passage a révélé les clés discontinues de la collection, corrigées, puis un assert sur champ vide normalisé à `null`, adapté. La consolidation finale et les migrations dev restent à exécuter par l’agent principal avec les autres lots.

La pertinence éditoriale du modèle local et la fidélité aux images ne sont pas garanties par ces simulations. Aucun essai vidéo ni publication réelle n’a été fait dans ce lot.
