# Feuille de route — Laravel OpenAPI Generator V1

Ce dossier suit la refonte du générateur, de la v0.6 à la version 1.0.0. Chaque épique a son document avec la liste
de ses livrables ; le tableau ci-dessous en est calculé automatiquement (`composer progress`).

**Architecture, concepts et patterns :** [architecture.md](architecture.md) — à lire avant toute contribution.
**Inventaire de la v0.6 (ce qu'on garde, réécrit ou abandonne) :** [legacy-inventory.md](legacy-inventory.md).
**Couverture de la spécification OpenAPI (ce qui est prévu, proposé, hors périmètre) :** [spec-coverage.md](spec-coverage.md).
**Librairie d'écriture :** `ark4ne/openapi-writer` (dossier voisin `../openapi-writer`), qui remplace
`goldspecdigital/oooas`. Son skill `skills/openapi-writer/` décrit l'API publique.

## Progression

<!-- progress:start -->
**Avancement global : `█████████░` 97 % (262 / 269 livrables)**

| Épique | Statut | Avancement | Livrables |
| --- | --- | --- | --- |
| [E0 — Branche, socle et outillage](E00-bootstrap.md) | En cours | `█████████░` 95 % | 22 / 23 |
| [E1 — Architecture et contrats](E01-contracts.md) | Terminé | `██████████` 100 % | 26 / 26 |
| [E2 — Découverte des routes](E02-route-discovery.md) | Terminé | `██████████` 100 % | 21 / 21 |
| [E3 — Métadonnées d'opération et types](E03-operation-metadata.md) | Terminé | `██████████` 100 % | 20 / 20 |
| [E4 — Requêtes et règles de validation](E04-requests.md) | Terminé | `██████████` 100 % | 29 / 29 |
| [E5 — Échantillonnage et inférence de schémas](E05-sampling.md) | Terminé | `██████████` 100 % | 20 / 20 |
| [E6 — Réponses](E06-responses.md) | Terminé | `██████████` 100 % | 32 / 32 |
| [E7 — Passes du pipeline](E07-pipeline-passes.md) | Terminé | `██████████` 100 % | 26 / 26 |
| [E8 — Écriture du document](E08-document-writing.md) | Terminé | `██████████` 100 % | 21 / 21 |
| [E9 — Intégration JSON:API](E09-json-api.md) | Terminé | `██████████` 100 % | 11 / 11 |
| [E10 — Intégration Laravel et expérience développeur](E10-laravel-dx.md) | Terminé | `██████████` 100 % | 12 / 12 |
| [E11 — Migration, documentation et version 1.0.0](E11-release.md) | En cours | `█████░░░░░` 53 % | 7 / 13 |
| [E12 — Webhooks et callbacks](E12-webhooks.md) | Terminé | `██████████` 100 % | 15 / 15 |
<!-- progress:end -->

## Mettre à jour la progression

1. Cocher le livrable dans le document de l'épique (`- [ ]` devient `- [x]`).
2. Lancer `composer progress` : le tableau ci-dessus est régénéré à partir des cases cochées.
3. Committer les deux fichiers ensemble. La CI (`php bin/progress.php --check`) échoue si le tableau n'est pas à jour.

Le statut est déduit des cases : aucune cochée = **À faire**, une partie = **En cours**, toutes = **Terminé**.

## Ordre des épiques

Chaque épique suit le même cycle que la librairie d'écriture : **contrat** (signatures et PHPDoc, corps neutres),
**tests rouges**, puis **implémentation**. Un épique ne commence son implémentation qu'une fois ses tests écrits.

```
E0 Socle ─► E1 Contrats ─► E2 Routes ─► E3 Opérations ─┬─► E4 Requêtes ───────────────┐
                                                      └─► E5 Échantillonnage ─► E6 Réponses ─┤
                                                                                       ▼
                                          E7 Passes ─► E8 Écriture ─► E12 Webhooks ─► E9 JSON:API ─► E10 Laravel ─► E11 Release
```

E4 et E5/E6 peuvent avancer en parallèle une fois E3 terminé.

1. [E0 — Branche, socle et outillage](E00-bootstrap.md)
2. [E1 — Architecture et contrats](E01-contracts.md)
3. [E2 — Découverte des routes](E02-route-discovery.md)
4. [E3 — Métadonnées d'opération et types](E03-operation-metadata.md)
5. [E4 — Requêtes et règles de validation](E04-requests.md)
6. [E5 — Échantillonnage et inférence de schémas](E05-sampling.md)
7. [E6 — Réponses](E06-responses.md)
8. [E7 — Passes du pipeline](E07-pipeline-passes.md)
9. [E8 — Écriture du document](E08-document-writing.md)
10. [E12 — Webhooks et callbacks](E12-webhooks.md) (ajouté après coup, réalisé après E8)
11. [E9 — Intégration JSON:API](E09-json-api.md)
12. [E10 — Intégration Laravel et expérience développeur](E10-laravel-dx.md)
13. [E11 — Migration, documentation et version 1.0.0](E11-release.md)

## Décisions

| Date | Décision |
| --- | --- |
| 2026-10-27 | `goldspecdigital/oooas` remplacé par `ark4ne/openapi-writer`, projet séparé et sans framework |
| 2026-10-27 | Étude de restructuration validée : Discovery → Metadata → Extraction → Passes → Écriture, `GenerationContext` explicite, registres d'extracteurs et de règles, chaîne d'échantillonnage, JSON:API sous `Integrations/` |
| 2026-10-28 | Refonte sur la branche `v1.0`, créée depuis `v0.6` ; le code v0.6 sert de référence fonctionnelle, pas de base de code |
| 2026-10-28 | Mêmes concepts et patterns que `openapi-writer` : classes `final`, objets de valeur immuables, pas de traits, pas d'état statique, contrats en interfaces, `phpat` (voir [architecture.md](architecture.md)) |
| 2026-10-28 | PHP `^8.3` (8.2 n'a plus que des correctifs de sécurité, jusqu'au 31/12/2026) ; Laravel `^13.0` (Laravel 12 n'a plus de correctifs de bugs depuis le 13/08/2026) ; Laravel 14 ajouté dès sa sortie |
| 2026-10-28 | `phpstan/phpdoc-parser` remplace `phpdocumentor/reflection-docblock` et `phpdocumentor/type-resolver` (génériques, array shapes, types conditionnels) |
| 2026-10-28 | `justinrainbow/json-schema` retiré : la validation passe par le `Validator` de `openapi-writer` (`opis/json-schema`) |
| 2026-10-28 | Version OpenAPI de sortie configurable par document, 3.1.2 par défaut ; mode strict par défaut |
| 2026-10-28 | Les schémas sont des `Schema` d'`openapi-writer` dès l'extraction : pas de modèle de schéma parallèle (fin de `Documentation\Request\Parameter`) |
| 2026-10-28 | Métadonnées par attributs PHP ; les balises PHPDoc `@oa-*` sont abandonnées (le résumé et la description restent lus dans la PHPDoc) |
| 2026-10-28 | Clé de configuration `versions` renommée `documents` (le mot « version » désigne aussi la version OpenAPI) |
| 2026-10-28 | Validé : Laravel `^13.0` seul et abandon des balises PHPDoc `@oa-*` au profit des attributs |
| 2026-10-28 | Tout code qui utilise `openapi-writer` s'écrit en suivant son skill (`skills/openapi-writer/`) |
| 2026-10-28 | Validé : `phpstan/phpdoc-parser` à la place de `phpdocumentor/*`, et clé `documents` à la place de `versions` |
| 2026-10-28 | Attribut `#[Operation]` conservé (alias d'import documenté) ; extensions enregistrées par la configuration et par les tags du conteneur |
| 2026-10-28 | Webhooks (3.1+) : `#[Webhook]` sur une action ou un contrôleur uniquement, sans liste ni découverte par dossier ; l'extracteur d'attributs range la route en opération ou en webhook ([E12](E12-webhooks.md)) |
| 2026-10-28 | Callbacks d'opération (3.0+) : `#[Callback]` répétable, payload partagé avec un webhook ou décrit par une ressource ou un type |
| 2026-10-28 | `x-webhooks` (avant 3.1) et `x-tagGroups` (avant 3.2) portés par `openapi-writer`, en option désactivée par défaut : livré par le writer (`VendorExtensions`, `09ec7af`), activé par la clé `vendor_extensions` du document |
| 2026-10-28 | Éléments de la spec ajoutés aux épiques ([spec-coverage.md](spec-coverage.md)), sauf priorité basse (`#[Link]`, exemples nommés, base64) |
| 2026-10-28 | Découverte (E2) : une `RouteDefinition` par méthode et par chemin ; masques `include` / `exclude` par URI, nom, domaine ou middleware ; `QUERY` et méthodes non standard retirées avant 3.2 en permissif, laissées au writer en strict |
| 2026-10-28 | Webhooks : `#[Webhook]` reste posé sur des routes réelles ; la documentation explique comment les protéger ou les désactiver |
| 2026-10-28 | `externalDocsDescription` ajouté à `#[Operation]` et `#[Tag]`, à côté de l'URL `externalDocs` |
| 2026-10-28 | Licence MIT (le fichier `LICENSE` était en GPL-3.0, `composer.json` en MIT) |
| 2026-10-28 | Exceptions : `@throws` lu sur l'action seulement ; réponse par chaîne (`#[Response]` sur la classe de l'exception → configuration → table de Laravel → classes HTTP de Symfony → échantillonnage de `HttpException`) ; rien d'inventé sans correspondance |
| 2026-10-28 | Métadonnées (E3) : `#[Operation]` sur un contrôleur donne des valeurs par défaut (sauf `tags` et `deprecated: true`) ; la PHPDoc l'emporte sur le type natif ; textes traduisibles par le contrat `Translator` |
| 2026-10-28 | Actions héritées : attributs fusionnés le long des contrôleurs (jusqu'au contrôleur de la route) et des redéfinitions de la méthode, l'enfant l'emportant ; PHPDoc héritée sans commentaire ou avec `{@inheritDoc}` |
| 2026-10-28 | Requêtes (E4) : un `RuleMapper` par comportement de règle ; ce qu'OpenAPI ne sait pas écrire devient une phrase de la description ; description manuelle par `DescribesRequest` et un `RequestDescriber` immuable |
| 2026-10-28 | Échantillonnage (E5) : transactions annulées dans un `finally`, `create()` seulement dans une transaction, graine par `mt_srand()` ; la PHPDoc et les casts priment sur la valeur échantillonnée pour le type |
| 2026-10-28 | Relations `whenLoaded()` : échantillonnées sur un niveau et décrites entièrement, facultatives (ressource exécutée sans puis avec les relations) |
| 2026-10-28 | Réponses (E6) : ressource exécutée sans puis avec ses relations, en composant ; 201 pour une ressource renvoyée par `POST` ; `#[Response]` sans corps garde le contenu inféré de son statut |
| 2026-10-28 | Passes (E7) : elles ne voient que la description ; groupe de tags, serveurs de domaine et `#[Security]` décidés à l'extraction ; réponses d'erreur partagées, ajoutées seulement si le statut manque ; corps d'erreur remplaçables (`ErrorBodies`) |
| 2026-10-28 | Tests : PHPUnit, Orchestra Testbench, fixtures JSON validées contre le schéma officiel ; couverture ≥ 90 % des lignes, MSI ≥ 80 % |
| 2026-10-28 | E10 : un document de 500 routes est généré, validé et écrit en 0,43 s, avec un pic de 68,7 Mo (`composer benchmark`, PHP 8.5, SQLite en mémoire) |
