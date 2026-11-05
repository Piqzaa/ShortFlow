# R7.4 — proposition d’essai réel limité

5 novembre 2026. **Cadrage préparé, essai non autorisé et non lancé. R7.4 reste ouvert.** Le propriétaire doit encore choisir le budget, confirmer le compte facturé et les deux portraits, puis valider le devis final avant tout envoi. Aucun achat de crédits, téléchargement de référence vers un fournisseur, génération ou activation effectué ici.

## Proposition à relire

Tester d’abord la continuité visuelle avec **quatre mini-épisodes de dix secondes**, composés chacun de deux clips indépendants de cinq secondes : deux épisodes avec un personnage seul, puis deux avec les deux personnages. Total maximal : **huit demandes vidéo, quarante secondes**. Les clips sont récupérés dans la bibliothèque privée et assemblés localement ; aucune publication sociale.

Modèle candidat déjà intégré : **Vidu Q1 Référence de personnage**, `fal-ai/vidu/q1/reference-to-video`, via la **clé API enregistrée dans VydFlow**. Le compte du connecteur Fal de l’assistant n’est pas présumé identique à ce compte applicatif. Aucun appel de génération par le connecteur ne remplacerait la vérification du parcours VydFlow.

Le tarif public consulté le 5 novembre indique **0,40 USD pour une vidéo de cinq secondes**, soit **8 × 0,40 = 3,20 USD estimés**, hors taxes et conversion. **Plafond proposé : 4 USD de générations, à accepter ou modifier par le propriétaire. Aucun plafond n’est encore autorisé.** Ce plafond de consentement n’est pas un réglage de facturation automatiquement configuré chez Fal. Une éventuelle recharge du compte est une décision distincte, jamais automatique. [Page officielle du modèle et du tarif](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video).

Le prix public n’établit ni le solde, ni les permissions de clé, ni un tarif propre au compte. Au moment de l’essai, relire le tarif et le devis exact, refuser toute différence non approuvée et compter chaque soumission, même si son résultat est incertain. Huit demandes constituent une limite de quantité : un reliquat de budget n’autorise pas une variante supplémentaire.

## Références et paramètres

**Deux portraits réels à choisir et faire approuver, sans nouvelle génération d’image prévue.** Les noms Milo/Nox et leurs traits reprennent le [pilote original](pilote-histoire-chats.md), mais les fiches utilisables ne sont pas supposées déjà présentes. Les images de scène du pilote comportent les deux personnages et ne sont pas automatiquement assimilées à deux portraits individuels validés. Si d’autres fiches sont retenues, réviser les noms/prompts avant tout accord de lancement.

Conserver pour chaque référence : propriétaire applicatif, identifiant et révision de fiche, nom de fichier privé et SHA-256. Fixer les copies dans les quatre préparations, avec Milo seul dans les quatre premiers clips et Milo/Nox dans les quatre suivants. Ni mise à jour implicite des fiches ni ajout de tous les personnages à chaque scène.

Paramètres prévus : vertical 9:16, caméra fixe, actions simples, `bgm=false`, `movement_amplitude=auto`, contrôles de sécurité activés. Le montage est configuré pour couper le son des plans. Ce premier essai évalue l’image et les mouvements ; **il ne valide pas les voix, répliques françaises, lipsync ou audio natif**, qui restent dans un essai distinct.

Le schéma actuel demande `prompt` et `reference_image_urls`, avec prompt limité à 1 500 caractères et jusqu’à sept références décrites. L’API documente aussi les Data URI utilisées par l’adaptateur existant. Le contrat ne fournit pas de champ de durée pour cet endpoint ; ne pas ajouter un `duration` inventé : cinq secondes est ici la durée annoncée de la sortie. Vérifier aussi la longueur du prompt final enrichi par VydFlow après sélection des fiches, pas seulement celle du texte de scène. [Contrat API officiel](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video/api).

Les huit prompts précis et les paramètres proposés figurent dans [le protocole JSON](fixtures/r74-essai-propose-20261105.json). Ce fichier est un **document de préparation**, avec références/compte/accord encore absents, pas un corps de requête prêt à envoyer.

| Épisode | Personnages | Deux actions simples | Estimation publique |
| --- | --- | --- | --- |
| Solo 1 | Milo | Tourner la tête ; lever puis baisser une patte | 0,80 USD |
| Solo 2 | Milo | Marcher trois pas ; regarder un bol puis la caméra | 0,80 USD |
| Duo 1 | Milo, Nox | Milo tourne la tête ; Nox tourne la tête à son tour | 0,80 USD |
| Duo 2 | Milo, Nox | Avancer d’un pas ; Nox lève une patte, Milo reste immobile | 0,80 USD |

