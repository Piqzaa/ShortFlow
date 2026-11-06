# Studio : modèles, langue et résultat — 6 novembre 2026

## Périmètre livré localement

Six configurations Gemini Omni 1.2 Flash chez Fal : texte, image et référence de personnage, chacune en 720p/1080p. Trois schémas publics sont conservés dans `tests/Fixtures/gemini-omni-12-contracts.json`. Les anciens identifiants restent disponibles ; aucun ancien modèle n’est redirigé vers Omni.

| Point de départ | Premier choix | Alternative avec audio | Scène sans voix |
|---|---|---|---|
| Texte | Omni 1.2 Flash 720p | Grok | Vidu Q1 |
| Image de départ | Omni 1.2 Flash Image 720p | Grok Image 720p | Vidu Q1 Image |
| Image de départ et fin | Omni 1.2 Flash Image 720p | LTX 2.3 Fast Image 720p | Vidu Q1 début/fin |
| Référence de personnage | Omni 1.2 Flash Référence 720p | Grok 1.5 Référence 720p | Vidu Q1 Référence |

Ces choix constituent une sélection pratique par compatibilité, audio et coût, pas un classement de qualité démontré. Aucun choix automatique. Prix d’exemple avec durée/résolution sur les cartes ; les frais par référence sont indiqués avec une référence d’exemple. Le devis final dépend des plans et références réellement retenus. Les autres modèles restent accessibles dans le catalogue.

Omni : audio natif inclus, 0,0819 USD/s en 720p et 0,1229 USD/s en 1080p, sans frais par image de référence. Exemple 5 s : estimation arrondie 0,41/0,62 USD. Texte/référence : 3–15 s. Image : sous-ensemble volontaire 3–10 s pour respecter aussi la limite avec image de fin. Référence : sept images maximum côté application, même si le fournisseur en permet dix. Les références vidéo/audio ne sont pas exposées. Prompt limité à 5 000 caractères dans l’application. Aucun champ `generate_audio` ou `enable_safety_checker` inventé pour ces endpoints.

Tarifs publics revérifiés pour les six anciens choix guidés texte/image/début-fin Grok, Vidu et LTX : seule leur date de contrôle est actualisée, pas leurs montants. Les contrats tarifaires de ces six choix changent donc de date ; la métadonnée de langue, elle, reste exclusivement dans la projection d’affichage et ne change aucun contrat durable.

## Langue et vidéo générée

« Décris ton plan » par défaut. « Décris ton plan en anglais » uniquement pour les six accès Veo déjà documentés avec une exigence anglaise. Changer de modèle ne traduit ni ne réécrit les dialogues. Audio natif ne promet ni français/argot intelligible, ni mots exacts, ni voix identique entre plans. Avertissement spécifique Kling O3 après le retour utilisateur ; Kling 3 annonce chinois/anglais. Omni/Grok n’ont aucun essai réel de français concluant dans ce lot.

La vidéo prête est montrée en haut avec téléchargement et liens de montage/plans. La sélection d’une version pour la publication reste facultative et repliée. Elle ne relance aucune génération et ne publie rien. Les actions après cette sélection ont des espacements et un retour à la ligne sur mobile. Les reprises de plans restent disponibles.

## Sources publiques consultées

- [Annonce Google Omni 1.2, 5 novembre](https://blog.google/innovation-and-ai/technology/developers-tools/introducing-gemini-omni-1-2-flash/).
- [Omni 1.2 Référence chez Fal : tarifs par résolution](https://fal.ai/models/google/gemini-omni-flash/v1.2/reference-to-video). Schémas texte/image/référence lus avec le connecteur Fal, sans soumission.
- [Retrait du seul accès Veo 3.1 Référence Fal au 17 novembre](https://fal.ai/models/fal-ai/veo3.1/reference-to-video/api).
- [Cycle de vie Google Cloud Veo 3.1](https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/veo/3-1-generate?hl=en) : page actuelle, fin au plus tôt le 4 mai 2027 pour les versions GA indiquées. Corrige la généralisation initiale du 17 novembre : les échéances dépendent du fournisseur et du produit.
- [Flow : évolutions des modèles](https://support.google.com/flow/answer/16352836?hl=en), calendrier propre à Flow.
- [Grok texte](https://fal.ai/models/xai/grok-imagine-video/text-to-video), [image](https://fal.ai/models/xai/grok-imagine-video/image-to-video), [référence](https://fal.ai/models/xai/grok-imagine-video/v1.5/reference-to-video).
- [Vidu texte](https://fal.ai/models/fal-ai/vidu/q1/text-to-video), [image](https://fal.ai/models/fal-ai/vidu/q1/image-to-video), [début/fin](https://fal.ai/models/fal-ai/vidu/q1/start-end-to-video), [référence](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video).
- [LTX : prix officiels](https://docs.ltx.io/pricing/).

## Vérifications et limites

- 155 tests unitaires contrats/catalogue, 7 078 assertions : corps des six configurations, limites, schémas, prix et invariance des contrats pour l’affichage de langue.
- Trois tests fonctionnels ciblés personnages, 87 assertions : trois choix compatibles par mode, tarifs frais, stockage réel des identifiants avec rollback, langue et dialogues conservés.
- Un parcours Omni Référence, 15 assertions : fiche synthétique, deux plans de 5 s, devis 0,82 USD, état non confirmé et aucune génération, transaction annulée.
- Résultat/publication : deux tests ciblés, 98 assertions, antérieurs à l’ajout Omni ; huit vues avant/après sélection à 320/390/768/1280, boutons espacés d’au moins 10 px, aucun débordement ni erreur JS.
- 200 tests frontend verts, `check` et build verts. Avertissements Vite existants sur ressources résolues à l’exécution et gros chunk Three.js conservés.
- Seize états navigateur sur les quatre points de départ × 320/390/768/1280 : trois choix exacts et prix visibles, aucun débordement ni erreur JS, texte saisi conservé, aide anglaise conditionnelle. Capture mobile relue visuellement. HTTP intercepté, soumissions remplacées par une réponse synthétique.
- Quatre GET authentifiés dev (`/studio`, `/studio/creation`, `/studio/ia`, `/studio/personnages`) : HTTP 200, transaction en lecture seule, 27 compteurs identiques, zéro HTTP fournisseur. Rapport local `artifacts/omni12-native-readonly.json`.
- PHPStan ciblé neuf fichiers, style de la nouvelle classe, 51 Twig et conteneur verts. Pas de suite PHP globale ni revue indépendante. Trois erreurs PHPStan historiques dans `AiEpisodeFlowTest` hors lignes du correctif ne sont pas annoncées corrigées.

Les premiers essais comportaient quatre échecs unitaires dus au montage du test de référence de fin, corrigé avant le passage vert ; un ancien passage résultat utilisait un cache Twig test périmé. Ne pas confondre ces passages avec les derniers résultats verts.

Aucun appel d’inférence, upload fournisseur, coût, clé réelle lue/déchiffrée, publication, email, migration supplémentaire, push ou déploiement pour ce lot. Le navigateur et les tests utilisent des références/clips synthétiques et du HTTP intercepté/simulé. Qualité audiovisuelle, disponibilité authentifiée du nouveau modèle et français réel restent à vérifier par le propriétaire sur un devis et un accord de lancement distincts. Grand R7 reste ouvert.

Cache web dev reconstruit. Seul `background-worker`, déjà actif, redémarré pour charger le nouveau catalogue après contrôle : zéro message, génération active, épisode running/assembling et export actif. Les autres workers restent inchangés ; aucune file n’a été consommée par un banc de commande.
