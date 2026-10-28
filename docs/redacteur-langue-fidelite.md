# R2.3 — Langue et fidélité du rédacteur local

28 octobre 2026. Cette étape corrige les consignes et les garde-fous textuels du scénario ; elle ne valide pas la voix produite par un modèle vidéo. Le [contrat R2.2](scenes-parlantes-contrat.md) reste partiellement porté : aucun script séparé avec locuteurs/langues n'est envoyé au fournisseur vidéo.

## Objectif et comportement

Les deux consignes du rédacteur n'interdisent plus les répliques explicitement demandées. La première demande une narration française avec citations originales ; la seconde des directions anglaises avec paroles citées verbatim dans leur langue, leur locuteur et leur plan. La liste des répliques identifiables est fournie au modèle texte comme aide à la rédaction, sans remplacer le brief complet. Les répétitions demandées ne sont pas dédupliquées.

Une réponse peut encore être mauvaise : les contrôles signalent les pertes et incertitudes pour la relecture, sans réécrire les scènes de l'utilisateur ni ajouter automatiquement une phrase dans un plan arbitraire. Les nouveaux contrôles s'appliquent aux propositions nouvellement rédigées et aux appels explicites de relecture hors ligne.

### Citations et langue

- Pour le contrôle de langue, seules les citations **fermées et exactement fournies** dans l'idée ou le contexte accepté sont mises à part. Les guillemets français, doubles droits et doubles typographiques sont reconnus.
- Une citation inconnue, modifiée ou non fermée reste analysée. Mettre un prompt français entre guillemets ne suffit pas à le déclarer anglais.
- Les mots et leur ponctuation interne sont comparés exactement ; les espaces typographiques extérieurs au contenu cité sont ignorés pour la comparaison. Cette opération ne modifie pas les textes retournés.
- Les directions françaises sont signalées à partir d'indices plus larges que l'ancienne liste. Si la langue de consignes suffisamment longues n'est pas identifiable, ou s'il ne reste que des citations sans consignes, une alerte d'incertitude apparaît.
- Les citations anglaises demandées ne font pas, à elles seules, déclarer anglais le résumé français qui les contient.

Ce contrôle reste heuristique, pas un détecteur universel de langue. Une liste vide d'alertes ne certifie ni traduction ni fidélité.

### Répliques et attribution

Une réplique doit être présente en entier dans les citations d'un prompt de plan. La présence dans le résumé ou la description ne suffit pas. Les fragments répartis sur plusieurs plans ne sont pas recollés pour faire passer le contrôle.

Les occurrences exactes sont comptées : absence, modification, répétition excédentaire ou ordre apparemment inversé sont signalés. Une même phrase demandée deux fois reste demandée deux fois. Une parole introduite dans un prompt sans correspondance aux répliques fournies est signalée. La détection de demande de parole repose sur des indices explicites ; si elle ne retrouve pas de texte cité exploitable, elle demande une précision/relecture plutôt que d'inventer le texte exact attendu.

**La présence textuelle ne prouve pas le bon locuteur.** Chaque demande de parole identifiée reçoit aussi une alerte de relecture sur mots, locuteurs, ordre, langue et capacité audio. Deux locuteurs inversés ne sont pas automatiquement certifiés corrects parce que leurs phrases sont présentes. Les systèmes de voix, TTS et lipsync restent hors de cette étape.

### Identité et actions

Les contrôles existants d'accessoires, couleurs d'yeux et répétition de résumé sont conservés. Un contrôle supplémentaire compare certaines tenues/couleurs explicitement attribuées à des personnages nommés, en français et en anglais. Demander une veste bleue pour Léa ne valide pas automatiquement une veste bleue portée par Hugo. Il s'agit de formes simples reconnues, pas d'une extraction exhaustive des personnes et attributs.

Les consignes renforcent l'opérateur concret des gestes manuels, la causalité et la continuité. Une alerte ciblée détecte certaines demandes simultanées incompatibles, comme rester complètement immobile tout en marchant. Une marche suivie d'un arrêt, une caméra fixe filmant un sujet mobile ou deux sujets différents ne sont pas traités comme cette contradiction. Une fantaisie explicitement demandée est respectée ; une fantaisie niée ou seulement citée dans des paroles ne désactive pas ce contrôle.

