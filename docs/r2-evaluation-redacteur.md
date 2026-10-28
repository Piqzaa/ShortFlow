# R2.6 — Évaluation réelle du rédacteur local

## État : évaluation terminée, 28 octobre 2026

Cette évaluation utilise réellement Qwen3 8B local, avec les services applicatifs après R2.5. Elle ne génère aucune image/vidéo, n'utilise aucune clé fournisseur, ne publie rien et n'active aucune série. Aucune base ni aucun compte n'est chargé par le programme. Le grand R2 reste en cours ; sa revue de clôture est R2.7.

**Résultat : 18 essais terminés, 28 appels d'inférence locaux ; 12 scénarios normalisés, quatre packs sociaux expirés et deux refus de contexte attendus avec zéro HTTP.** Les scénarios demandent une relecture et des corrections ; aucun pack éditorial n'est disponible dans cette campagne. Les réussites structurelles ne constituent pas une validation de fidélité ni de qualité vidéo. Les réponses synthétiques et les erreurs sont versionnées dans [l'archive des résultats](fixtures/redacteur-evaluation-r2-resultats.json), avec [le corpus fixe](fixtures/redacteur-evaluation-r2.json).

## Protocole figé avant les essais

- Source applicative : `e7f85b9f738f27794e0156228ddbba92e9c7295e`. Empreintes SHA-256 du corpus, du programme et des trois services contrôlés conservées dans les métadonnées. Les services ne sont pas modifiés pendant les passages.
- Corpus versionné : `docs/fixtures/redacteur-evaluation-r2.json`. Six scénarios (récit Milo, tutoriel tartine, présentation du mug, dialogue français exact, danse, mémoire balle/carton) et trois cas éditoriaux (mug, récit décrit, titre « Vacances 2026 » seul). Entrées synthétiques dérivées de l'audit historique et du cas mémoire existant, sans modifier ces sources historiques.
- Deux passages séquentiels, dans le même ordre, mêmes paramètres, sans correction ni retry entre passages. Le contrôle d'import pauvre doit demander des précisions sans inférence. La température reste celle du service (0,6), aucun seed n'est fixé : les deux réponses sont des observations de variabilité, pas un échantillon statistique représentatif.
- `scripts/evaluate-writer-local.php` appelle `LocalStoryWriter::propose()` ou `publication()`. Un client d'observation relaie seulement `POST http://host.docker.internal:11434/api/chat`, capture corps/statut/horloges, puis remet cette réponse au service via `MockResponse`. Les options, prompts, schémas et limites HTTP de l'application sont conservés. La lecture complète de la réponse dans ce relais change l'ordonnancement du streaming : ces durées ne constituent pas une mesure certifiée du transport de production.
- Conservation par essai : entrée, résultat normalisé ou erreur, réponse brute et enveloppe de chaque appel, requête exacte, durées murale (`microtime`) et monotone (`hrtime`) par appel et par essai, écart des horloges, alertes. Aucun message d'erreur fournisseur brut n'est affiché dans l'application ; les captures synthétiques appartiennent exclusivement à ce programme de diagnostic.
- Si le transport échoue avant lecture complète, seule sa classe d'erreur et les temps sont disponibles : aucun corps partiel n'est conservé. Ne pas attribuer une cause de contenu précise à cet appel. Le SHA Git est déclaré au programme ; les empreintes des fichiers sont la preuve des octets évalués.
- Les métadonnées fournissent version Ollama, état initial du modèle, PHP, commit et empreintes. Les métriques natives Ollama restent dans les enveloppes : chargement, prompt et génération ; elles ne se confondent pas avec l'attente totale du créateur. Aucun effacement de capture lors d'un échec.
- Une réponse JSON structurée est distinguée d'une proposition acceptée par l'application, puis d'une proposition fidèle à relire. La grille de revue couvre faits, identité/objets, ordre et faisabilité des actions, langue, paroles et locuteurs, mémoire, adéquation scène/prompt, hashtags, corrections humaines et limites non détectées. Aucune correction humaine n'est appliquée entre les passages : elles seront seulement proposées dans la revue.

## Conditions du poste

Matériel lu sur ce poste : Intel Core i5-9300HF, 4 cœurs / 8 threads, environ **15,92 Gio de RAM** ; NVIDIA RTX 2060, **6 144 Mio de VRAM**, pilote **617.42**. Modèle installé `qwen3:8b`, quantification **Q4_K_M**. Aucun autre modèle n'est installé ou choisi pour ce lot.

Docker/PHP et Ollama sont déjà installés. Les services locaux restent démarrés ; aucune suite PHP/FFmpeg ou autre inférence n'est lancée en parallèle par l'agent. Quelques lectures matérielles et la revue des fichiers ont lieu au début du premier essai. Le premier passage peut inclure le chargement du modèle ; le second réutilise son état. Le worker de rédaction dev a zéro demande active/en attente au démarrage. Aucun réglage de veille, d'alimentation, de mémoire ou de GPU n'est modifié. Une suspension ou une forte divergence des horloges doit être signalée, jamais interprétée comme un débit d'inférence normal.

Ollama observé : **0.40.11**. À vide avant le premier cas, aucun modèle n'est chargé. Une lecture pendant le passage rapporte Qwen3 avec un contexte de **4 096 tokens**, taille **5 986 561 554 octets**, dont **4 206 582 824 octets en VRAM**. Ce partage mémoire n'est pas une mesure de charge GPU ni de débit.

Les essais ont tourné dans un conteneur CLI temporaire, sans worker à renouvellement horaire, sans port exposé, retiré automatiquement en fin de programme. Du **28 octobre à 01:59:27 UTC au 02:25:47 UTC**, soit **02:59:27–03:25:47 à Paris**, environ **26 min 20 s**. Sorties locales : `artifacts/writer-evaluation-r2/20261028-015927-f9aefb/` ; journal `artifacts/r2-6-evaluation.log`. Les preuves nécessaires à la reprise sont dans l'archive versionnée : métadonnées, état chargé, 18 entrées/sorties et toutes les réponses complètes capturées. L'archive conserve les chaînes ISO et les corps HTTP exacts ; aucune capture n'est remplacée par une correction humaine.

## Mesures et résultats techniques

Secondes, **murale / monotone**. Chaque scénario utilise deux appels ; chaque pack un appel ; chaque refus de contexte zéro appel. Les quatre timeouts n'ont ni résultat ni corps complet capturé : leur cause de contenu reste inconnue. Le validateur de hashtags n'a pas reçu de pack à accepter ou refuser.

| Cas | Passage 1 | Passage 2 | Résultat des deux passages |
| --- | ---: | ---: | --- |
| Récit Milo | 136,405 / 136,536 | 103,263 / 103,231 | Scénarios normalisés, deux alertes d'identité chacun |
| Tutoriel tartine | 45,494 / 45,457 | 65,318 / 65,315 | Scénarios normalisés, aucune alerte |
| Présentation produit | 43,995 / 43,977 | 42,652 / 42,634 | Scénarios normalisés, aucune alerte |
| Dialogue français | 42,897 / 42,855 | 61,957 / 61,918 | Scénarios normalisés, une alerte paroles chacun |
| Danse | 54,892 / 54,875 | 107,102 / 107,058 | Scénarios normalisés, aucune alerte |
| Mémoire de série | 54,267 / 54,236 | 100,544 / 100,527 | Scénarios normalisés, aucune alerte |
| Pack produit, trois réseaux | 180,269 / 180,128 | 180,097 / 180,010 | Délai dépassé, aucun pack |
| Pack récit, trois réseaux | 180,109 / 180,002 | 180,130 / 180,069 | Délai dépassé, aucun pack |
| Import titre seul | 0,001 / 0,001 | 0,003 / 0,003 | Précisions demandées avant HTTP, aucune invention produite |

Les douze scénarios vont d'environ **42,65 à 136,40 s murales** ; médiane **murale 58,424257 s**, médiane **monotone 58,396488 s**. Le premier appel rapporte **33,0442357 s de chargement Ollama**. Ne pas soustraire simplement ce chargement pour annoncer un débit chaud : longueur des réponses, cache, ordre et température varient. Écart maximal absolu des horloges par essai : **0,140863 s** ; aucun grand écart enregistré, sans certification de calibration ou d'absence de suspension. Les mesures natives sont disponibles pour les 24 appels terminés, pas pour les quatre expirés.

Le programme a terminé avec le **code 1**, `technicalFailures=true`, à cause des quatre demandes éditoriales non abouties ; les deux refus de contexte sont attendus. Ce bilan complet ne doit pas être annoncé comme une campagne entièrement réussie. Les deux passages ne permettent aucun taux de fiabilité général, promesse de rapidité, comparaison statistique avec l'audit ancien ou score commercial.

## Relecture des scénarios

Lecture indépendante des douze scénarios, confrontée à celle de l'agent principal. Les corrections ci-dessous sont proposées, jamais appliquées aux sorties ou aux consignes entre passages. Les quatre packs expirés ne permettent aucune relecture de contenu ; les deux refus d'import ont été contrôlés explicitement (raison `context`, zéro appel, aucune proposition).

| Cas | Ce qui est conservé | Écart et correction humaine proposée, non appliquée |
| --- | --- | --- |
| Milo 1 | Chat blanc et exploration ; collier et yeux ajoutés signalés par deux alertes | « Griffer avec le museau » et porter/secouer le bol avec les pattes sont peu plausibles et non alertés. Remplacer par renifler le livre et toucher le bol posé au sol. |
| Tutoriel 1 | Mains seules, beurre puis confiture, résultat final | Coupe préalable ajoutée, beurre et confiture regroupés dans un clip de 5 s. Répartir beurre, confiture, résultat ; supprimer la coupe non demandée. |
| Produit 1 | Mug blanc sans logo, table, forme → texture → anse, sans personne | « Froide » n'est pas démontrable par l'image et « ergonomique » n'est pas établi. Remplacer par observations de surface, reflets et forme. Aucune alerte. |
| Dialogue 1 | Deux citations exactes, locuteurs et ordre dans les plans correspondants ; alerte paroles présente | Préfixe commun « questions/answers » jusque dans le plan final silencieux : contradiction non isolée par le contrôle. Limiter ce préfixe à l'identité et nommer explicitement la langue française dans chaque plan parlé. |
| Danse 1 | Même chat/salon et séquence demandée | Pose finale interprétée avec les pattes arrière levées, sans appuis définis. Clarifier l'intention ambiguë en chat assis, pattes avant levées ; retirer les actions du préfixe identitaire. Aucune alerte. |
| Mémoire 1 | Balle/carton évités ; chat blanc sans accessoire, nouveau sujet poubelle | Poubelle ouverte au premier plan puis recherche d'une ouverture. Remplacer par observation depuis le rebord ; sortie/posture détendue plutôt qu'un ronronnement présenté comme résolution visuelle. Aucune alerte. |
| Milo 2 | Chat blanc conservé ; deux alertes justes pour collier et yeux inventés | Renversement de chaise sans taille/poids précisés, accès à la tasse omis, fin chargée. Supprimer les traits ajoutés et simplifier/expliciter les actions et déplacements. |
| Tutoriel 2 | Mains seules, beurre avant confiture et résultat terminé | Coupe du pain encore ajoutée ; confiture et déplacement sur assiette regroupés à la fin. Supprimer la coupe, montrer les étapes sur pain déjà posé et garder le résultat visible. Aucune alerte. |
| Produit 2 | Sujet, ordre et calme conservés | « Soft », ergonomie et confort ajoutés sans source. Décrire uniquement surface, couleur, courbe et ouverture de l'anse. Aucune alerte. |
| Dialogue 2 | Citations, locuteurs et ordre exacts, « in French » explicite ; préfixe statique, alerte paroles | « Tabby » ajoute un motif de pelage absent du chat roux demandé (également au passage 1). Garder « ginger cat ». Voix/lipsync toujours non validés. |
| Danse 2 | Pose moins incohérente qu'au premier passage et séquence conservée | Pattes/appuis encore ambigus, salon présent seulement dans le résumé et absent de tous les prompts. Ancrer le salon et les appuis ; cadrer les pattes. Aucune alerte. |
| Mémoire 2 | Balle/carton évités ; chat blanc sans accessoire, nouveau sujet oreiller | « Renverse » devient « sending it flying backward » dans le prompt. Le préfixe « placed on the floor » brouille l'état pendant la retombée ; nouvelle chute sans prise/soulèvement explicite. Décrire une interaction simple au sol et l'état cohérent de chaque plan. Aucune alerte. |

Ces douze propositions sont techniquement normalisées et leurs directions sont en anglais hors citations intentionnelles. Chacune reçoit au moins une correction humaine proposée dans cette grille stricte ; cela n'est pas un taux de défaut généralisable. Les deux essais de dialogue conservent les citations exactes et leurs locuteurs dans les plans correspondants, avec une alerte de capacité ; cela n'établit pas les voix. Aucune danse n'a été rendue ou synchronisée à de la musique. Les écarts seront portés à la revue globale R2.7 ; le moteur reste figé pendant ce lot de mesure.

### Points à résoudre ou limiter à la clôture R2.7

1. Résultats éditoriaux indisponibles aux deux passages : quatre timeouts sans corps de réponse. La raison de contenu est inconnue ; ne pas retirer le validateur des hashtags, augmenter arbitrairement le délai ou attribuer ces délais à un format précis sans preuve. Garder la saisie manuelle disponible.
2. Préfixes d'identité contenant des actions ou un état de position : ils peuvent contredire le plan courant. Limiter les préfixes à l'identité n'est pas encore garanti par le service.
3. Plausibilité et causalité : bol transporté avec les pattes, appuis de danse, objets qui retombent sans être soulevés ; contrôles automatiques incomplets. Une liste d'alertes vide ne valide pas le scénario.
4. Propriétés et détails ajoutés : collier/yeux bien signalés dans ces exemples, mais ergonomie/confort ou motif de pelage ne le sont pas. La relecture humaine reste nécessaire, y compris avant un coût vidéo.
5. Le maintien de deux exclusions de mémoire n'est pas une validation de mémoire générale. L'audio, les raccords visuels, le rythme et la qualité vidéo nécessitent des essais distincts autorisés.

R2.6 publie une mesure et ses limites ; il ne constitue pas une autorisation de génération autonome. R2.7 doit confronter ces écarts à la porte de sortie de tout R2 et consigner les corrections, limitations et décisions requises avant de passer à R3.

## Revues, validations et rôle des composants

- Revue indépendante du protocole et de la métrologie : 18 essais, 28 appels, quatre timeouts sans corps, deux refus sans HTTP, médianes et chargement confirmés. Les cinq empreintes (programme, corpus, trois services) correspondent aux octets évalués ; aucune source applicative n'a changé pendant la campagne.
- Relecture indépendante des douze scénarios et des six contrôles éditoriaux : écarts et corrections consignés ci-dessus, sans modifier les captures. Cette revue ciblée n'est pas la clôture globale R2.7.
- Le nouveau programme a été exécuté intégralement ; syntaxe `php -l`, CS-Fixer en mode vérification et `--dry-run` réussis. Cardinalité, répétitions, égalité des entrées avec le corpus, commit et empreintes contrôlés avant archivage ; JSON valide. Liens et `git diff --check` vérifiés avant commit.
- Aucun fichier d'application, configuration, template, migration, test ou asset modifié. La dernière suite applicative **758 tests / 8 619 assertions** et les **152 frontend** restent les résultats R2.5, non rejoués ici. Aucun nouveau build ou contrôle visuel nécessaire pour ce programme sans interface. Les tests R2.5 prouvent séparément la conservation DB des textes ; cette campagne CLI sans DB ne la démontre pas.

Le corpus fixe fournit les entrées ; le programme appelle les services Symfony métier existants sans démarrer le Kernel ; les services valident les sorties ; les captures permettent de distinguer ce que le modèle a répondu de ce que l'application accepte ; la relecture humaine juge les écarts de sens. Cette séparation explique pourquoi des tests unitaires verts ne certifient pas un scénario prêt à générer. Aucun contrôleur, message Messenger, entité ou service fournisseur n'est créé par cette évaluation.

## Rejouer

Vérifier que Qwen3 8B local est disponible et qu'aucune rédaction réelle n'est active. Depuis une source propre dont le SHA est lu par `git rev-parse HEAD` : `php scripts/evaluate-writer-local.php --commit=<SHA complet> --dry-run` valide la sélection sans appel ; sans `--dry-run`, deux passages sont exécutés et chaque essai est conservé. Options `--case=<identifiant>` et `--repeat=1` ou `--repeat=2` pour une reprise explicitement documentée ; ne pas les présenter comme une série complète.

Le code de sortie est 1 s'il existe un échec technique inattendu, 0 sinon ; ce code ne certifie jamais la qualité éditoriale ni, à lui seul, le bon refus de contexte. Ce dernier doit être vérifié dans les captures, comme fait pour les deux passages présents. Les erreurs, refus et sorties différentes sont conservés ; ne pas sélectionner seulement un passage favorable pour le bilan.

**Prochain point exact : R2.7**, revue de clôture du grand R2 avec ces limites et les correctifs/limitations nécessaires avant validation de sa porte de sortie. Aucun test vidéo payant, publication, activation ou changement de modèle n'est autorisé par cette mesure. Commit local `test: evaluate local writer on repeated corpus`, aucun push.
