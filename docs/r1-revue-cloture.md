# R1 — Revue globale de clôture

27 octobre 2026. Cette revue applique la règle demandée par le propriétaire : examiner **tout le grand lot R** avant de commencer le suivant. Elle complète les revues de sous-tâches ; elle ne transforme pas les tests simulés en validation de production.

**Décision de sortie : R1 terminé, passage à R2.1 permis.** Aucun blocage restant identifié dans le périmètre R1 après relectures et contrôles finaux. R2 n'est pas commencé. Le commit de clôture est retrouvable avec `git log --oneline --grep="docs: close R1 writing reliability review"` ; aucun push.

## Périmètre et résultat attendu

Le problème initial était une demande encore en file déclarée expirée après dix minutes avant même de démarrer. R1 devait distinguer attente/exécution, borner les travaux bloqués, afficher des états fidèles, permettre de retrouver la demande et résister aux traitements concurrents ou interrompus, sans perdre les préparations ni lancer de vidéo.

Commits relus : `b4f4311` (reproduction), `c356b2b` (délais/admission), `826cfae` (interface/reprise) et `818261c` (concurrence/interruption). La revue de clôture est un commit documentaire distinct. Les preuves et commandes détaillées restent dans [le suivi R1](r1-redacteur-attente.md).

## Confrontation à toutes les tâches

| Tâche | Résultat et preuve examinée | Verdict |
| --- | --- | --- |
| R1.1 | Douze cas 0/11 minutes, scénario/éditorial, lecture/dernier/traitement ; contrat conservé après correction | Satisfait |
| R1.2 | Attente 1 h, phase 210 s et total 420 s distincts ; bornes testées, dates historiques, admission bornée, résultats tardifs rejetés | Satisfait dans le périmètre manuel local |
| R1.3 | Étapes nommées sans pourcentage fictif, dates serveur, récupération GET et aide à la sauvegarde ; empreinte/ambiance/saisies protégées | Satisfait ; recette desktop/mobile documentée |
| R1.4 | Dernière place globale, requêtes simultanées, double claim, séparation des propriétaires, ancien worker et six points d'interruption/redélivrance | Satisfait en PostgreSQL/processus locaux |
| R1.5 | Deux relectures indépendantes backend et interface, contrôles de régression, limites, guide Symfony, repères et commit documentaire | Satisfait |

Les contrôles de propriétaire, type et périmètre des routes privées restent présents. Les réponses de suivi ne sont pas mises en cache et ne créent pas une nouvelle demande. Le résultat est à prévisualiser/appliquer ; un formulaire éditorial modifié n'est pas rechargé. Coûts, confirmations vidéo, validation avant publication et préparations existantes ne sont pas modifiés par R1.

## Relectures indépendantes

- **Backend/concurrence** : service, entité, migration, mutex texte, routes privées et tests de file/cycle/processus ; pas de blocage applicatif restant identifié. Les barrières PostgreSQL attendent des connexions réellement distinctes. Les arrêts SIGKILL concernent les enfants synthétiques, pas les workers du développeur.
- **Interface/reprise** : étapes, erreurs, annonces accessibles, horloge d'affichage, récupération GET, empreintes et protection des saisies ; pas de blocage identifié. Aucun nouveau frontend depuis la recette R1.3 ; les nouveaux tests R1.4 ne changent pas les vues.

Les agents ont relu les sources et preuves, sans exécuter de suite DB en parallèle. Les contrôles réellement exécutés par l'agent principal et leurs résultats sont consignés dans le suivi et la roadmap ; aucune ancienne suite n'est présentée comme un nouveau passage.

## Contrôles finaux exécutés

