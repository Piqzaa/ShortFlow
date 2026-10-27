# Qualité actuelle du rédacteur — essais réels locaux du 27 octobre 2026

Pour la reprise sur un autre PC, les neuf cas et le diagnostic capturé sont regroupés dans [le corpus synthétique versionné](fixtures/redacteur-audit-20261027.json). Les chemins `artifacts` ci-dessous décrivent les archives locales originales, ignorées par Git ; ils ne sont pas indispensables pour reproduire les défauts connus.

## Méthode

Évaluation synthétique du service actuel `LocalStoryWriter`, commit `d3e4700`, Qwen3 8B déjà installé sur Ollama local. Aucun nouveau modèle téléchargé. Appels séquentiels directement au service PHP ; pas de base, compte, fournisseur vidéo, génération d'image, publication ou activation de série. Les propositions sont conservées sous `artifacts/audit-global-20261027-writer` ; les scripts de recette ignorés sont `artifacts/audit-global-writer.php` et `artifacts/audit-global-diagnostic.php`.

Six scénarios : quatre sujets repris du corpus existant, puis une demande de dialogues français et une danse. Trois plans par sujet, 5 secondes chacun sauf Milo en 10/15/15 secondes. Les sujets de répétition, les huit plans et la mémoire de série ne sont pas rejoués ici. Chaque scénario est exécuté une seule fois ; aucune amélioration statistique ni comparaison de modèles ne peut être déduite.

Trois essais éditoriaux demandent un titre/description YouTube et les légendes Instagram/TikTok avec leurs hashtags, en français : produit, petite histoire, import décrit uniquement par un titre. Ils exercent directement le service de texte avec des briefs synthétiques, pas le formulaire ni la construction complète de sa source. Le cas d'import est exploratoire : l'aide éditoriale automatique n'est pas actuellement exposée sur un import sans épisode lié. Un diagnostic complémentaire utilise ensuite une source plus proche du snapshot réel (`title`, `videoTitle`, `description`, `scenes`).

Les durées des neuf premiers essais sont des écarts d'horloge murale sur ce poste, premier appel potentiellement froid compris. Pas de préchargement isolé, de mesure réseau publique, de charge contrôlée ou d'horloge monotone enregistrée dans cette première série. Le diagnostic complémentaire mesure séparément murale et monotone. Ne pas utiliser ces durées comme SLA ni comme comparaison directe avec le benchmark préchauffé du 24 octobre.

Un relevé `/api/ps` pendant les essais indique `qwen3:8b`, contexte 4096, environ 5,99 Go de taille et 4,21 Go de VRAM utilisés par le modèle selon Ollama. Il ne constitue pas une mesure de capacité multi-utilisateurs ou une description complète du matériel.

## Résultats des scénarios

**6 réponses structurées exploitables sur 6, 18 plans.** Cinq ensembles de prompts sont en anglais sur cette lecture ; le cas dialogué reste en français. Une seule proposition comporte une alerte : Milo, pour couleur d'yeux inventée. Ce compte d'alertes ne reflète pas le nombre réel de problèmes.

| Sujet | Durée murale | Lecture humaine des sorties effectives |
| --- | ---: | --- |
| Milo découvre les objets | 145,443 s | Identité « black eyes » inventée, détectée. Livre qui tombe puis est laissé tomber de nouveau depuis le sol : transition physique peu claire et fin narrative faible. Relecture nécessaire. |
| Rempotage | 153,525 s | Sujet et résultat global respectés. Déplacement/répartition des racines décrits sans opérateur concret (« No human character ») : préciser les gestes/les mains avant vidéo. Aucune alerte. |
| Tartine | 167,051 s | Ordre beurre puis confiture et résultat final respectés. Plusieurs gestes dans le premier clip de 5 s ; description « verse » / prompt « spread » ne décrivent pas exactement le même mouvement. Aucune alerte. |
| Mug | 171,076 s | Sujet conservé, mais toucher la surface apparaît sans être demandé ; « froid » et « confortable » sont des propriétés difficiles à montrer par l'image seule. Répétitions de formulation. Aucune alerte. |
| Deux amis et le chat, paroles françaises exactes | 164,391 s | Résumé/scènes conservent les deux citations ; prompts entièrement français et sans les répliques. « Ses lèvres s'ouvrent pour parler » ne porte pas le texte demandé. Aucune alerte : défaut prioritaire. |
| Chat qui danse | 126,272 s | Début/danse/pose finale respectés en texte, prompts anglais. « Pattes en l'air » devient « legs in the air », ambigu ; aucune référence musicale ou de mouvement n'est transmise par ce test. Cela ne valide ni chorégraphie ni synchronisation réelle. |

Les six durées vont de 126 à 171 secondes ; médiane murale environ 159 secondes. Ce relevé décrit une attente vécue sur ce poste, pas le débit d'un service hébergé. Le transport asynchrone et la possibilité de quitter la page restent essentiels.

## Cas prioritaire : les dialogues ne font pas partie du contrat actuel

Demande : Léa dit « Tu as trouvé mon chat ? », Hugo répond « Oui, il dort sur ta chaise ! », puis ils regardent le chat roux. Les descriptions françaises reprennent ces répliques ; les prompts effectifs décrivent les regards et gestes sans les citer.

Deux problèmes se combinent :

1. Les consignes de `LocalStoryWriter.php:43` et `:63` interdisent les dialogues, tout en demandant de suivre un brief qui peut en contenir. Il faut définir explicitement ce que le produit accepte, pas laisser deux instructions contradictoires.
2. Les alertes de langue (`:169`) utilisent une liste courte et un seuil de trois mots français distincts. Dans chaque prompt de ce cas, deux mots seulement appartiennent à cette liste ; le préfixe répété ne compte pas davantage (`markers()`, `:214`). Aucun contrôle ne compare les répliques demandées aux prompts. La revue indépendante UX a confirmé ce mécanisme.

