# Inventaire de la v0.6

La V1 repart d'un `src/` vide : aucun fichier de la v0.6 n'est recopié tel quel. Ce document dit, pour chaque
composant, ce qu'on **garde** (le comportement, réécrit selon [architecture.md](architecture.md)), ce qu'on
**refond** (le besoin reste, la solution change) et ce qu'on **abandonne**. La v0.6 reste consultable sur la
branche `v0.6` ; ses deux documents attendus (`tests/Feature/expected/`) servent de référence fonctionnelle aux
scénarios de la V1.

## Composants

| Composant v0.6 | Rôle | Décision V1 | Épique |
| --- | --- | --- | --- |
| `Console/Generator` | Commande, transactions, langues, écriture, validation | **Refondu** : commande mince dans `Laravel/Console/` ; transactions → `SamplingSession` ; écriture → `Output` | E8, E10 |
| `Documentation/DocumentationGenerator` | Parcours des routes, construction oooas, tags, groupes | **Refondu** : `Discovery` + `Generator` + `DocumentBuilder` ; tags et groupes → passe | E2, E7, E8 |
| `Documentation/DocumentationEntry` | Contrôleur, action, PHPDoc, attribut `#[Response]`, nommage | **Refondu** : `RouteDefinition` + `OperationExtractors` + stratégies de nommage | E2, E3 |
| `Documentation/ComputeEntry` | Choix du parser par carte de classes ordonnée, transformateurs | **Refondu** : registres à priorités | E1, E4, E6 |
| `Documentation/Request/Parameter`, `Parameters`, `Concerns/*` | Modèle de paramètre maison, conversion oooas, modes `flat` | **Abandonné** : `Schema` du writer + `ParameterDescription` ; le mode `flat` est **gardé** comme stratégie de sérialisation des query | E4 |
| `Documentation/Request/Component` | Registre statique des composants | **Refondu** : `ComponentRegistry` dans le contexte | E1, E7 |
| `Documentation/Request/Security`, `SecurityRequirement` | Schémas de sécurité | **Refondu** : `SecurityDescription` + objets du writer | E7 |
| `OAS/Objects/*` | Surcharges d'objets oooas | **Abandonné** (le writer couvre le besoin) | — |
| `Parsers/Requests/RequestParser`, `RulesParser`, `RuleParser` | Lecture de `rules()` d'une `FormRequest` | **Refondu** : `FormRequestExtractor` + `RuleParser` | E4 |
| `Parsers/Requests/Concerns/Rules/CommonRules` (~80 règles) | Traduction des règles en contraintes | **Gardé** (comportement), un `RuleMapper` par règle ; complété des règles Laravel 11 → 13 | E4 |
| `Parsers/Requests/Concerns/Rules/CustomRules`, `Contracts/CustomRuleParser*` | Règles personnalisées | **Refondu** : `RuleMapper` public + attribut `#[DescribesRule]` sur la règle | E4 |
| `Parsers/Requests/DescribedRequestParser`, `Descriptors/Requests/*`, `Contracts/Documentation/DescribableRequest` | Description manuelle fluide d'une requête | **Gardé** (besoin), API refaite sur la description V1 | E4 |
| `Parsers/Responses/JsonResourceParser`, `ResourceCollectionParser`, `Concerns/Resource` | Exécution de la ressource sur un modèle échantillon | **Gardé** (principe), refondu en extracteurs + `Sampling` | E5, E6 |
| `Parsers/Responses/JsonResponseParser`, `ResponseParser`, `RedirectResponseParser` | Réponses génériques | **Gardé** | E6 |
| `Parsers/Responses/BinaryFileResponseParser`, `StreamedResponseParser` | Fichiers et flux | **Gardé**, complété (`streamJson`, `eventStream` en `itemSchema` 3.2) | E6 |
| `Parsers/Responses/JsonApi*`, `Concerns/JAResource*`, `Parsers/Rules/IncludesRuleParsers`, `FieldsRuleParsers` | Intégration JSON:API | **Gardé**, déplacé dans `Integrations/JsonApi/` | E9 |
| `Parsers/Common/EnumToRef` | Enums en composants `$ref` | **Gardé**, dans `ComponentExtractionPass` | E7 |
| `Attributes/Response` | Statut, en-têtes, pagination | **Refondu** : répétable, typé, sans branche morte (`statusCode` toujours `int`) | E3, E6 |
| `Attributes/Request` (non suivi par git, jamais lu) | — | **Abandonné** : remplacé par `#[RequestBody]` / `#[QueryParameter]`… | E3 |
| `Attributes/ResourceFactory`, `Attributes/Id` | Factory d'échantillon, identifiant de composant | **Gardés** (sémantique), renommés si besoin à la revue du contrat | E3, E5 |
| Balises PHPDoc `@oa-response-*`, `@oa-description` | Métadonnées en PHPDoc | **Abandonnées** au profit des attributs ; résumé et description restent lus dans la PHPDoc | E3 |
| `Support/Reflection`, `Reflection/Type` | Types natifs et PHPDoc (`phpdocumentor`) | **Refondu** : `Types/` sur `phpstan/phpdoc-parser` | E3 |
| `Support/Fake` | Faux objet universel | **Gardé** (principe), `Sampling\FakeObject` typé | E5 |
| `Support/Pagination/PaginatorFactory` | Pagination simulée | **Gardé**, étendu aux trois paginateurs (`LengthAware`, simple, curseur) | E6 |
| `Support/Config` | Accès statique à la configuration | **Abandonné** : `Configuration` typée dans le contexte | E1 |
| `Support/ArrayCache`, `Support/Facades/Logger`, `Service/Logger` | Cache statique, journal statique | **Abandonnés** : caches d'instance, `Diagnostics` + rendu console | E1, E10 |
| `Support/Trans`, `Translator` | Génération multilingue | **Gardé** (besoin), port de traduction injecté | E8 |
| `Support/Date`, `MimeType`, `Http`, `Arr`, `ArrayInsensitive`, `ClassHelper`, `Support`, `Ref` | Utilitaires | Repris au cas par cas, sinon remplacés par `Illuminate\Support` | — |
| `Transformers/Middlewares/*` | Sécurité Bearer, CSRF, XSRF par middleware | **Gardés** (comportement), en `MiddlewareMapper` utilisés par une passe | E7 |
| `Providers/LaravelOpenApiProvider` | Fournisseur de services | **Refondu** : `OpenApiServiceProvider` | E10 |
| `config/openapi.php` | Configuration | **Refondue** : `documents`, `extensions`, version OpenAPI par document ; guide de migration | E1, E11 |

## Problèmes connus de la v0.6 corrigés par construction

- Ordre des parsers dépendant de l'ordre d'un tableau de configuration.
- États statiques partagés entre deux documents d'une même exécution (configuration, cache, composants).
- Traits imbriqués (`Concerns/`) qui rendent le flux de données illisible.
- `operationId` construit sans garantie d'unicité (`"$method:$routeName"`).
- Réponse 422 ajoutée sans schéma ; aucune réponse 401, 403, 404 ou 429.
- `#[Response(statusText: 'Created')]` produit un 200 : la branche `statusCode === null` est morte.
- Paramètres optionnels de chemin (`{id?}`) non gérés.
- Sortie limitée à OpenAPI 3.0.2.
