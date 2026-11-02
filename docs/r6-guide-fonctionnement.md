# R6.3 — guide et accès à l’aide

Le guide public `/fonctionnement` réunit fonctionnement, coûts/bêta, réseaux et premiers pas. Sommaire à ancres natives, contenu HTML sans dépendance aux animations ; actions vers inscription/connexion ou Studio/import/Connexions selon le compte confirmé.

La landing le propose dans la navigation desktop/mobile, le pied de page et la note du parcours IA. À la demande du propriétaire, **Contact est retiré de la sidebar et du menu du Studio**. Le compte comporte désormais « Besoin d’aide ? », avec le guide et Contact. Les six destinations métier et l’accès Mon compte restent conservés. Contact public reste accessible depuis la landing.

Les textes décrivent les contraintes du code actuel : import VideoProbe (100 Mo / 180 s), MP4 H.264 ou WebM, Instagram professionnel dans InstagramApi, TikTok Sandbox. Pas de promesse d’accès général, tarif provisoire vendu, génération gratuite, fidélité audiovisuelle garantie ou publication autonome. Génération et publication ont chacune leur confirmation ; une programmation déjà confirmée part à la date prévue sans nouveau clic.

## Validation

- Revue indépendante des sources : aucun blocage. Le token `--ink` est bien défini dans app.css ; la suggestion de remplacement par `--text` n’a donc pas été retenue.
- Recette publique Chromium : guide et landing à 320/390/768/1001/1100/1280 px, préférence reduced motion, HTTP200 et largeur document égale au viewport, aucune erreur JavaScript. Guide sans JavaScript à 390 px : quatre sections et ancre Réseaux accessibles. Captures mobile/desktop et en-tête 1001 px inspectées.
- Contrôles PHP, frontend et espace compte : résultats finaux dans le manifeste associé.

Le premier passage PHP utilisait un ancien cache sans debug ne connaissant pas la nouvelle route : 16 tests / 143 assertions, quatre échecs et un avertissement. Autoload et caches test/dev ont été actualisés ; l’attente de route Connexions du nouveau test a aussi été corrigée vers `/studio/connexions`. Aucun contrôle métier n’a été affaibli.

R6 reste ouvert. R6.4 doit encore préciser le délai de support avec le propriétaire avant ouverture ; réception de la boîte confirmée, transport applicatif toujours Mailpit local et production R8.4 distincte. R6.5 SEO et revue globale R6.6 restent à faire. Aucun push, appel fournisseur réel, coût, email réel, publication, activation, migration applicative, worker redémarré ou déploiement.

Complément final : **les seize cas compte/guide sont couverts sur deux passages** : dernier passage de 16 tests / 185 assertions, quinze réussites et une attente d’URL d’import incorrecte ; chemin complet vérifié dans le routeur, test corrigé puis **1 test / 7 assertions vert**. Pas de troisième passage complet après cette seule correction de test. Le lien produit était correct. La suite PHP globale 825 / 9 596 reste historique, non rejouée.

**195 frontend**, syntaxe JS, build, 120 Twig, conteneur, PHPStan et style ciblé réussis. Avertissement de taille Three.js existant au build.

Compte/contact/guide connecté : trois réponses Symfony exportées avec propriétaire synthétique en transaction ; onze compteurs identiques après rollback et zéro HTTP fournisseur. Relecture navigateur de ces réponses à 320/390/768/1280, sans JavaScript : aucun débordement, zéro lien Contact dans sidebar/menu, guide présent dans le compte et six destinations basses conservées. Clic Contact depuis le bloc Aide vérifié à chaque largeur ; captures 390/1280 inspectées. Ces captures constituent une recette synthétique, pas une nouvelle validation sur iPhone matériel.

**R6.3 terminé**, R6.4 prochain point ; voir [le manifeste](fixtures/r63-guide-20261103.json).
