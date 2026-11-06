# Vyd — présentateur

6 novembre 2026. Demande explicite du propriétaire : générer le portrait du présentateur proposé, créer sa fiche dans VydFlow, fournir le prompt et le modèle pour une introduction avant une capture d’interface commentée avec sa propre voix off. Aucune génération vidéo autorisée par cette préparation.

## Portrait

Une image originale créée avec l’outil intégré `image_gen` : [portrait-v1.png](portrait-v1.png), 941 × 1672 pixels, 2 177 219 octets. Le [prompt exact](image-prompt.txt) est conservé. Sortie originale laissée sous le répertoire `generated_images` de Codex, copie pérenne dans le projet. Image inspectée : adulte fictif, visage frontal dégagé, hoodie anthracite/violet, mèche violette, arrière-plan de bureau flouté, aucun texte/logo ; aucun personnage réel ou célébrité demandé.

Ancrage d’identité : adulte 25–30 ans, peau olive claire, yeux marron, cheveux noirs courts ondulés et petite mèche violette, sourcils épais, visage rasé, hoodie anthracite doublé violet, posture détendue et regard caméra. Aucun clonage de voix ni portrait d’utilisateur utilisé.

## Import local demandé

La base locale actuellement chargée contient un seul profil vérifié, identifiant 1, nom d’affichage vide, avec la fiche **Bendo**. Ce rattachement local au parcours du propriétaire a été contrôlé avant l’import. Le précédent repère historique « profil Vyd, id 2 » n’est pas celui observé dans cette base et n’a pas servi de cible. Aucun rattachement à un compte Fal distant ni lecture de clé déduits de cette inspection.

Fiche créée via `AiCharacters`/`AiReferenceStorage` : **id 2, Vyd — présentateur, révision 1**, un portrait JPEG privé, aucune vue supplémentaire. L’original PNG n’a pas été modifié ; conversion JPEG par le service applicatif existant et contrôles de taille/dimensions/quota conservés.

La [preuve d’import](import.json) conserve empreintes et dimensions : JPEG privé 139 141 octets, 941 × 1672. Sur 27 compteurs, seul `ai_character` augmente de 1. Fiches existantes inchangées ; aucune demande portrait IA, génération vidéo, épisode ou message nouveau. Trois drapeaux de génération forcés à false dans le seul processus CLI isolé, cinq clients HTTP bloqués, zéro appel fournisseur. Aucun drapeau global, cache web ou worker modifié.

Relecture authentifiée en transaction de lecture seule : bibliothèque, fiche `/studio/personnages/2`, portrait et liste de sélection HTTP 200 ; 27 compteurs inchangés, zéro HTTP fournisseur. L’empreinte du fichier servi par la réponse binaire correspond à celle de l’import. Le premier banc cherchait à hacher `getContent()` d’une `BinaryFileResponse` (sans contenu en mémoire) ; correction du banc pour vérifier le fichier réellement servi, sans nouvel import.

## Préparation vidéo remise au propriétaire

[Idée, réglages et prompt complet prêts à copier](studio-prompt.md). Prompt vidéo 1 119 caractères, sous la limite de 1 500 du champ. Un plan de 10 s en 9:16, modèle `gemini-omni-12-subject-1080p`, fiche Vyd seule présente, son conservé. Tarif Fal public revérifié : 0,1229 USD/s en 1080p, audio inclus et références non facturées, soit environ 1,23 USD pour 10 s. Le coût réel doit être relu dans le devis applicatif avant confirmation.

La génération intégrée du portrait n’est pas une exécution du pipeline portrait Fal de VydFlow. Aucun appel d’inférence Fal, upload fournisseur, budget/lancement vidéo, voix utilisateur enregistrée, capture d’interface, publication, achat de crédits, migration ou déploiement. La fiche n’est pas une approbation humaine du portrait, ni une validation de la voix française, des mouvements ou de la qualité publicitaire. Pas de nouvelle suite de tests/build : création de contenu uniquement, pas de code applicatif changé.
