# Épisode → programmation et diagnostic du rédacteur — 24 octobre 2026

Évolution ultérieure du même jour : le lot supervisé est livré dans [recurrence-supervisee.md](recurrence-supervisee.md). Les mentions de récurrence à venir ci-dessous décrivent l’état au moment de ce lot antérieur.

## Objectif et parcours livré

Une vidéo validée dans un épisode ouvre maintenant une préparation durable liée à cet épisode. Le studio autonome utilise le même parcours. Réseaux, textes et date restent sur le préparateur existant ; la confirmation finale reste obligatoire. Le calendrier et le moteur d’envoi ne sont pas reconstruits.

1. Générer/assembler puis **Valider cette version** dans l’épisode.
2. **Préparer et programmer la publication** crée un brouillon privé sans choisir de réseau ni lancer d’envoi. Une répétition retrouve la préparation de cette version.
3. Choisir les réseaux, textes et créneau dans le préparateur habituel, puis confirmer.
4. Retrouver depuis la série l’état global, la date et le fuseau, les états distincts des destinations et un lien vers la préparation/le suivi existant. Une consultation ne fait aucun appel social.
5. Modifier ou annuler dans les écrans existants, avec leurs protections de propriétaire, CSRF, révision et programmation.

La préparation conserve la vidéo source validée au moment de sa création. Modifier ensuite la validation de l’épisode ne remplace jamais silencieusement une vidéo programmée. Une ancienne version encore programmée doit être annulée avant de préparer la nouvelle. Les anciennes préparations restent visibles comme **Version antérieure**. Supprimer un brouillon depuis son parcours existant permet d’en recréer un ; ce n’est pas une republication automatique.

Le montage d’une autre version se fait depuis l’épisode, puis cette version doit être validée. La modification de source et l’application directe d’un montage sont refusées dans une préparation liée ; les préparations ordinaires de la bibliothèque gardent leur fonctionnement. Un lien de retour à l’épisode apparaît dans le préparateur et le détail du brouillon.

Les anciens brouillons ne sont pas rattachés artificiellement à une série par le simple fait qu’ils contiennent la même vidéo. Ce lien est créé lors du nouveau parcours explicite.

## Erreur du rédacteur local

Le service masquait toutes les erreurs derrière « scénario inexploitable ». Le moteur et Qwen3 8B sont accessibles depuis Docker. Un essai fictif a rencontré le délai maximal intermédiaire de 90 s ; un second a réussi en **84,149 s** avec trois clips de 10 + 15 + 15 s. L’ancien maximum de 55 s par étape pouvait donc interrompre une rédaction encore en cours. Les anciens échecs n’ayant pas enregistré leur cause, on ne peut pas attribuer rétrospectivement chaque échec au délai.

Le délai est désormais borné à **180 s par étape**, soit au plus deux appels successifs de trois minutes. Le travail reste asynchrone ; cela n’accélère pas le modèle. Le format JSON transmis au moteur impose maintenant aussi les longueurs contrôlées côté PHP. Les contrôles de structure, durées, nombre de clips et doublons stricts restent actifs.

Les erreurs distinguent délai dépassé, moteur injoignable, modèle absent, moteur occupé, réponse interrompue et contenu non exploitable. Le job conserve ce message sûr ; les logs techniques contiennent uniquement la phase et un code de cause, sans prompts, corps du fournisseur, identifiant utilisateur ni exception complète. Aucune reprise automatique, API texte payante ou génération vidéo n’est ajoutée.

Une ancienne erreur liée à une préparation différente n’est plus réaffichée sur une nouvelle vidéo. Le résultat ou l’erreur de la demande courante reste récupérable après navigation. Les anciens enregistrements d’échec ne sont pas effacés ni relancés.

Le résultat réel de l’essai est structurellement valide mais contient encore des formulations maladroites et une chute faible. Il valide le fonctionnement technique, pas la qualité nécessaire à une publication autonome. Le choix du modèle n’a pas changé et aucun modèle n’a été téléchargé.

## Composants et Symfony

| Fichier | Rôle |
| --- | --- |
| `Entity/PublicationDraft.php` | Association nullable vers l’épisode, source réelle conservée dans les items existants |
| `migrations/Version20261024010000.php` | Colonne, clé étrangère et index additifs, sans reprise des données existantes |
| `Service/EpisodePublications.php` | Verrou commun de publication, propriétaire/révision/validation, réutilisation du brouillon et projection des états enregistrés |
| `Controller/AiEpisodeController.php` | POST avec CSRF puis redirection vers le préparateur ou le suivi existant |
| `Controller/AiSeriesController.php` et `series/_publications.html.twig` | Résumé par épisode, date et états par destination |
| `PublicationComposerController`, `PublicationDraftController` et leurs templates | Contexte et retour à l’épisode, choix de source cohérent avec la validation |
| `PublicationDrafts` et `VideoEdits` | Protection de la source d’épisode, y compris contre les appels POST directs et l’application d’un montage |
| `LocalStoryWriter` et `StoryWritingException` | Délai local, contraintes JSON et erreurs explicites avec diagnostics sans contenu privé |
| `StoryProposals` et `story_writer_controller.js` | Message d’échec sûr du worker et restauration limitée à la préparation courante |
| Tests PHP/JavaScript concernés | Isolation, CSRF, répétition, versions, calendrier, états partiels et erreurs du rédacteur |

**Flux Symfony** : route POST → contrôle d’accès et CSRF → service injecté → verrou consultatif propriétaire puis transaction Doctrine/verrou de ligne → préparation créée par `PublicationDrafts` → association à l’épisode → redirection HTTP 303. La confirmation, les dates/fuseaux, Messenger et les contrôles au départ restent ceux du calendrier existant.

La relation Doctrine relie les deux objets ; elle ne programme rien à elle seule. C’est la confirmation du service de publication qui arme le créneau et son message Messenger. La projection de la série lit les états existants, elle n’invente pas un succès à partir du seul fait que la vidéo a été générée.

## Tester

- Préparer deux fois une même version : même brouillon, aucun job social créé.
- Ouvrir une série étrangère ou falsifier le CSRF : refus, aucune préparation supplémentaire.
- Programmer un épisode, retrouver la même date dans le calendrier et la série ; désarmer/annuler depuis la préparation.
- Valider une nouvelle version pendant qu’une ancienne est programmée : ancienne source conservée, création de la nouvelle préparation bloquée jusqu’à annulation.
- Simuler YouTube publié et Instagram échoué : succès partiel et états séparés ; aucun appel social à la consultation.
- Arrêter Ollama ou simuler un modèle absent/délai dépassé : message adapté, scènes conservées. Ne pas arrêter le moteur d’une autre demande active pour tester.

Les tests automatisés utilisent exclusivement `shortflow_test` et des clients HTTP sociaux simulés. Migration appliquée localement en dev/test ; cache dev renouvelé et workers redémarrés après constat de files dev vides. Les résultats exacts sont dans [verification.md](verification.md).

## Suite

Ce lot ne crée pas encore la récurrence. Le prochain chantier est un épisode par créneau hebdomadaire, préparé en avance avec mémoire narrative et références communes, puis validation manuelle. Le mode autonome et son consentement BYOK durable viendront après validation de ce fonctionnement et de la qualité du rédacteur.
