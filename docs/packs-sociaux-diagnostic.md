# R2.4 — Packs sociaux et diagnostic

Le rédacteur doit fournir tout le pack demandé avant de proposer son application : titre et description YouTube, légendes Instagram/TikTok, avec un tableau de zéro à cinq hashtags par réseau. Un champ manquant ou invalide fait refuser le pack entier. Les titres restent sans hashtags ; les tags validés rejoignent uniquement la description ou la légende.

## Fonctionnement et fichiers

1. `PublicationEditorial` conserve une photographie de la source, des réseaux, du profil et des textes existants ; `StoryProposals` place la demande dans Messenger. Ces protections ne changent pas.
2. `LocalStoryWriter::publication()` transmet explicitement les réseaux et rappelle tous les champs, leurs limites et la séparation des hashtags, en complément du schéma JSON strict. Un seul appel local, toujours borné à 180 secondes ; aucun second essai automatique.
3. `PublicationTexts` vérifie le pack complet : réseaux et champs exacts, textes non vides et bornés, caractères interdits, listes de tags autorisés/exclus. Aucun retrait silencieux de hashtags incorporés, aucun résultat partiel ni troncature.
4. `PublicationException` conserve les arguments habituels de RuntimeException et ajoute une raison technique contrôlée. `LocalStoryWriter` journalise seulement cette raison, sans réponse brute, source, message fournisseur ni chaîne d'exception externe. Il distingue notamment réseaux, structure, texte, longueur, hashtags, profil, moteur indisponible, modèle absent, délai, réponse interrompue et JSON illisible. Un format inexploitable non identifié garde un diagnostic général : aucune cause n'est inventée.
5. `StoryProposals` enregistre le message français dans l'état d'échec. Le préparateur existant l'affiche avec une action : vérifier le moteur, demander une nouvelle proposition ou écrire directement. Réouvrir le brouillon ou relivrer le message Messenger ne relance pas l'appel. Une nouvelle tentative est une nouvelle demande explicite, soumise aux limites existantes.
6. Un résultat valide reste une proposition. Seuls les champs choisis sont appliqués, avec protection des éditions faites pendant l'attente. Aucune publication ni génération vidéo n'est déclenchée.

En Symfony, une exception métier sert ici à ramener un refus jusqu'au worker, puis à l'état durable consulté par le navigateur. Elle ne remplace ni la validation serveur ni les autorisations. Le code du contrôleur, les templates, Stimulus et le schéma de base restent inchangés ; les nouvelles indications utilisent leur affichage existant.

## Preuves et limites

`PublicationPackDiagnosisTest` couvre les refus distincts, la non-exposition des contenus externes dans le message/logger/chaîne, le pack complet trois réseaux, le délai et le verrou occupé (aucun HTTP, verrou tiers conservé). `PublicationEditorialTest` ajoute le parcours simulé refus → réouverture du brouillon et diagnostic HTML → nouvelle demande manuelle réussie, sans modifier les textes ni programmer la publication.

Le diagnostic brut de l'audit reste refusé pour ses hashtags incorporés. Les deux autres échecs éditoriaux historiques n'ont pas de réponse brute enregistrée : leur cause exacte reste inconnue. Les consignes nouvelles ne constituent pas encore une preuve de qualité sur Qwen réel ; cette évaluation reste en R2.6. Le contexte pauvre, encore rouge, appartient à R2.5. Aucune préparation ancienne n'est invalidée ou réécrite ; la version de normalisation reste inchangée, les règles d'acceptation étant conservées.

Pour tester, vérifier `shortflow_test`, appliquer ses migrations puis exécuter `php bin/phpunit --filter 'PublicationPackDiagnosisTest|PublicationTextsTest|PublicationEditorialTest|LocalStoryWriterTest'`. La suite complète reste volontairement rouge sur la reproduction R2.5, sans exclusion. Les résultats exacts et le prochain point sont dans [le suivi R2](r2-qualite-redacteur.md).

Aucune intervention manuelle ni crédit fournisseur nécessaire pour ce lot. Pas de nouvelle recette navigateur desktop/mobile : aucun style/template/contrôleur frontend modifié ; le parcours HTML est vérifié en fonctionnel et les tests frontend sont rejoués. La recette visuelle R2.2 reste une preuve historique distincte.
