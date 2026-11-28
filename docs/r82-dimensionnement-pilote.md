# R8.2 — dimensionnement du pilote VydFlow

## Mise à jour du 28 novembre 2026

Le configurateur OVH affiche le VPS-2 mensuel à **10,19 € TTC** avec sauvegarde standard incluse, à Erith, au Royaume-Uni. C'est le seul emplacement proposé pour ce modèle lors de la vérification. À Gravelines, le VPS-3 sans engagement avec Debian 13 affiche **14,69 € TTC** avec sauvegarde standard incluse. Ces montants viennent du récapitulatif public de configuration. Le panier final et le renouvellement restent à vérifier. [Configurateur OVH](https://www.ovhcloud.com/fr/vps/configurator/?planCode=vps-2027-model2)

Cinq comptes au plafond Abonné représentent **15 Go de bibliothèque et jusqu'à 5,24 Go de fichiers temporaires**, selon les limites de `VideoLibrary`. Avec 1 Gio pour la base et les autres données, une sauvegarde complète représente environ **21,32 Go**, soit **149,22 Go** pour sept copies. Au tarif Scaleway Paris Standard Multi-AZ de 0,01606 € HT par Go et par mois, cela revient à **2,88 € TTC par mois** en régime établi. À 100 Mbit/s soutenus, le transfert d'une copie prendrait environ 28 minutes. Mesurer la taille du stockage complet, notamment les données de montage, les références et les vignettes, ainsi que le débit sur l'hôte. [Tarifs Scaleway](https://www.scaleway.com/en/pricing/storage/)

| Cible | VPS TTC par mois | Total TTC estimé avec sept copies |
| --- | ---: | ---: |
| PulseHeberg ECO-8, France, 4 vCPU, 8 Go RAM, 80 Go NVMe | 6,00 € au tarif public | **8,88 €** |
| OVH VPS-2, Erith, 4 vCPU, 8 Go RAM, 75 Go NVMe | 10,19 € au configurateur | **13,06 €** |
| OVH VPS-3, Gravelines, 6 vCPU, 12 Go RAM, 100 Go NVMe | 14,69 € au configurateur | **17,56 €** |

PulseHeberg ECO-8 est la cible proposée si la France reste le choix pour le pilote de cinq comptes. L'offre est mensuelle, compatible Docker et propose Debian 13. Le CPU est partagé, les snapshots et la haute disponibilité sont absents. La sauvegarde chiffrée hors serveur et la mesure en charge restent donc prévues. Le prix et le stock ECO-8 restent à confirmer au panier. Les totaux sont calculés avant arrondi, hors renouvellement du domaine, frais éventuels et services facultatifs. [PulseHeberg Economy Cloud](https://pulseheberg.com/fr/cloud/vps-economy) · [Installation Linux et recette](r8-pilote-linux.md)

## Estimation historique du 23 novembre 2026

