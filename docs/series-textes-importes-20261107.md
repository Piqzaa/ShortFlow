# Aide aux textes des épisodes importés — 7 novembre 2026

## Objectif et parcours

Les séries composées de vidéos existantes disposent désormais de la même aide rédactionnelle que les épisodes IA. Depuis une série importée, « Style des textes » enregistre une langue (français/anglais) et un ton communs ; les consignes détaillées et listes de hashtags restent facultatives et repliées. Les textes déjà préparés ne sont pas réécrits quand ce style change.

Dans « Préparer la publication », l’utilisateur décrit ce que montre l’épisode puis clique sur « Enregistrer et proposer mes textes ». Le rédacteur local reçoit cette description, le style et les réseaux choisis. Il ne reçoit pas la vidéo et ne l’analyse pas. Un simple enregistrement du brouillon conserve aussi la description, sans demander de proposition. Un intitulé seul ou une description vide ne suffit pas pour démarrer la rédaction d’une vidéo importée.

Une proposition terminée reste séparée des textes finaux. L’utilisateur choisit les champs à appliquer ; un titre ou une légende existants ne sont pas cochés automatiquement. Si un champ a été retouché depuis la demande, son remplacement nécessite la case d’accord explicite. Les textes finaux restent modifiables dans les champs habituels. Les choix de date et les réglages sociaux sont conservés ; demander ou appliquer des textes n’arme jamais la publication et ne déclare pas une vidéo importée comme synthétique.

Le réordonnancement et le nom de la série ne changent pas la source rédactionnelle. Un nouveau style, une autre description enregistrée ou d’autres réseaux rendent l’ancienne proposition obsolète. Un épisode retiré de la série ne peut plus recevoir une nouvelle proposition ; ses textes existants restent conservés. Les brouillons déjà programmés ne peuvent pas être modifiés par ce parcours. Si le moteur local est indisponible, la rédaction manuelle reste utilisable.

L’ancienne aide des épisodes IA conserve sa source issue des scènes et son comportement de proposition automatique des textes manquants. Pour les vidéos importées, la demande est toujours déclenchée par le bouton explicite. La cadence et la confirmation groupée restent un lot ultérieur.

## Composants et notions Symfony

- `VideoSeries` conserve un profil rédactionnel JSON, comme `AiSeries`. `configureEditorial()` avance sa révision pour détecter une sauvegarde concurrente.
- `Version20261107020000` ajoute seulement `video_series.editorial_profile`, avec `{}` par défaut. Son retour arrière refuse de supprimer un profil renseigné.
- `PublicationEditorialController` utilise le même formulaire pour les deux types de séries. La nouvelle route `/studio/series/videos/{id}/textes` vérifie le propriétaire et dispose d’un jeton CSRF distinct ; la révision empêche une page ancienne d’écraser une nouvelle configuration.
- `PublicationComposerController` expose l’aide aux brouillons liés à un épisode importé et conserve leur description avant de traiter la demande ou l’application. La sauvegarde du brouillon précède la rédaction : les textes saisis restent enregistrés si le moteur refuse la demande.
- `PublicationEditorial` construit une empreinte de la vidéo, de la description, du style et des réseaux. Les verrous du propriétaire, du brouillon et de la série protègent l’application ; les empreintes des champs protègent les retouches humaines.
- `StoryProposals`, `LocalStoryWriter` et le contrôleur Stimulus de suivi existants sont réutilisés : aucune nouvelle file, dépendance, API payante ou boucle d’envoi.
- Les trois vues modifiées présentent le lien de style, le formulaire commun et les propositions dans le préparateur existant. Twig échappe les textes saisis/proposés.

Flux : route Symfony → formulaire et contrôle CSRF/propriétaire/révision → sauvegarde Doctrine → demande locale en file → résultat séparé → sélection et contrôle des empreintes → application aux champs du brouillon. La confirmation de publication reste une action distincte du moteur existant.

## Recette propriétaire

1. Ouvrir une série « Avec tes vidéos », puis « Style des textes » ; enregistrer la langue et le ton.
2. Sur un épisode, ouvrir « Préparer la publication » et sélectionner les réseaux souhaités.
3. Décrire le contenu en quelques mots ; enregistrer le brouillon puis le rouvrir pour vérifier la conservation sans rédaction automatique.
4. Demander les textes. Après le résultat, vérifier titre, légende et hashtags, puis appliquer seulement les champs souhaités.
5. Modifier un texte final et vérifier qu’il est conservé lors d’une nouvelle demande. Les dates restent choisies dans le préparateur habituel ; aucune confirmation réelle n’est nécessaire pour cette relecture.

## Vérifications et activation locale

Les tests fonctionnels utilisent exclusivement `shortflow_test`, un rédacteur synthétique et des clients fournisseurs/sociaux bloqués. Aucun média propriétaire n’est envoyé au rédacteur ni à un fournisseur.

- Nouveau banc importé complet : **7 tests / 75 assertions**, après la dernière sauvegarde du descriptif. Les passages intermédiaires ne sont pas additionnés.
- **PHPStan : quatre fichiers applicatifs**, **CS-Fixer : six fichiers PHP, zéro correction au contrôle final**, **39 Twig**, conteneur Symfony, schémas test/dev, `npm run check` et `npm run build` verts. Avertissement Vite sur la taille des chunks déjà existant ; aucun nouveau JavaScript/CSS ni nouvelle suite frontend exécutée.
- **14 états Chrome** : 320/390/768/1280 pour style, proposition prête et moteur désactivé, puis deux vues 390 sans JavaScript. Champs et réglages conservés, cases de remplacement explicites, clavier/consignes repliées, aucun formulaire imbriqué, débordement ou erreur console/JS, zéro POST. Deux captures mobiles relues.
- **10 GET authentifiés HTTP200** : Studio/création/liste/choix/créations de séries et pages/style des séries IA/import existantes, en transaction explicitement en lecture seule ; 29 compteurs inchangés, zéro tentative HTTP fournisseur.

Régression rédaction IA complète : **13 tests / 144 assertions**, y compris contexte requis, profil obsolète, retouches humaines, suivi/reprise et application depuis le préparateur. Régression série/publication complète : **6 tests / 102 assertions**, y compris sélection de source IA, conservation au déplacement importé, programmation et confirmation immédiate simulées. **Total des trois bancs distincts : 26 tests / 321 assertions**, tous verts. Pas de nouvelle suite PHP globale ni de revue indépendante prétendue.

Les premiers passages du nouveau banc ont révélé des défauts du banc : sélecteur `form` trop large (formulaire de déconnexion), objets Doctrine détachés après une requête et tentative de remplacer un service déjà initialisé. Ils ont été corrigés avant le passage final. Une première commande CS-Fixer multi-chemins sans `--config` a également été refusée ; la commande correctement configurée a ensuite réussi.

La migration additive est appliquée en test/dev : une requête applicative, 29 tables au total ; les compteurs et empreintes des anciennes données dev sont identiques avant/après. Aucun contenu propriétaire n’a été créé ou modifié. Le cache et les workers déjà actifs sont actualisés après contrôle d’inactivité. Aucun coût, génération réelle, déchiffrement de secret réel, upload fournisseur, publication réelle, push ou déploiement public.