Une réponse structurée et `warnings: []` ne signifient donc ni « anglais respecté » ni « paroles conservées ». Le rendu peut donner confiance au créateur alors que les entrées du modèle ne portent pas sa demande.

Correction à cadrer : séparer scène visuelle, locuteur, texte exact et langue ; afficher ces éléments dans le récapitulatif ; alerter si le chemin choisi ne les porte pas. Un prompt anglais contenant volontairement une citation française exacte doit rester accepté. La conservation des citations dans le texte ne suffira toujours pas à valider l'audio : modèle, endpoint, voix, identité, langue et rendu réel demandent un essai distinct au budget choisi.

## Textes sociaux : premiers résultats

| Source synthétique | Durée murale | Résultat |
| --- | ---: | --- |
| Mug, titre et résumé | 172,318 s | `PublicationException`, aucune proposition renvoyée. Le premier script ne conserve pas le message ni la réponse brute : cause précise non attribuable à ce seul relevé. |
| Milo et la boîte, titre et résumé | 172,260 s | Même classe d'échec, aucune proposition renvoyée ; même limite de diagnostic. |
| « Vacances 2026 » seulement | 495,826 s | Pack accepté par le service, mais le modèle invente une préparation de voyage, des projets et l'absence de plan final. Ces faits ne figurent pas dans le titre. |

Le cas minimal produit notamment « Quelques idées en tête, mais pas encore de plan final ». Ce n'est pas une simple reformulation neutre du titre. Les hashtags sont structurellement acceptés, mais cette acceptation ne vérifie pas le fond. Il faut demander davantage de contexte factuel ou produire un texte neutre à relire, plutôt que combler les informations manquantes.

La durée de 495,826 s est atypique pour un appel dont le code configure `max_duration=180`. Aucun relevé monotone ni réponse brute n'a été conservé pour ce cas ; sa cause n'est pas établie. Ne pas en déduire un nouveau délai garanti, un contournement confirmé du timeout, ni un débit. Ce point justifie la mesure complémentaire instrumentée plutôt qu'une comparaison directe des temps.

## Diagnostic éditorial complémentaire : réponse complète mais non conforme

Un quatrième essai éditorial sur le mug, avec une source structurée (`title`, `videoTitle`, `description`, trois `scenes`) et capture de la réponse locale, confirme un défaut concret. Le serveur Ollama retourne un JSON complet pour les trois réseaux : `done=true`, `done_reason=stop`, 617 tokens générés. Le délai est de 76,491 s à l'horloge murale, 77,555 s à l'horloge monotone, et environ 76,597 s annoncées par Ollama. Ce cas n'est donc pas un résultat tronqué à la limite de sortie.

La description YouTube contient une longue accumulation de hashtags, en plus du tableau séparé de cinq hashtags. Certains sont répétés ou hors sujet, comme une référence au jardin pour le mug. `PublicationTexts::normalize()` refuse correctement le champ contenant `#` et renvoie : « Les hashtags doivent rester séparés dans la réponse du rédacteur. » Le service ne donne pas de pack à appliquer ; il ne faut pas enlever cette validation pour masquer l'échec.

Preuves synthétiques : `text-diagnostic.json` pour l'erreur et les deux horloges, `text-diagnostic-response.json` pour l'enveloppe et le contenu. Le client HTTP de diagnostic transmet uniquement à l'adresse Ollama locale autorisée, capture cette réponse synthétique puis la fournit au service inchangé. Aucune API vidéo ou sociale n'est appelée.

**Bilan éditorial : un pack normalisé sur quatre tentatives, et ce pack accepté invente un contexte à partir d'un titre pauvre.** Ce petit échantillon ne permet pas d'estimer un taux d'échec général ; il suffit à refuser une conclusion de fiabilité déjà acquise. La cause des deux premiers échecs reste non établie faute de capture ; seul le quatrième est attribué précisément aux hashtags intégrés à la description.

## Conclusion et critères du prochain lot

L'assistance locale produit des bases utiles à éditer. **Elle n'est pas validée pour une génération payante autonome ni pour appliquer sans relecture les textes sociaux.** La normalisation de structure et les contrôles de provenance protègent l'application, mais ne suffisent pas à rendre les scénarios fidèles.

Priorités proposées :

- Fiabiliser la production d'un pack éditorial complet et classer les échecs sans perdre les textes humains. En cas d'échec, garder une rédaction manuelle possible ; aucune relance facturée ou publication automatique.
- Conserver les contraintes explicites : identité, objets, gestes, ordre, résultat, durée, langue et éventuelles paroles. Le cas de dialogue observé doit au minimum déclencher une relecture avant coût.
- Ne pas inventer un contexte de publication à partir d'un titre trop vague. Expliquer que le rédacteur ne voit pas la vidéo.
- Distinguer demande en attente et exécution : la reproduction en mémoire de l'audit technique confirme qu'une rédaction `queued` de 11 minutes devient `failed` avant inférence. Une limite de temps ne doit pas être présentée comme une mesure de qualité.
- Rejouer un corpus fixe après correction, avec quelques répétitions et une grille humaine indépendante : fidélité, actions filmables, langue, paroles, cohérence scène/prompt, exactitude des légendes et corrections nécessaires. Évaluer séparément succès structurel, qualité éditoriale, alertes et durée.

Les changements ne sont pas implémentés dans cet audit. Aucun nouveau modèle ou fournisseur n'est choisi par défaut ; aucune vidéo n'a été créée pour valider les descriptions, voix ou mouvements.
