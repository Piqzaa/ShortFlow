# Réseaux et date propres à chaque vidéo

Lot du 26 octobre 2026. Une nouvelle sélection de 2 à 20 vidéos ouvre un écran d’organisation avec un choix de réseaux, un mode « Maintenant » ou « Choisir une date » et un fuseau par vidéo.

## Parcours

1. Dans Vidéos, sélectionner plusieurs vidéos puis préparer la sélection.
2. Choisir les réseaux et le créneau de chacune. Exemple : première maintenant sur YouTube/Instagram, deuxième dans trois jours à 14 h sur TikTok, troisième dans une semaine sur les trois réseaux.
3. « Enregistrer mes préparations » crée un brouillon indépendant par vidéo. Aucun envoi n’est lancé et aucune programmation n’est activée.
4. Ouvrir « Textes et confirmation » pour la vidéo choisie, compléter les textes et réglages sociaux, puis confirmer son envoi immédiat ou sa programmation avec le moteur existant.
5. Le lien « Les vidéos de ma sélection » permet de revenir aux autres préparations tant que cet espace de travail est disponible. Chaque brouillon reste durablement accessible depuis Vidéos et le calendrier, même après expiration de la sélection en session.

La sélection reste un espace temporaire d’une heure, dans la limite existante de dix espaces de travail. Elle n’ouvre pas vingt compositeurs à l’avance. Les préparations multiples déjà enregistrées restent dans leur ancien parcours ; elles ne sont pas découpées silencieusement. La préparation d’une seule vidéo et les épisodes de série gardent leur parcours.

## Fichiers et responsabilités

- `PublicationComposerController::start` conserve les contrôles de sélection/propriété puis oriente les nouvelles sélections multiples vers `app_publication_selection`. La route historique `app_publication_batch` reste disponible pour son ancien parcours.
- `PublicationBatchType` construit uniquement les lignes autorisées par la session et protège CSRF/révision ; `PublicationBatchRowType` valide réseaux, mode et fuseau. Des IDs supplémentaires sont rejetés par le formulaire.
- `PublicationBatchController` contrôle propriétaire et expiration, présente les lignes et retrouve les brouillons créés. Il ne confirme aucune publication.
- `PublicationBatch::prepare` vérifie toutes les vidéos, réseaux et dates avant d’enregistrer. Il réutilise `PublicationDrafts::save` et `plan` sous le verrou propriétaire PostgreSQL et une transaction commune. Les comptes attendus sont pris côté serveur depuis les connexions courantes, pas depuis un champ libre du navigateur.
- `PublicationDraft.preparationKey` est une clé unique, facultative, calculée pour propriétaire + sélection + vidéo. Elle évite de recréer les brouillons lors d’un rejeu, même si la réponse/session du premier enregistrement a été perdue. Elle est attribuée après le rechargement du brouillon par `plan`, puis enregistrée dans la transaction.
- Migration additive `Version20261026190000` : colonne nullable et index unique. Les anciens brouillons gardent une valeur nulle ; aucune suppression de données.
- `templates/publication/selection.html.twig` affiche organisation et résultat ; `publication.css` adapte les lignes au mobile. Le contrôleur Stimulus `composer` existant gère les champs de date séparément pour chaque ligne. Aucun nouveau framework ni dépendance.

Flux Symfony : sélection privée → formulaire validé → validation de toutes les lignes → verrou/transaction → un brouillon + créneau proposé par vidéo → récapitulatif de sélection → compositeur individuel → confirmation habituelle → worker existant. `plan` propose une date ; seul `confirm` active l’envoi ou la programmation.

Un défaut sur une ligne ou un limiteur rencontré après le premier enregistrement annule tout le lot. Les objets Doctrine sont vidés après rollback et rechargés avant affichage : un ID obtenu avant le rollback ne doit pas être réutilisé comme un brouillon enregistré. Les sauvegardes restent soumises à la limite existante de 30 par minute.

Le lot suivant ajoute le choix des comptes sur chaque ligne ; voir [comptes-sociaux-multiples.md](comptes-sociaux-multiples.md). L’audit initial est conservé dans [audit-produit-concurrence-20261026.md](audit-produit-concurrence-20261026.md).

## Vérifications

- Migrations appliquées à `shortflow_test` avant PHPUnit ; migration additive appliquée aussi à la base locale. Schéma de test validé.
- Tests du nouveau parcours : choix indépendants, dates converties correctement, brouillons non activés, reprise sans doublon après vidage Doctrine, date invalide dans la dernière ligne, rollback après un premier flush, CSRF, vidéo supplémentaire et isolation propriétaire. Clients sociaux/fournisseurs simulés ; aucune publication ou message d’envoi créé.
- Le premier lot de 17 tests a trouvé quatre échecs : trois dus au conflit de nom avec une ancienne route et un dû à la clé non persistée avant un rechargement Doctrine. Ces deux défauts ont été corrigés ; un cache de routes de test ancien a ensuite été actualisé. Rejeu du nouveau parcours : **5 tests / 81 assertions réussis**. Les douze tests du compositeur existant avaient réussi dans le premier lot.
- Frontend : **136 tests réussis**, contrôle syntaxique et build réussis ; avertissements statiques/Three.js existants.
- PHPStan sans erreur, CS-Fixer sans correction, conteneur Symfony valide et 100 templates Twig valides. Le contrôle final du retour vers la sélection et de l’ancienne route a réussi : **1 test / 42 assertions**, journal ignoré `artifacts/selection-return-test.log`.
- Recette navigateur sur deux vues synthétiques : choix de réseaux propres aux trois lignes, dates indépendantes, champs affichés selon le mode, récapitulatif distinct. Contrôle à 390 × 844 sans débordement horizontal ni erreur JavaScript. Les vues bloquent les POST HTTP ; les préparations de test et leur propriétaire synthétique sont supprimés après rendu.

La suite PHP complète a ensuite été relancée avec le lot multi-comptes : **601 tests, 7 268 assertions réussis**. Aucun essai social réel, génération vidéo payante ou nouvelle récurrence. Commit et push de l’ensemble demandés explicitement par le propriétaire.
