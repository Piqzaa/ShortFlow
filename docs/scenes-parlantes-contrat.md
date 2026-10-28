# R2.2 — Contrat des scènes parlantes

28 octobre 2026. Ce document définit le comportement nécessaire pour parler de **répliques exactes prises en charge**. Le parcours actuel ne porte pas encore ce contrat de bout en bout. R2.2 l'explicite et affiche cette limite avant les décisions de coût ; il n'ajoute ni champs de dialogue, ni synthèse vocale, ni lipsync, ni nouveau fournisseur.

## Ce qui existe réellement

Les préparations conservent une idée, des descriptions françaises et des prompts vidéo. `AiShotPrompts` construit le prompt envoyé. Lors du cadrage R2.2, les consignes de `LocalStoryWriter` excluaient les dialogues ; le cas capturé R2.1 prouve qu'une phrase présente dans la description peut manquer du prompt effectif. **Depuis R2.3**, [les consignes et contrôles textuels sont corrigés](redacteur-langue-fidelite.md) : citations conservées lorsqu'elles sont présentes, pertes/écarts signalés et relecture des locuteurs toujours requise. Cela ne constitue pas le portage complet du script structuré ni une validation audio.

Il n'existe actuellement aucun script structuré de répliques transmis séparément au fournisseur. Le booléen `audio` du catalogue ne décrit pas un contrat de paroles exactes, de voix stable ou de synchronisation des lèvres. Ce constat porte sur **le chemin intégré à VydFlow**, pas sur toutes les possibilités commerciales des modèles ou de leurs interfaces externes.

## Données minimales à porter pour une prise en charge contrôlée

Chaque réplique doit être liée au plan concerné et conserver son ordre. Le contrat comprend au minimum :

| Élément | Règle |
| --- | --- |
| Plan | La réplique appartient à une scène précise ; réordonner des plans déplace leurs répliques avec eux |
| Locuteur | Identité explicite et stable, liée au personnage nommé quand il existe ; aucun locuteur inventé ou choisi silencieusement si ambigu |
| Texte exact | Chaîne originale conservée : mots, accents, négations et ponctuation ; aucune paraphrase, traduction, troncature ou substitution silencieuse |
| Langue | Langue de parole explicite, distincte de la langue des consignes vidéo ; conserver le choix de l'utilisateur, ne pas l'inférer de la langue du prompt technique |
| Ordre | Ordre explicite des répliques dans le plan ; ne pas fusionner les paroles de deux personnages |
| Capacité du chemin | Vérifier fournisseur, modèle/version, endpoint, accès choisi et réglages audio ; une capacité mentionnée ailleurs n'est pas une preuve de sa transmission dans VydFlow |

La représentation de référence peut être lue comme « plan 1 → Léa → `Tu as trouvé mon chat ?` → français ; plan 2 → Hugo → `Oui, il dort sur ta chaise !` → français ». Ce n'est pas une nouvelle API ou un nouveau schéma de base déployé par cette étape. Le stockage/transport effectif, la validation et les alertes seront reliés aux préparations existantes lors des corrections suivantes, sans perte des anciens textes.

Les consignes techniques peuvent être en anglais et contenir une réplique française exacte, clairement distinguée comme parole. L'alerte de langue ne doit pas considérer cette citation intentionnelle comme un prompt technique entièrement français. Inversement, conserver la citation dans le résumé ne prouve pas qu'elle est transmise au chemin vidéo. Un contrôle doit comparer ce qui a été demandé à ce qui sera envoyé et indiquer ce qu'il ne sait pas vérifier.

## Avant une dépense

Pour annoncer ce contrat pris en charge, le récapitulatif devra montrer les répliques et leurs locuteurs/langues, en plus des prompts effectifs et du coût. Toute perte, ambiguïté, incompatibilité ou modification après le devis doit être visible avant confirmation. Un texte trop long ou une durée insuffisante ne doit pas provoquer une coupe silencieuse. La modification d'un élément du script devra invalider l'ancienne validation/devis comme les changements de scène existants.

