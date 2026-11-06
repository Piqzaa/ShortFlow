# Récapitulatif et connexions lisibles — 6 novembre 2026

Retour propriétaire : résolution répétée dans « Avant de générer », identifiant Fal opaque affiché comme nom de compte, identifiants sociaux peu utiles dans Connexions. Il souhaite aussi raccourcir son introduction à 5 secondes.

Le récapitulatif ajoute la résolution uniquement si le nom de modèle ne la contient pas déjà, sans distinction de casse. Les noms sans résolution gardent l’information. « 1 plan » est au singulier.

L’identifiant de compte Fal n’est plus passé aux vues par les contrôleurs épisode/génération et n’est plus rendu dans le récapitulatif ou la page de génération. L’affichage reste « Fal · connexion navigateur enregistrée » et le suivi rappelle que le compte actif est vérifié avant envoi. Aucun nom humain n’est inventé : il n’existe pas actuellement dans les métadonnées enregistrées. Aucun appel fournisseur ni déchiffrement d’une vraie connexion n’est fait pour découvrir un nom.

Dans Connexions, la ligne visible « Identifiant : … » est retirée ; noms de chaîne, @Instagram, noms TikTok, état et liens/actions sont conservés. Les identifiants techniques restent dans les champs cachés et URLs nécessaires au ciblage : aucune protection d’appartenance, reconnexion ou déconnexion n’est affaiblie.

Le contrôle interne du compte Fal reste identique : `account_id` continue à participer à l’autorisation, au devis et à la vérification avant un appel payant. Le test de changement de compte actif refuse toujours l’envoi sans soumission fournisseur.

Le [prompt du présentateur](pilotes/vyd-presentateur/studio-prompt.md) est adapté à cinq secondes avec la réplique courte « Tu postes encore tes vidéos une par une ? Découvre VydFlow. » : le texte initial de dix secondes n’est pas simplement compressé. Repères 5 s : environ 0,41 USD en 720p / 0,62 USD en 1080p. Aucun brouillon réel, durée, modèle, consentement ou fiche du propriétaire n’est modifié ; le récapitulatif de son message affichait encore 7 s et doit être relu avant confirmation.

Vérifications ciblées : six fonctionnels, passages distincts 1/56 + 2/33 + 2/87 + 1/31, soit 207 assertions. Les quatre devis 5/10 s en 720/1080 n’affichent chacun la résolution qu’une fois ; connexion Fal et garde de changement de compte simulées ; noms sociaux et champs de ciblage cachés préservés. Premier test social corrigé : l’assertion de nom Instagram cherchait dans le premier groupe YouTube, sans défaut applicatif associé.

PHPStan vert sur les deux contrôleurs et le banc personnages ; trois alertes historiques hors lignes modifiées dans les bancs FalOAuth/SocialAccounts restent signalées, pas annoncées corrigées. Style des six fichiers PHP, 55 Twig et conteneur verts ; cache dev actualisé. Pas de suite PHP globale, frontend/build ou revue indépendante nouvelle (JS/CSS inchangés).

Six vues statiques de recette à 390/1280 : récapitulatif Fal, récapitulatif Omni et connexions synthétiques, sans doublon/ID visible/débordement ; noms et actions conservés, capture mobile relue. Réseau intercepté et aucune soumission de propriétaire. La première assertion visuelle avait supposé une résolution 720p pour Kling, corrigée pour compter la résolution réellement exposée.

Aucune migration, génération réelle, coût, lecture de clé réelle, upload fournisseur, email, publication, push ou déploiement ; aucun worker redémarré. R7 reste ouvert.
