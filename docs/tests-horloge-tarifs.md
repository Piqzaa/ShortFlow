# Horloge des tarifs synthétiques — 2 novembre 2026

Les 35 erreurs et deux échecs documentés en R5.3/R5.4 provenaient de prix historiques vérifiés le 19 octobre et comparés à l’heure réelle, désormais au-delà des quatorze jours. Réactualiser artificiellement la date du catalogue ou retirer la protection aurait autorisé des estimations non revérifiées : aucun tarif ni date fournisseur n’a été modifié.

## Correction

AiCatalog centralise `priceExpired(checked)` et reçoit une horloge de prix. En fonctionnement normal, le service utilise NativeClock UTC. En test Symfony uniquement, `app.ai_price_clock` est un MockClock au 30 octobre 2026 à midi UTC, afin de conserver les fixtures historiques. La comparaison reste strictement antérieure à maintenant moins quatorze jours. Le parsing d’une date seule est explicitement UTC, même si le fuseau PHP est Europe/Paris.

Les trois contrôles AiGenerations (confirmation, reprise après clé, départ fournisseur) et celui d’AiEpisodeFlow utilisent cette même règle. Les vérifications de montant, consentement, propriétaire, clé, prompt et récupération d’une demande déjà transmise sont conservées. L’horloge des retraits de modèles et celle des publications ne sont pas figées par cette configuration. En construction directe avec une horloge déjà injectée, elle sert aussi aux prix si aucune horloge de prix distincte n’est fournie.

Tests de frontière : tarif encore valable exactement à quatorze jours, périmé une seconde après ; ancien prix refusé ; date UTC sous fuseau Paris ; Pika toujours retiré malgré l’horloge de prix synthétique.

## Preuves et limites

- Suite finale **825 tests PHP / 9 596 assertions**, en 2 min 15 s : shortflow_test migrée, copie Linux isolée sans `.env.local`, conteneur éphémère basé sur l’image locale `vydflow-edit` déjà installée, avec moteur de sous-titres hors réseau. Aucun worker lancé dans ce conteneur, aucun modèle téléchargé. Journal local `artifacts/contact/full-php-final.log`.
- Passage préparatoire dans l’image PHP simple : 824 / 9 432, 16 erreurs et un échec. Deux constructions directes de catalogue utilisaient encore l’heure réelle (repli d’horloge corrigé), quatorze erreurs venaient de fixtures documentaires omises dans la copie temporaire, un échec du moteur de sous-titres absent de cette image. Aucune exclusion de test : copie complétée et image appropriée, puis suite complète verte.
- 18 unitaires ciblés / 89 assertions (frontières et corpus narratif) verts. PHPStan, style, YAML/conteneur et schéma test vérifiés.
- Revue indépendante en lecture seule : quatre gardes et configuration examinées sans blocage ; suggestion UTC intégrée et testée. Aucun catalogue réel revérifié, aucune date fournisseur actualisée.

**Limite applicative maintenue : les modèles dont le tarif réel a expiré restent bloqués avant nouveau lancement.** Une future revérification des tarifs fournisseurs est un travail distinct. Ce correctif rend les tests déterministes, il ne rend pas les anciens tarifs actuels.

Aucun coût, appel vidéo/social/rédacteur externe, activation, migration applicative, redémarrage worker, déploiement ou push.