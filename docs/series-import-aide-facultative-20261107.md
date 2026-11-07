# Import : priorité aux textes manuels — 7 novembre 2026

Retour propriétaire : un import permet déjà d’écrire les textes de publication. Le style et les consignes IA exposés sur la page de série donnaient l’impression d’une configuration obligatoire.

Le lien « Style des textes » est retiré de la page de série importée. Dans le préparateur, l’ensemble de l’aide rédactionnelle est replié sous « M’aider à écrire les textes (facultatif) ». Le bloc reste ouvert si une description a été enregistrée ou si une demande existe, pour retrouver son travail. Les réglages langue/ton/hashtags sont accessibles dans « Personnaliser les propositions », à l’intérieur de cette aide. La page de réglage explique explicitement qu’ils ne sont pas nécessaires pour importer, écrire et programmer.

Les profils, descriptions, propositions et textes existants sont conservés. Aucune modification PHP applicative, moteur, règle d’autorisation, génération, prix ou publication. La partie IA garde son fonctionnement actuel : ce lot n’ajoute aucune génération payante ou publication sans relecture.

## Fichiers et flux Symfony

Trois vues Twig modifiées : `series/videos_show` retire le lien général ; `publication/_editorial` organise l’aide avec des `<details>` HTML natifs ; `series/editorial` précise le rôle facultatif des réglages pour l’import. Le contrôleur et le service sont inchangés. Twig conserve les mêmes champs de formulaire ; fermer un bloc ne retire pas ses valeurs du formulaire. Aucun nouveau JavaScript, CSS ou formulaire imbriqué.

Le banc fonctionnel existant exporte également sa vue initiale pour la recette navigateur. Les deux parcours existants enregistrement/application sont rejoués : **2 tests / 28 assertions** ; le passage supplémentaire de capture **1 / 11** les recouvre et ne s’additionne pas. Les **trois Twig** sont valides. Pas de nouvelle suite PHP globale, frontend ou build : aucun asset modifié.

**12 états Chrome** : 390/1280, série importée/préparateur initial/proposition prête, avec et sans JavaScript. Absence du lien sur la série, aide initialement fermée et reprise ouverte, personnalisation repliée, ouverture clavier et texte conservé après fermeture/réouverture ; aucun débordement/erreur console ou JS/POST, capture mobile de la série relue. Un premier banc automatisé au clic a expiré sur un élément instable ; le passage final au clavier avec mouvement réduit est celui rapporté. Cache web dev seul actualisé ; dix GET200 authentifiés en transaction de lecture seule, 29 compteurs égaux et zéro HTTP fournisseur. Aucun worker redémarré, migration, contenu propriétaire modifié, modèle réel, coût, publication, push ou déploiement public.

Recette propriétaire : ouvrir une série importée puis préparer un épisode. Écrire ses textes et enregistrer directement ; l’aide IA reste facultative. Ouvrir l’aide seulement si souhaité, puis « Personnaliser les propositions » pour accéder au style.

## Suite pour les séries IA

Direction demandée : préparer automatiquement titres/descriptions/hashtags à partir des scènes de chaque épisode, plutôt qu’exiger tous les textes avant sa création. Le futur calendrier doit séparer la préparation des textes, la génération vidéo payante et la confirmation de publication. Le choix relecture/publication automatique et les autorisations/coûts doivent être définis dans ce parcours, sans transformer les autorisations historiques consommées en accord permanent. Cette automatisation et la programmation groupée ne sont pas livrées par ce correctif.