Ce portage n'est **pas réalisé en R2.2**. En attendant, une note toujours visible indique que les répliques exactes ne sont pas encore prises en charge, que la proposition peut les oublier, et que l'audio ne garantit ni mots, ni voix commune entre plans, ni synchronisation des lèvres. Aucun nouveau consentement ou contrôle d'accès n'est ajouté : la confirmation du coût existante reste obligatoire, et aucun prompt manuel n'est supprimé ou bloqué par cette notice.

| Situation actuelle | Information donnée |
| --- | --- |
| Modèle sans audio | Les clips ne produiront pas de paroles audibles ; une bande sonore ajoutée séparément au montage reste un autre traitement |
| Modèle avec audio | Audio possible selon le modèle, sans prise en charge vérifiée des répliques exactes dans VydFlow |
| Son des clips coupé au montage | La voix éventuelle de ces clips ne sera pas conservée ; cela ne réduit pas le coût fournisseur et ne dit rien d'une piste ajoutée séparément |
| Raccord par image ou extension vidéo | Un raccord visuel ne prouve pas la continuité vocale ; un contexte sonore source ne suffit pas à valider les nouvelles paroles |
| Réparation/reprise | Même limitation avant une nouvelle confirmation, sans modifier les autres clips ni le coût déjà estimé |
| Récurrence | Les futurs scripts n'existent pas à l'autorisation ; pas de relecture intermédiaire promise. Si les mots exacts sont essentiels, orienter vers la préparation manuelle de chaque épisode |

La note récurrente n'invite donc pas à relire des prompts futurs avant l'autorisation : elle explique explicitement l'absence de cette étape intermédiaire. Le résultat vidéo conserve sa validation avant publication.

## Implémentation R2.2 et flux Symfony

`templates/ai/_speech_notice.html.twig` centralise le texte statique, sans JavaScript requis. Ses seules variantes concernent l'audio connu, le son coupé et le parcours récurrent ; elles ne constituent pas de nouveaux indicateurs de capacité du catalogue.

La note est incluse :

- dans `_story_proposal.html.twig`, avant la proposition, pour la préparation commune Studio/Séries ;
- dans `ai/generation.html.twig`, avant confirmation individuelle et reprise `waiting_key`, y compris les extensions utilisant cette page ;
- dans `series/episode.html.twig`, au devis commun (remplacement d'un plan compris) et à la reprise des étapes en pause ;
- dans `series/_recurrence.html.twig`, hors du panneau replié de configuration et avant l'autorisation des coûts.

Le contrôleur continue à charger les données privées existantes → Twig affiche les capacités audio déjà connues et la limitation → le formulaire d'origine conserve ses champs CSRF et sa confirmation → le service valide le même devis avant tout envoi. Aucun service métier, hash de consentement, prompt, prix, durée, catalogue, secret ou migration n'est modifié ici. La note n'est pas une validation de qualité et ne remplace pas les contrôles serveur.

`tests/Functional/AiSpeechNoticeTest.php` exerce dix cas sur les vraies routes avec clients HTTP interdits : Studio, modèle avec/sans audio, génération préparée/reprise de clé, devis Studio/Séries avec son conservé/coupé, récurrence. Il vérifie texte, ordre avant l'action, absence d'ancêtre replié/caché, consentement toujours requis, devis/prompt inchangés et absence de message lancé. Un test existant de réparation de plan vérifie aussi la note au devis de remplacement.

## Limites et suites

Les quatre échecs R2.1 restent attendus : langue et citations en R2.3, répliques/fidélité à relier à ce contrat, contexte pauvre en R2.5. R2.2 ne les masque pas en ajoutant une alerte universelle au rédacteur.

Aucune preuve de rendu parlé réel n'est acquise. Les essais autorisés en R7 devront examiner, sur le chemin effectivement intégré : mots prononcés, attribution au locuteur, langue, intelligibilité, synchronisation, voix entre plans, durée réelle et coût. Une réussite d'audio ambiant ou un JSON valide ne suffira pas. Toute dépense, nouveau pilote TTS/lipsync ou choix de fournisseur garde son autorisation propre ; aucune chaîne payante de repli n'est ajoutée automatiquement.

Pour les résultats des contrôles R2.2, la recette desktop/mobile et la reprise exacte : voir [le suivi R2](r2-qualite-redacteur.md) et [la roadmap](roadmap.md). Aucun test manuel payant n'est nécessaire pour relire ce contrat ou vérifier les notices.
