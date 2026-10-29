# Vérification des raccords avant R3.6

Le 29 octobre 2026, après le correctif de sélection du plan unique, le propriétaire demande si le raccord reprend réellement la fin du plan précédent, à quel plan s’applique la fin commune et pourquoi un modèle image est requis. R3.6 reste non commencé.

## Fonctionnement vérifié

Dans le parcours actuel, le raccord est une suite d’appels vidéo distincts. Le moteur attend le résultat du plan 1, extrait sa dernière image et la fournit comme cadre initial du plan 2 ; même opération du plan 2 au plan 3. Aucun plan suivant n’est soumis tant que le résultat précédent ou son extraction sont indisponibles. L’image de départ commune concerne le premier plan ; la fin commune est affectée au dernier. Des cadres spécifiques déjà affectés restent prioritaires et sont présentés dans le devis ; une image initiale explicite du plan 2 est refusée en mode raccord, puisqu’elle entrerait en conflit avec le clip précédent.

Cela aide la liaison visuelle, sans prolonger l’état interne du modèle, son mouvement ni son audio. Le fournisseur peut encore modifier l’apparence, introduire une coupe ou produire un mouvement incohérent. Les tests simulés ne valident pas une continuité audiovisuelle réelle. L’aide correspondante est désormais visible quand le raccord est choisi.

Choisir le raccord ne change pas automatiquement le point de départ. Le bouton explicite « Choisir un modèle image et sa référence » le change, comme l’import d’une image en mode texte. Les tests frontend couvrent le choix du raccord en texte et en personnage sans mutation de la source.

Le parcours garde un même modèle par vidéo. Son mode texte ne reçoit pas de cadre initial ; le mode personnage reçoit une référence d’apparence, pas un cadre imposé. Ces variantes ne peuvent donc pas recevoir l’image du clip précédent dans ce parcours : le serveur refuse cette combinaison. Un premier appel texte/personnage puis des variantes image serait un nouveau parcours avec modèles/références/tarifs à présenter explicitement dans le devis, pas une capacité déjà livrée. Aucun remplacement de modèle implicite ajouté.

## Correctif de la dernière image

L’extracteur prenait auparavant une image à partir de `durée − 0,15 s`. Il décode désormais la fin de la piste vidéo et conserve la dernière image décodée. Le fichier temporaire est réécrit à chaque image, puis importé dans le stockage privé. Si la fenêtre finale ne contient aucune image parce que le son dépasse la vidéo, une seconde lecture sans recherche temporelle utilise le reste du même budget de 20 secondes. Un échec garde l’erreur applicative et empêche l’appel vidéo suivant ; nettoyage temporaire inchangé.

Le comportement de recherche depuis la fin du fichier est documenté par [FFmpeg, option `-sseof`](https://ffmpeg.org/ffmpeg.html#Main-options). La durée du conteneur et celle de la piste vidéo peuvent différer ; le témoin avec audio plus long vérifie ce cas.

## Fichiers et tests

- `AiEpisodeFlow::advance()` et `AiPlanInputs::resolve()` : chaîne séquentielle, références effectives et fin commune déjà implémentées, aucune modification métier de ces méthodes.
- `AiFrameExtractor` : dernière image décodée et repli local borné ; aucun réseau ni nouvelle autorisation de coût.
- `preparation_controller.js` : aide de raccord visible et formulation fidèle à la limite des modes ; source et modèle conservés.
- `AiEpisodeFlowTest` : trois plans avec contrôle des images réellement attachées aux jobs suivants, égalité des empreintes avec l’extraction attendue, fin uniquement sur le dernier et absence d’envoi prématuré. Témoin de 60 images rouges sauf la dernière bleue, avec puis sans son plus long ; vérification du pixel par FFmpeg sans dépendance GD.

Premier ciblage : une dépendance GD indisponible et une fixture qui attendait un job avant son passage dans `advance()` ; correction des fixtures sans assouplir les assertions. Ciblage suivant : **5 tests / 52 assertions verts**, avant ajout du témoin audio plus long demandé par la revue indépendante. Ce témoin final passe **1 test / 7 assertions**. Première suite complète avant ce repli : **796 tests / 9 134 assertions verts** ; passage final : **796 tests / 9 137 assertions**, **176 frontend**, schémas/lints/PHPStan/style/check/build verts. Revue indépendante finale sans blocage ; avertissement Vite Three.js connu.

Recette UI sur page Symfony synthétique exportée, assets construits : choisir le raccord garde `source=text` et le modèle Kling 3, affiche l’aide de compatibilité et permet le bouton de changement explicite. Desktop/mobile 375 px et clavier, aucun débordement horizontal ni erreur console. Captures locales `artifacts/r3-light/linked-help-desktop.png` et `linked-help-mobile.png` ; aucune connexion réelle ni POST vidéo dans le navigateur.

Commandes : `npm run test:ui` (**176 tests verts**), `npm run check`, `npm run build`, `php scripts/check.php` dans `edit-worker` temporaire/test ; copie Linux des sources et du vendor, Kernel chargé vérifié, caches/variables locales exclus. Migrations de test vérifiées, schémas dev/test, YAML/Twig/conteneur, PHPStan/style et PHPUnit inclus. Suites DB exclusivement sur `shortflow_test`, sans concurrence.

La génération et les appels HTTP sont simulés, FFmpeg traite de petits médias synthétiques locaux. Aucune génération IA payante, qualité audiovisuelle réelle, publication, activation, migration applicative ou push. La vérification des variantes texte/personnage reste une limitation explicite, pas une promesse de prise en charge.

Cache dev et quatre workers actualisés après garde à zéro traitement actif. Commit local `fix: use the final decoded frame for clip bridges`. Prochain point exact : R3.6, non commencé ; grand R3 en cours, revue globale R3.7 à faire.