**Proposition au 23 novembre 2026.** Démarrer avec cinq comptes au total et un VPS-2 OVHcloud x86 en France si disponible, au mois sans engagement. Le disque local sert aux médias privés. Une sauvegarde chiffrée va chez un second prestataire, en région Paris. Compter **13,96 à 14,55 € TTC par mois** si les cinq comptes remplissent leur quota, avec sept sauvegardes quotidiennes complètes. Le total est calculé aux tarifs publics, hors renouvellement du domaine et services facultatifs. Le panier reste à confirmer. [Catalogue OVH France](https://eu.api.ovh.com/1.0/order/catalog/public/vps?ovhSubsidiary=FR) · [Prix Scaleway Paris](https://www.scaleway.com/en/pricing/storage/)

### Coût mensuel de la bêta fermée

| Poste | Hypothèse | Montant TTC |
| --- | --- | ---: |
| OVHcloud VPS-2 2027, mode mensuel `default` | 4 vCores, 8 Go de RAM, 75 Go NVMe | 10,19 € |
| Sauvegarde standard OVH | La vitrine l’annonce incluse. Le catalogue sépare une option à 0,50 € HT. Réserve jusqu’à confirmation du panier. | 0 à 0,60 € |
| Sauvegarde hors serveur | Scaleway Standard Multi-AZ Paris, 7 × 26 Gio, soit environ 195,42 Go moyens sur un mois de régime établi, à 0,01606 €/Go HT | 3,77 € |
| Emails de confirmation et récupération | Brevo SMTP sur l’offre gratuite pour le pilote, si le domaine et l’expéditeur sont acceptés et la délivrabilité vérifiée | 0 € |

Les 26 Gio par copie supposent jusqu’à 25 Gio de bibliothèque et une réserve d’environ 1 Gio pour la base et les autres fichiers sauvegardés. Mesurer ces autres données avant ouverture. Les MP4/WebM sont déjà compressés. Le calcul compte sept copies complètes, sans supposer de gain de compression ou de déduplication. Une huitième copie conservée tout un mois porterait la borne haute à 15,09 € TTC. Le prix historique « à partir de 8,65 € TTC » du VPS-2 correspond au tarif avec engagement de douze mois. Les tarifs calculés ici utilisent une TVA de 20 %. [VPS OVH France](https://www.ovhcloud.com/fr/vps/) · [Catalogue public OVH](https://eu.api.ovh.com/1.0/order/catalog/public/vps?ovhSubsidiary=FR) · [Stockage Scaleway](https://www.scaleway.com/en/pricing/storage/) · [Offre Brevo](https://help.brevo.com/hc/fr/articles/208580669-FAQ-Quels-sont-les-limites-de-l-offre-Gratuit)

### Capacité et trafic

Le quota VydFlow est de 5 Gio et 100 vidéos par compte, avec 100 Mio et trois minutes au maximum par fichier. Le disque de 75 Go représente environ 69,85 Gio avant formatage. Au seuil d’alerte de 80 %, il reste environ 30,88 Gio pour la bibliothèque après une enveloppe prudente de 25 Gio pour Linux, Docker, PostgreSQL, les journaux et le travail temporaire. Cinq comptes pleins consomment 25 Gio. La réserve de 25 Gio est une hypothèse à vérifier sur le VPS. Sur le PC, les trois images Docker totalisaient environ 0,49 Gio, et les six conteneurs de répétition environ 154 Mio de RAM au repos, hors hôte. La mesure locale sert uniquement de repère pour le socle.

| Comptes | Vidéos au quota maximal | Disque prévu | Sauvegardes complètes hors machine | Budget mensuel TTC prudent |
| --- | ---: | --- | ---: | ---: |
| **5 au départ** | 25 Gio | NVMe 75 Go compris dans le VPS | 182 Gio | **14,55 €** |
| 10 | 50 Gio | Volume additionnel de 100 Go pour les médias | 357 Gio | 23,35 € |
| 50 | 250 Gio | Volume additionnel de 500 Go pour les médias | 1 757 Gio | 72,99 € |

Les deux scénarios d’extension isolent les médias sur un volume supplémentaire et conservent le disque système pour l’application et le travail temporaire. Les coûts gardent le même prix de calcul pour isoler l’effet du stockage. Le débit réel du VPS devra être mesuré avant d’élargir la cohorte. La sauvegarde automatique OVH ne couvre que le disque système. La comparaison n’inclut aucun achat de volume. [Options du catalogue OVH](https://eu.api.ovh.com/1.0/order/catalog/public/vps?ovhSubsidiary=FR) · [Périmètre de la sauvegarde OVH](https://www.ovhcloud.com/fr/vps/vps-backup/)

Pour le trafic, prendre par exemple 30 vidéos de 50 Mio par compte et par mois, chacune lue trois fois et envoyée vers deux destinations. Cela donne environ **7 Gio entrants et 37 Gio sortants** à cinq comptes. Les téléchargements supplémentaires, reprises et exports s’ajoutent. Les sauvegardes complètes dominent le trafic si le quota est plein. Une copie par jour de 26 Gio donne **780 Gio sortants sur 30 jours** et un transfert d’environ 37 minutes à 100 Mbit/s soutenus. OVH affiche un trafic illimité pour cette gamme en Europe. Scaleway inclut l’entrée et affiche 75 Go sortants inclus par mois, puis 0,01 €/Go HT. Une restauration de 26 Gio représente environ 28 Go sortants du stockage. [Trafic VPS OVH](https://www.ovhcloud.com/fr/vps/) · [Flux et tarifs Scaleway](https://www.scaleway.com/en/pricing/storage/)

### Montée en charge et mise en service

- Pour la première cohorte, privilégier import, programmation, publication et suivi. Réserver le montage avec FFmpeg à un seul rendu à la fois et mesurer le web et les publications pendant ce rendu. Laisser l’aide aux légendes via Ollama et la transcription désactivées pour la première mesure. Le fichier Qwen3 8B pèse 5,2 Go à lui seul. La RAM dépend aussi du contexte et du parallélisme, et reste à mesurer avec l’application sur le VPS. [Modèle Ollama](https://ollama.com/library/qwen3:8b) · [Mémoire et parallélisme Ollama](https://docs.ollama.com/faq)
- Porter la sauvegarde Windows de répétition vers la cible Linux. Tester une copie chiffrée vers le stockage hors machine, ainsi que le débit, la reprise sur interruption et la restauration. Prévoir un instantané ou une copie incrémentale si une archive complète immobilise les écritures trop longtemps. Éviter de réserver sept archives complètes sur le NVMe de travail. Les secrets et clés restent dans une sauvegarde séparée.
- Avant d’élargir la cohorte, mesurer espace et trafic réellement consommés. Au-delà de l’enveloppe disque, comparer un volume additionnel à un stockage objet privé pour les médias actifs, avec sauvegarde distincte. Cette seconde option demande une intégration pour la lecture, les imports, le montage et les liens temporaires des plateformes. [Stockage objet OVH](https://www.ovhcloud.com/fr/public-cloud/prices/)
- Vérifier au panier le prix au mois, les frais éventuels, la région et le premier renouvellement. Le domaine déjà acheté reste à renouveler au tarif du registrar. Préparer l’image de production, la configuration HTTPS, un compte fictif et le test de restauration avant d’inviter les premiers comptes.
