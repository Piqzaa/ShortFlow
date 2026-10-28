# R2.5 — Contexte nécessaire aux textes sociaux

Un titre tel que « Vacances 2026 » ne dit pas ce qui arrive dans la vidéo. Le rédacteur demande désormais une description ou des scènes avant de proposer les textes. Il ne remplace pas cette absence par une histoire supposée, et ne consomme aucun appel au modèle dans ce cas.

## Règle et limites

`PublicationTexts::requireContext()` recherche dans `description` ou dans une chaîne de `scenes` un texte contenant au moins deux mots/nombres et une lettre, distinct des titres après normalisation de casse, espaces et ponctuation. Les titres, identifiants vidéo/épisode et paramètres de profil ne servent jamais de contexte à eux seuls. Valeurs vides, types incorrects, nombres seuls et simple reprise du titre entraînent une demande de précisions.

Ce seuil est un contrôle de présence minimal, pas une validation sémantique. Deux mots comme « mug bleu » peuvent fournir un contexte utile ; une description erronée ou générique peut aussi passer. Les scènes sont des indications de préparation et ne prouvent pas que le rendu les respecte. Le modèle ne regarde ni la vidéo ni les images : le créateur doit préciser les changements du montage, vérifier les faits et relire les propositions. Aucun détecteur universel d'hallucinations n'est livré.

## Flux Symfony et fichiers

1. Le préparateur enregistre les modifications humaines avant de demander les textes. `PublicationEditorial` fige le descriptif, les scènes, les réseaux et la source comme auparavant ; `StoryProposals` conserve la demande via Messenger. Le champ de précision ne devient pas obligatoire pour enregistrer ou publier des textes manuels.
2. `LocalStoryWriter::publication()` appelle le contrôle de contexte avant le verrou local et l'HTTP. En cas de manque, il produit une `PublicationException` de raison `context`, avec une action française. Le worker termine la demande en échec durable et libère sa place ; aucune inférence, réparation ou relance automatique.
3. Le template `templates/publication/_editorial.html.twig` explique le besoin de scènes ou de précisions, donne un exemple et relie l'aide au champ avec `aria-describedby`. Il rappelle que la vidéo n'est pas vue et que la saisie directe reste disponible.
4. Le créateur peut préciser ce que montre la version puis demander une nouvelle proposition. Les consignes du modèle distinguent intitulés et faits fournis ; elles excluent les préparatifs, projets ou résultats déduits sans source. La validation des packs/hashtags de R2.4 reste identique, avec un seul appel de 180 secondes maximum.
5. `PublicationEditorial::apply()` vérifie aussi le contexte, après propriété, état et version de la proposition. Une ancienne proposition générée à partir de titres seuls reste stockée mais ne peut plus être appliquée ; demander une nouvelle proposition avec des précisions. Aucun texte déjà appliqué n'est supprimé ou réécrit. Les contrôles existants de champs cochés, éditions concurrentes et consentement au remplacement restent présents.

En Symfony, le contrôle métier partagé évite qu'un appel direct au writer ou l'application d'un ancien résultat contourne la règle. Le contrôleur reste léger ; le message Messenger conserve le fonctionnement asynchrone et le diagnostic est affiché depuis l'état enregistré. Aucune nouvelle entité, dépendance ou migration.

## Vérifier et reprendre

`PublicationContextTest` ajoute onze contextes refusés (avec zéro HTTP et entrée intacte) et cinq témoins descriptifs acceptés. `PublicationEditorialTest` vérifie l'échec sans contexte, la nouvelle demande explicitement décrite sans application automatique, et le refus d'appliquer une ancienne proposition même avec autorisation de remplacement. Les tests queue/cycle de vie/concurrence reçoivent une description factuelle dans leur fixture pour continuer à tester leurs contrats temporels ; leurs assertions restent inchangées. Le corpus historique R2.1 et ses oracles restent inchangés.

Après vérification de `shortflow_test` et application des migrations, lancer `php bin/phpunit --filter 'PublicationContextTest|PublicationPackDiagnosisTest|PublicationTextsTest|PublicationEditorialTest|LocalStoryWriterTest|StoryProposalConcurrencyTest|StoryProposalLifecycleTest|StoryProposalQueueTest|WriterQualityRegressionTest'`, puis la suite complète. Résultats exacts dans [le suivi R2](r2-qualite-redacteur.md).

Recette synthétique du préparateur : 390 × 844 et 1440 × 900, focus du champ puis Tab jusqu'au bouton, saisie de faits, diagnostic complet accessible au défilement, textes personnels présents, aucune erreur console observée ni débordement horizontal. HTML produit avec services HTTP simulés, serveur GET uniquement sur loopback ; compte temporaire de `shortflow_test`, onglet et serveur retirés, viewport restauré. Aucun appel au modèle ou au fournisseur, aucune publication réelle. Les coûts et le rendu vidéo restent des validations distinctes.

Pas d'intervention manuelle requise pour R2.5. L'évaluation réelle des nouvelles consignes sur le corpus fixe reste R2.6 et la revue globale R2 reste R2.7.