Pas de danse, saut complexe, contact entre personnages, échange d’objet ou parole imposée dans ce premier protocole. Les actions doivent pouvoir se terminer dans un clip de cinq secondes.

## Exécution après les accords manquants

1. Confirmer le compte Fal utilisé par le propriétaire dans VydFlow, sa disponibilité/crédits et les deux références approuvées, sans afficher ni consigner la clé. Un abonnement ou des crédits d’un autre service ne sont pas supposés utilisables chez Fal.
2. Préparer les quatre épisodes dans le parcours existant ; relire chaque scène, ses références exactes, son modèle et son devis. Conserver les empreintes de devis et le plafond accordé. Ni modification de `.env.local` ni ouverture globale des générations.
3. Avant traitement, contrôler la file et le périmètre des jobs de l’utilisateur. Préparer un traitement limité aux seules demandes approuvées ; ne pas démarrer une consommation générale qui pourrait entraîner d’anciens jobs. La procédure et l’activation temporaire doivent être revues à ce moment-là ; aucun worker n’est lancé par ce cadrage.
4. Soumettre de façon suivie, au plus les huit demandes approuvées. Conserver immédiatement identifiant/reçu/état de chaque demande. Au moindre envoi ambigu, suspendre et rapprocher le reçu avec le fournisseur ; aucune répétition automatique ni nouveau modèle de secours. Un incident de récupération reprend la demande existante, pas une nouvelle génération.
5. Récupérer les fichiers via les contrôles privés existants, vérifier décodage/métadonnées, assembler et relire les quatre résultats. Conserver les fichiers et observations avant toute conclusion de qualité. Le succès d’une soumission ne prouve pas celui de la copie privée.
6. Relever le coût réel seulement s’il est accessible de façon fiable ; garder sinon la mention « estimation ». Arrêter le traitement temporaire et restaurer toute activation limitée. Contrôler l’absence de nouvelle publication sociale et documenter la fin de l’essai.

## Grille d’observation R7.5

Pour chaque clip et chaque montage, noter **réussi / défaut observé / impossible à conclure**, avec le moment du défaut et une capture si utile :

- Visage, museau, yeux et couleurs identiques à la référence.
- Tenue et proportions stables entre les deux plans puis entre épisodes.
- Nombre de personnages correct ; absence de Nox dans les scènes solo.
- Identités et vêtements séparés dans les scènes duo, positions gauche/droite conservées lorsque demandées.
- Gestes simples exécutés et terminés, sans membre supplémentaire, fusion ou transformation gênante.
- Assemblage lisible, sans coupe qui masque un geste inachevé ni image noire/corrompue.
- Audio/voix/langue : **non évalués par ce protocole visuel**.

Cette grille est vierge : **aucun résultat ni avis humain n’est encore obtenu**. Un seul résultat agréable ne certifie pas une série stable. R7.3c/R7.5 resteront ouverts sur les capacités non observées ; R7.6 demandera une décision explicite sur ce qui est présentable.

## Option sans nouvelle dépense

Le propriétaire peut rester à **0 USD de nouvel appel**. Le [premier essai robot du 19 octobre](ia-premier-essai.md) documente une vraie vidéo IA existante, dont la présence actuelle et l’usage doivent être vérifiés avant relecture. Elle peut aider à relire lecture/gestes/copie privée ; elle ne valide pas une continuité de deux personnages. La maquette de trente secondes du pilote est un montage d’images fixes et ne valide pas de mouvement IA. Aucune dépense n’est nécessaire pour conserver ces limites comme non validées.

## Vérification de ce cadrage

Page et API publiques Vidu consultées, schéma du catalogue Fal lu via le connecteur ; aucune génération/upload/solde/historique demandé. La connexion du connecteur ne prouve pas le compte VydFlow et aucune identité de compte ou clé n’est copiée dans le protocole. Le catalogue applicatif et ses dates restent inchangés : cette vérification documentaire n’actualise pas silencieusement un devis enregistré.

Contrôles locaux du JSON : quatre épisodes, deux plans chacun, huit plans de cinq secondes, présence attendue par épisode, longueur des prompts bruts, somme des coûts et champs d’accord/références volontairement absents. Aucun code applicatif, schéma, service, test ou dépendance modifié ; aucune suite PHP/frontend/build/navigateur ou revue indépendante rejouée pour ce document. Les résultats R7.3c restent historiques.

**Il manque maintenant le choix de budget, le compte facturé et les deux références.** Une fois ces éléments connus, établir le devis final et demander l’accord de lancement concret. Aucun coût, génération, achat de crédits, email, activation, publication, migration, cache dev actualisé, worker démarré/redémarré, push ou déploiement.