Les autres gestes et raccords restent à relire : le code ne peut pas prouver la physique d'une histoire. Il ne répare pas silencieusement une sortie en ajoutant une action ou un trait.

## Fichiers, responsabilités et flux Symfony

| Fichier | Rôle |
| --- | --- |
| `src/Service/StoryTextChecks.php` | Règles pures de citations/langue, répliques, tenue nommée et contradictions ciblées ; aucun HTTP ni accès DB |
| `src/Service/LocalStoryWriter.php` | Consignes corrigées, aide `requested_lines` au modèle texte, appel des nouveaux contrôles puis conservation des contrôles existants |
| `src/Service/SeriesRecurrences.php` | Garde de pause existante maintenue ; résumé d'alerte complet, compté et borné à 255 caractères plutôt qu'une liste coupée au milieu |
| `tests/Unit/StoryTextChecksTest.php` | Cas adverses et témoins, copie exacte entre les deux appels simulés et indépendance des constats sur le dialogue historique |
| `tests/Functional/SeriesRecurrencesTest.php` | Demande de parole mise en pause avec brouillon éditable, aucune génération créée ou requête vidéo partie, redélivrance sans doublon |

Flux : demande privée → worker existant → deux appels texte locaux → contrôles purs → proposition avec alertes dans l'aperçu existant. L'utilisateur garde son choix d'application. En récurrence, une alerte suspend le flux avant le fournisseur vidéo, conserve les plans modifiables et libère les réservations non consommées. Aucune récurrence réelle n'a été activée pendant les vérifications.

Le service pur est utilisé par composition depuis le rédacteur, sans nouvelle dépendance externe ni modification de sa signature publique. Les routes, contrôles propriétaire, confirmations de coûts, tarifs, modèles, références et schémas ne changent pas.

## Témoins et preuves

Le corpus R2.1 et ses assertions restent inchangés. Les trois reproductions scénario doivent maintenant être vertes : français non signalé, paroles perdues et faux positif de citation française. Le cas d'invention depuis un titre pauvre reste rouge jusqu'à R2.5 ; les textes sociaux et leur normalisation ne sont pas modifiés en R2.3.

Les nouveaux témoins couvrent notamment : trois styles de guillemets, citation inconnue/modifiée/non fermée, consignes françaises hors citation, prompt composé seulement d'une citation, langue indéterminée, phrases fragmentées ou répétées, ordre inversé, locuteurs non certifiés, refus explicite de dialogue, parole sans réplique, anglais cité dans une narration française, tenues permutées, immobilité/déplacement, séquence normale, caméra fixe et fantaisie positive/négative/citée.

Les résultats exacts et commandes exécutées sont consignés dans [le suivi R2](r2-qualite-redacteur.md). Tous les tests de modèle utilisent des réponses synthétiques ou des captures archivées, sans appel Ollama réel ni vidéo payante. Les tests fonctionnels sont gardés sur `shortflow_test`, migrée avant PHPUnit.

## Limites de livraison

- Aucune évaluation réelle répétée du nouveau prompt Qwen n'est prétendue : elle reste en R2.6. Les tests prouvent le traitement des fixtures, pas la sortie d'une future inférence.
- Aucune voix audible, identité vocale ou synchronisation réelle n'est validée : essais autorisés distincts en R7.
- Les propositions anciennes déjà terminées conservent leur résultat et leurs alertes enregistrées ; elles ne sont pas réévaluées ou réécrites automatiquement. Une nouvelle proposition applique les nouveaux contrôles ; la note R2.2 continue à informer sur les limites.
- Les formes de citation non reconnues, formulations indirectes, attributions complexes et contradictions sémantiques générales demandent une relecture. Le code ne se présente pas comme une validation complète de l'histoire.
- Les alertes complètes restent dans les nouvelles propositions ; le suivi récurrent affiche un résumé compté et une indication complète qui tient dans ses champs existants. Il ne prétend pas restituer toutes les alertes dans 255 caractères.

Pour comprendre Symfony ici : les décisions métier restent dans les services, les contrôles purs se testent sans kernel, le writer garde l'HTTP local et les erreurs sûres, et la garde récurrente prend la décision de pause avant toute tâche payante. Le changement de consignes requiert le rafraîchissement du worker de rédaction hors traitement actif ; aucune migration n'est nécessaire.
