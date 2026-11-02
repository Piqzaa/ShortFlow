# Contact et suggestions — livraison locale du 2 novembre 2026

Besoin : audit marché/SEO du 27 octobre (contact trouvable depuis landing et Studio), repris en R6.4. Le propriétaire demande de terminer les fichiers locaux après synchronisation avec l’autre PC. Cette livraison anticipée ne clôture ni R5 ni R6.

## Comportement et Symfony

`ContactController` traite uniquement GET /contact. Il choisit le template public ou Studio selon ROLE_VERIFIED, avec réponse privée/no-store. Les trois templates partagent un contenu unique ; Twig échappe l’adresse déjà centralisée dans `legal_identity.email` et encode les sujets des liens mailto. La messagerie de l’utilisateur compose l’email : aucune collecte, aucun envoi automatique ni stockage de message par VydFlow.

Accès : pied de landing et menu public mobile, bas de sidebar et menu Studio. Les six destinations Studio restent aussi dans le menu natif, en complément de la barre basse. La page Contact ne sélectionne pas artificiellement Vidéos. Cible main focalisable, noms accessibles R5 et fonctionnement sans JS conservés. CSS prévoit retour à la ligne et actions de 44 px.

## Vérifications

- 20 tests PHP / 291 assertions : AccountTest et ValidationQueueTest, sur shortflow_test migrée. Premier passage refusé par ancien cache de routes ; autoload/cache actualisés. Une attente historique de sept liens adaptée à huit (Contact ajouté). Rejeu final vert en copie Linux isolée sans `.env.local`.
- 118 Twig et conteneur valides ; PHPStan sans erreur ; style contrôleur/tests/export vert ; 186 frontend, check et build verts (avertissement taille bundle existant).
- Page publique réelle locale : 320 px sans débordement (320/320), lien d’évitement focalise contenu, trois mailto encodés et actions de 44 px. Capture `output/playwright/contact-mobile.png` inspectée.
- Export synthétique étendu : 23 vues dont Contact public/connecté, rollback vérifié, onze compteurs identiques, zéro HTTP fournisseur/inférence. Le routeur de recette sert aussi favicon/cursor locaux ; aucune ouverture réseau.
- Revue indépendante en lecture seule Contact et R5.3–R5.5 : aucun blocage de code identifié. Sa réserve sur la suppression des destinations du menu est levée en conservant les six liens.

Réception réelle de `dev@alexbuild.fr` et délai de réponse restent à confirmer par le propriétaire avant ouverture : R6.4 garde sa case ouverte, aide complète non livrée. Aucun email réel envoyé, coût, publication, migration applicative, redémarrage worker, déploiement ou push.