| Contrôle | Résultat du 27 octobre |
| --- | --- |
| Base/migrations avant les suites | `shortflow_test` vérifiée ; `Version20261027160000` à jour |
| Tests concurrents finaux et deux répétitions indépendantes | **14 tests / 236 assertions** à chaque passage, tous réussis |
| Suite PHP complète, incluant à nouveau les courses | **667 tests / 8 044 assertions**, tous réussis |
| Suite frontend | **152 tests**, tous réussis, aucun ignoré |
| PHPStan niveau 5 sur `src`, puis les deux fichiers de tests R1.4 | Aucune erreur |
| CS-Fixer final sur les nouveaux fichiers | Aucun fichier à corriger |
| Conteneur Symfony, 19 YAML, 102 Twig et schéma Doctrine test | Réussis, schéma synchronisé |
| `npm run check` et `npm run build` | Réussis ; avertissement Vite existant sur la taille du chunk Three.js |
| Liens locaux des documents R1/roadmap et `git diff --check` | Réussis |

Sorties locales ignorées : `artifacts/r1-4-focused-final.log`, `r1-4-full.log`, `r1-final-ui.log`, `r1-final-phpstan.log`, `r1-4-phpstan-tests.log`, `r1-final-style.log`, `r1-final-check.log`, `r1-final-build.log`. Les preuves, commandes et tests versionnés permettent la reprise sans ces journaux. Aucune nouvelle recette navigateur en R1.4/R1.5 : les sources d'interface n'ont pas changé depuis la recette R1.3 1440 × 900 / 390 × 844 décrite dans le suivi ; la suite frontend a été rejouée.

## Écarts expliqués et limites conservées

Le cas à deux propriétaires a d'abord attendu deux inférences simultanées. La lecture du code et le test ont confirmé le verrou `flock` déjà prévu : dans un même environnement temporaire, une seconde rédaction échoue avec « occupé ». Le test final vérifie ce contrat et la nouvelle demande volontaire réussie après libération. Aucune protection n'a été retirée pour obtenir un résultat vert.

La reprise après arrêt est conservatrice : avant claim et avant la limite de file, le traitement peut démarrer ; pendant une exécution, la redélivrance survient après son délai et constate l'expiration ; après résultat prêt mais avant acquittement, elle conserve le résultat et acquitte sans répéter les appels. Le test accélère la redélivrance en antidatant seulement le message synthétique. Une nouvelle rédaction après expiration exige une nouvelle demande humaine.

Ce lot ne prouve pas :

- le débit réel d'Ollama ou une capacité commerciale ; l'admission ne compte que les propositions manuelles encore valides, sans borner les récurrences directes ou les messages physiques expirés ;
- un verrou distribué entre plusieurs machines/conteneurs : le mutex local dépend du TMPDIR partagé ;
- toute l'exploitation de `messenger:consume` et de Docker : le vrai Worker et le transport sont utilisés avec un dispatcher de test minimal ;
- la qualité narrative, les répliques ou le rendu audiovisuel, traités en R2 puis R7 ;
- la qualité responsive de toute l'application : la recette R1.3 concernait les interfaces de rédaction, et la landing signalée par le propriétaire reste explicitement en R5/R6.

Les points de capacité/topologie vont en **R8.2**, la surveillance et les procédures d'interruption en **R8.6**, et la charge de la cible en **R8.8**. Ces limites n'empêchent pas la correction locale R1 ; elles empêchent d'en déduire une disponibilité ou un débit de production.

## Pédagogie et reprise

L'entité porte l'état durable et ses dates ; le service décide des transitions sous verrous courts ; Messenger transporte une demande pouvant être reçue plusieurs fois ; le rédacteur réalise les appels hors transaction ; les contrôleurs publient uniquement l'état autorisé ; Stimulus le présente et protège les modifications de l'utilisateur. Un acquittement de message et un résultat métier sont deux événements distincts, d'où les tests d'arrêt entre les deux.

Pour un autre PC, suivre le README et la roadmap : migration additive R1.2, cache/worker hors traitement actif et assets construits. Les fixtures versionnées suffisent aux tests ; aucun crédit Fal n'est requis. Aucun essai payant, compte social, récurrence réelle, déploiement ou push n'a été effectué pour cette clôture.

**Prochain chantier : R2.1**, transformer les écarts connus de qualité en cas de non-régression. La nouvelle règle de revue globale s'appliquera à la fin de R2, puis à chaque grand lot suivant. Une intervention du propriétaire sera demandée au moment où une étape l'exige : téléphone réel, jugement de compréhension/qualité, accès externes ou essai payant explicitement autorisé.
