# Roadmap V1 — vision de lancement validée le 15 octobre 2026

Le passage à la V1 est autorisé. Comptes terminés ; lot bibliothèque autorisé le 15 octobre 2026. La landing conserve ses animations et adopte le parcours bêta : inscription → confirmation → connexion → studio/bibliothèque. L’ancienne liste d’attente reste sur /liste-attente, hors parcours principal, sans effacement de données. Décision du propriétaire du 15 octobre : priorité à la publication immédiate multi-réseaux avant l’IA. Cadrage dans docs/publication.md.

1. **Cadrage et comptes — terminé** : inscription, connexion/déconnexion, confirmation obligatoire, récupération et dashboard privé. Emails exclusivement locaux dans Mailpit.
2. **Bibliothèque privée — terminée** : import contrôlé, stockage hors de public, lecture autorisée avec Range, renommage/suppression et isolation propriétaire. MP4 H.264/AAC ou WebM VP8/VP9/AV1 et Opus/Vorbis, vidéo 8 bits ; 100 Mo/fichier, 3 minutes, 3 840 px par côté, 1 Go et 100 vidéos/compte. ffprobe lit les métadonnées sans transcodage. Liste paginée, dernières vidéos et progression de transfert.
3. **Publication immédiate multi-réseaux — lot YouTube en validation** : sélection d’une ou plusieurs vidéos, destinations TikTok/YouTube/Instagram au choix, métadonnées adaptées, confirmation récapitulative, connexion OAuth officielle et suivi par vidéo/destination. Intégrations construites une par une et tests réels conditionnés aux accès développeur. Voir docs/publication.md et docs/youtube.md.
4. **Préparation et calendrier** : brouillons, fuseaux horaires, programmation/annulation et tâches traçables, à partir du moteur de publication immédiate.
5. **Studio IA BYOK** : un fournisseur/un modèle, connexion guidée unique, secret chiffré, estimation documentée, génération asynchrone et copie durable dans la bibliothèque. Le fournisseur facture directement l’utilisateur. Choix du fournisseur et essais payants dans ce lot ultérieur.
6. **Montage automatique guidé** : disponible pour les vidéos importées et générées, avec sous-titres, adaptation verticale et habillage prédéfini. Quelques recettes solides, prévisualisation et choix explicite avant transformation ; conserver la source. Musique/intro/outro selon les recettes retenues et les droits disponibles. Pas d’éditeur vidéo complet au lancement.
7. **Séries récurrentes** : thème, prompt, fournisseur/modèle BYOK, recette de montage, destinations et créneaux. Préparation en avance, descriptions adaptées, validation activée par défaut ; mode automatique activé explicitement selon les capacités autorisées des plateformes. Pause, limites de générations et plafond de dépense configuré. Vérifier les moyens de respecter ce plafond avec le fournisseur choisi avant activation ; une estimation seule ne garantit pas une limite de facturation.
8. **Abonnement logiciel et lancement** : Stripe pour VydFlow uniquement, quotas, infrastructure privée adaptée, sauvegardes/restauration, exploitation, suppression de compte et données, confidentialité et documents commerciaux finalisés avant ouverture publique. Aucun prix définitif ni promesse d’IA illimitée.

## Promesse et parcours de lancement

« Importe ou génère tes vidéos courtes, prépare-les et programme leur publication sur tes réseaux depuis un seul studio. »

La cible du premier lancement commercial inclut la chaîne complète ci-dessus, avec des options limitées et fiables. Cette cible ne signifie pas que ces fonctions sont disponibles aujourd’hui, ni que les lots futurs sont autorisés à être développés immédiatement. Livraison progressive : publication → calendrier → IA BYOK et montage → séries → préparation du lancement. Le lot YouTube est développé localement et documenté dans docs/youtube.md, avec premier transfert réel traité avec succès le 16 octobre et worker Docker automatique ; arrêt pour bilan après ce lot avant Instagram/TikTok/IA.

### Parcours vidéo importée

Importer une ou plusieurs vidéos → montage facultatif → préparation des titres, légendes et descriptions par réseau → prévisualisation/récapitulatif → publication immédiate ou date choisie. Une vidéo déjà finalisée peut passer directement à la préparation/publication. L’aide à la rédaction est facultative ; les textes restent modifiables. Son fournisseur, son coût éventuel et ses conditions seront cadrés dans le lot concerné.

### Parcours vidéo IA et série

Une génération ponctuelle rejoint la bibliothèque et le même parcours que les imports. Une série réutilise une configuration : sujet, consignes, modèle BYOK, montage, destinations et fréquence. VydFlow prépare les vidéos et leurs textes avant le créneau prévu. « Publier chaque lundi à 14 h » désigne l’heure de publication cible, pas le début de génération ; le délai de préparation et la date limite de validation doivent être définis.

Validation activée par défaut : tant qu’une vidéo n’est pas approuvée, sa publication reste bloquée. Si la validation manque ou si la préparation dépasse le créneau, afficher le retard et demander une reprogrammation, sans publier tardivement de façon implicite. Le mode automatique nécessite une activation explicite et des règles compatibles avec chaque destination. Pause d’une série et annulation d’une publication doivent être accessibles ; leur effet sur les traitements déjà engagés sera précisé dans le cadrage technique.

### Calendrier et suivi communs

Le calendrier est le centre de contrôle des vidéos importées, générées et issues des séries. États compréhensibles : en préparation, à valider, programmée, publiée ou en échec, avec détail par étape et destination. Modification des textes, changement de date et annulation avant envoi ; choix explicite du fuseau horaire et comportement des récurrences lors des changements d’heure à cadrer. Un échec doit permettre une reprise sans republier une destination déjà réussie.

TikTok, Instagram Reels et YouTube Shorts sont les destinations visées, sous réserve des accès et capacités réellement validés. Un lancement commun ne garantit pas une apparition à la même seconde sur tous les réseaux. Ne pas annoncer une automatisation ou une intégration disponible avant validation réelle.

## Expérience IA retenue
Connexion fournisseur guidée une fois, conservation de l’idée pendant la configuration, réglages simples, coût estimé avant lancement, vidéo récupérée dans VydFlow. Une connexion fournisseur par autorisation pourrait remplacer la copie de clé si une API adaptée existe réellement. Pas de solde inventé ni de coût garanti.

L’hybride (recharges vendues par VydFlow + BYOK) est une piste future, pas une fonction autorisée aujourd’hui. Il modifierait les flux économiques et nécessiterait une étude de marge, de fiscalité et des conditions fournisseur. Pas de wallet ou d’architecture de paiement IA anticipée.

Montage guidé, sous-titres et séries récurrentes font désormais partie de la cible du lancement, à développer dans leurs lots. Plusieurs fournisseurs/modèles, éditeur vidéo complet, automatisations libres et statistiques avancées restent ultérieurs. Les décisions du présent document remplacent l’ancien prompt de lancement.
