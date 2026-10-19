# Premier essai IA réel — réussi le 19 octobre 2026

Statut : **première génération Fal réelle réussie** après enregistrement de la clé par le propriétaire et réponse explicite « clé save, essai autorisé ». Une seule demande pour le modèle/réglages ci-dessous. Le résultat a rejoint la bibliothèque privée. Le drapeau global reste désactivé ; l’activation a été limitée aux processus CLI de cet essai. Au contrôle initial : une clé Fal enregistrée, aucune génération active.

## Essai proposé

- Fournisseur : Fal, avec le compte approvisionné du propriétaire.
- Modèle : Kling 2.5 Turbo Pro, endpoint `fal-ai/kling-video/v2.5-turbo/pro/text-to-video`.
- Un seul plan de 5 secondes, vertical 9:16, sans audio.
- Sujet : un petit robot en papier marche dans une forêt lumineuse.
- Prompt prévu : `A small paper robot walks through a softly glowing forest at dawn. Smooth tracking camera, gentle motion, cinematic soft light, vertical composition, one continuous shot, no text.`
- Tarif affiché par Fal revérifié le 19 octobre : **0,35 USD pour 5 secondes**, hors taxes et conversion éventuelles. [Page fournisseur](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video).
- Aucune deuxième soumission payante automatique et aucune publication sur les réseaux. Une consultation ou reprise du même résultat est distincte d’une nouvelle génération.

## Préparation et exécution

1. Le propriétaire saisit sa clé dans VydFlow → Connexions → IA et clés API, sur le compte VydFlow utilisé pour le test. Ne pas transmettre la clé dans la conversation, un fichier versionné ou une URL.
2. Obtenir son accord explicite sur le modèle, les réglages et le coût ci-dessus avant toute soumission. Le simple enregistrement d’une clé ne vaut pas consentement à une dépense.
3. Conserver la préparation et son estimation, puis activer les générations uniquement dans les processus CLI dédiés à cet essai, sans modifier `.env.local`. Ne pas démarrer d’autres préparations. La demande réelle doit traverser `AiGenerations`, `AiVideoApi` et le stockage privé existants, plutôt qu’un outil de génération externe à VydFlow.
4. Vérifier que la demande part une seule fois, conserver sa référence dans l’état existant, puis consulter cette même demande. Sur réponse ambiguë, ne pas refaire le POST ; vérifier le résultat fournisseur.
5. Contrôler la copie dans la bibliothèque privée, la lecture du fichier et les métadonnées de la sonde. Le succès distant seul ne valide pas la copie privée. Ne pas soumettre de publication sociale.
6. Après l’essai unique, rétablir le drapeau désactivé si aucune autorisation plus large n’a été donnée, documenter le résultat réel et le coût constaté uniquement s’il est accessible de façon fiable. Ne pas annoncer le coût estimé comme une facture vérifiée.

## À comprendre

Le bouton de confirmation crée une intention de dépense ; la présence d’une clé prouve seulement son stockage. Le service vérifie clé, paramètres et tarif, Messenger exécute le traitement, l’adaptateur Fal traduit la demande, et la bibliothèque valide puis conserve le média. Ce test doit donc exercer le parcours VydFlow complet. Les tests automatisés existants utilisent des API simulées et ne remplacent pas cet essai fournisseur réel.


## Résultat constaté

- Génération locale n° 1, état `available`, vidéo privée n° 8, titre « Premier essai IA — robot en papier ».
- Le propriétaire de la clé Fal enregistrée est celui de la génération et de la vidéo. Aucune clé, adresse email ni URL de résultat signée publiée dans ce compte rendu.
- Fichier MP4 H.264 : 10 385 992 octets, 1080 × 1920, durée sondée 5,042 secondes. Copie durable présente dans le stockage privé dev, associée dans la même transaction Doctrine.
- Le traitement a traversé le service `AiGenerations`, le message `GenerateAiVideo`, le vrai worker Messenger, l’adaptateur `FalVideoApi`, le téléchargement contrôlé et la bibliothèque existante. La préparation et la confirmation ont été effectuées par un script local de test sous le compte propriétaire de la seule clé Fal enregistrée ; le clic sur le formulaire web n’a pas été exercé dans cet essai réel.
- Une activation `AI_GENERATION_ENABLED=1` a été fournie seulement aux deux commandes CLI dédiées à la confirmation et au worker temporaire. Le worker habituel a été arrêté à vide pendant l’essai, puis rétabli après arrêt gracieux du temporaire. La configuration du site n’a jamais été ouverte globalement aux générations payantes.
- Décodeur FFmpeg : lecture de la totalité du fichier terminée sans erreur (code de sortie 0). Un aperçu local a été copié dans `artifacts/first-ai-real-test-preview.mp4`, hors Git, depuis le fichier déjà récupéré ; aucun deuxième téléchargement fournisseur nécessaire.
- Les nombres de publications YouTube, Instagram et TikTok sont identiques avant et après. Aucune publication sociale ajoutée.
- Coût **estimé** et autorisé : 0,35 USD hors taxes/conversion. Le montant réellement débité n’a pas été consulté dans la facturation Fal ; ne pas présenter l’estimation comme une facture vérifiée.
- Aucun appel supplémentaire à la soumission de génération ; les appels de suivi consultent la même référence fournisseur. Les scripts et caches temporaires restent ignorés par Git. Le diagnostic final confirme que les générations payantes globales sont désactivées et le worker habituel est de nouveau actif.

Lecture navigateur de la copie locale : métadonnées chargées, lecture jusqu’à `ended=true` à 5,041667 s, aucune erreur média ni erreur console. Le serveur d’aperçu était limité à la boucle locale et a été arrêté après vérification.
