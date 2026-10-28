# Architecture, concepts et patterns

Ce document fixe les concepts et les patterns du générateur V1. Ils reprennent ceux d'`openapi-writer`
(`../openapi-writer/docs/architecture.md`) et les adaptent à un paquet Laravel. Toute contribution les suit ; un
écart se discute et se consigne ici avant d'être codé.

**Idée directrice :** le générateur **observe** l'application (routes, contrôleurs, requêtes, ressources) et en
produit une **description** neutre ; des **passes** la complètent ; `openapi-writer` **écrit**. Aucune couche ne fait
le travail d'une autre : un extracteur ne connaît pas le document final, l'écriture ne lit jamais l'application.

## 1. Flux de génération

```
Router ──► Discovery ──► RouteDefinition[] ──► Extraction ──► OperationDescription[] ──► Passes ──► DocumentBuilder ──► OpenApi ──► Output
              │                                   │    ▲                                  │              │            (writer)
              │                                   │    └── Sampling (instances, schémas)  │              │
              └──────────────── GenerationContext : configuration du document, Diagnostics, Components ──┘
```

1. **Discovery** lit le routeur et produit un `RouteDefinition` par route retenue (URI, méthodes, action, middlewares,
   contraintes `where`, liaisons de modèles). Rien d'autre ne lit le routeur.
2. **Extraction** transforme chaque route en `OperationDescription` : métadonnées (résumé, tags, attributs), requête
   (paramètres, corps, sécurité), réponses. Chaque aspect est traité par un registre d'extracteurs.
3. **Sampling** fournit aux extracteurs de réponses des instances représentatives (factories, enums, faux objets)
   et infère des schémas à partir des valeurs et des types.
4. **Passes** travaillent sur l'ensemble des opérations : sécurité tirée des middlewares, réponses d'erreur,
   composants et `$ref`, tags et groupes, `operationId` uniques.
5. **DocumentBuilder** traduit la description en objets `openapi-writer` ; **Output** écrit le fichier (JSON ou
   YAML, version OpenAPI de la configuration) et rend compte de la validation.

## 2. Couches et règle de dépendance

```
Support ← Types ← Attributes ← Configuration ← Description ← Context ← Discovery ← Sampling ← Extraction ← Generation ← Output ← Integrations ← Laravel
                                       (Exceptions : utilisables par toutes les couches)
```

| Couche | Dossier | Contenu |
| --- | --- | --- |
| Support | `src/Support/` | Briques sans dépendance interne (`HttpStatus`, `MediaTypes`, chaînes et tableaux) |
| Types | `src/Types/` | Système de types : arbre `Type`, `TypeResolver` (réflexion + PHPDoc via `phpstan/phpdoc-parser`) |
| Attributes | `src/Attributes/` | Attributs PHP publics posés par l'utilisateur (`#[Operation]`, `#[Response]`…) |
| Configuration | `src/Configuration/` | `Configuration`, `DocumentConfiguration` : la configuration typée et validée |
| Description | `src/Description/` | Modèle intermédiaire immuable (`OperationDescription`, `RequestDescription`…) |
| Context | `src/Context/` | `GenerationContext`, `Diagnostics`, `ComponentRegistry` |
| Discovery | `src/Discovery/` | `RouteCollector`, `RouteDefinition`, filtres de routes |
| Sampling | `src/Sampling/` | `Sampler` (chaîne), `SamplingSession` (transactions), `SchemaInferrer` |
| Extraction | `src/Extraction/` | Registres d'extracteurs : `Operations/`, `Requests/`, `Rules/`, `Responses/` |
| Generation | `src/Generation/` | `Generator` (façade), `Pass` + `Passes/`, `DocumentBuilder` |
| Output | `src/Output/` | `DocumentWriter` (disque, JSON / YAML), rapport de validation |
| Integrations | `src/Integrations/` | `JsonApi/` (`ark4ne/laravel-json-api`), `Sanctum/`… : extensions livrées |
| Laravel | `src/Laravel/` | `OpenApiServiceProvider`, commandes Artisan, chargement de la configuration |
| Exceptions | `src/Exceptions/` | Hiérarchie unique d'exceptions |

- Une couche ne dépend que des couches à sa gauche. Vérifié par `phpat` (`tests/Architecture/ArchitectureTest.php`).
- **Laravel :** toutes les couches peuvent dépendre des **contrats** `Illuminate\Contracts\*` et des classes qu'elles
  documentent (`FormRequest`, `JsonResource`, `Route`…). Seule la couche `Laravel` utilise les façades, les helpers
  globaux (`config()`, `app()`, `request()`…) et le conteneur. Vérifié par `phpat` et
  `spaze/phpstan-disallowed-calls`.
- **openapi-writer :** `Description`, `Extraction`, `Sampling` et `Generation` utilisent `Schema`, `SecurityScheme`
  (feuille autonome, rangée telle quelle dans les composants) et les enums du writer ; seuls
  `Generation\DocumentBuilder` et `Output` construisent les autres objets du writer (`OpenApi`, `PathItem`,
  `Operation`…). Le writer est la seule façon d'écrire de l'OpenAPI.
- `Integrations` n'est jamais une dépendance du cœur : une intégration s'enregistre par la configuration ou par
  le fournisseur de services, et son paquet tiers reste en `suggest`.

## 3. Le modèle de description

### 3.1 Objets de valeur immuables

`src/Description/` contient le modèle intermédiaire : ce que l'extraction a compris d'une opération, avant toute
décision d'écriture. Patterns : **Value Object immuable**, **Fluent Interface**, **Named Constructor**.

```php
final readonly class ParameterDescription extends Description
{
    protected function __construct(
        public string $name,
        public ParameterLocation $in,
        public Schema $schema,
        public bool $required = false,
        public ?string $description = null,
        public bool $deprecated = false,
    ) {}

    public static function path(string $name, Schema $schema): self
    {
        return new self($name, ParameterLocation::Path, $schema, required: true);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }
}
```

Règles :

1. **Classes `final readonly`**, héritant de la base abstraite `Description`, qui porte
   `copy(mixed ...$changes): static`. Aucune autre classe abstraite dans `Description/`.
2. **Construction** par constructeurs nommés (`ParameterDescription::path()`, `ResponseDescription::ok()`) ; le
   constructeur est `protected`. Un objet de description n'est jamais construit dans la valeur par défaut d'un
   paramètre d'une autre classe (le constructeur protégé n'y est pas accessible) : le constructeur nommé le passe.
3. **Modification** : chaque méthode renvoie une copie (`copy()`) ; l'objet courant ne change jamais.
4. **Collections** : comme dans le writer, un setter qui remplace (`parameters(...)`) et `add` + le nom exact du
   champ qui ajoute (`addParameters(...)`), tous deux variadiques et renvoyant une copie. **Maps** indexées par un
   nom (types de média, composants) : un setter au pluriel qui remplace tout (`contents(array)`), un setter au
   singulier qui pose ou remplace une entrée (`content('application/json', $schema)`) et `without*()` qui la retire.
5. **Schémas** : un champ de schéma est un `Ark4ne\OpenApiWriter\Objects\Schema`, jamais un tableau ni une classe
   maison. Les règles de validation et l'inférence construisent directement ce `Schema`.
6. **Pas de version OpenAPI dans la description** : elle décrit le modèle de la dernière version (`nullable()`,
   plusieurs types, `const`…) ; le writer convertit à l'écriture.

### 3.2 Ce que contient une opération

| Objet | Contenu |
| --- | --- |
| `OperationDescription` | Route d'origine, méthode HTTP, `operationId`, résumé, description, tags, `deprecated`, requête, réponses, sécurité, extensions |
| `RequestDescription` | `ParameterDescription[]` (chemin, query, en-têtes, cookies), `BodyDescription` (types de média → `Schema`), présence de règles de validation |
| `ResponseDescription` | Code de statut, description, en-têtes, contenus (type de média → `Schema`), pagination, origine (inférée, attribut, passe) |
| `SecurityDescription` | Schéma de sécurité requis et scopes |
| `DocumentDescription` | Toutes les opérations d'un document, ses tags, ses schémas de sécurité ; entrée et sortie des passes |

### 3.3 `GenerationContext`

Un `GenerationContext` par document généré, passé **explicitement** à chaque extracteur, sampler et passe :

- `DocumentConfiguration` du document en cours (aucune lecture de `config()` hors de la couche `Laravel`) ;
- `Diagnostics` : avertissements et erreurs, chacun avec la route et l'étape concernées ;
- `ComponentRegistry` : schémas nommés rencontrés (ressources, enums), avec détection des collisions de noms ;
- la locale courante (génération multilingue).

Il remplace les états statiques de la v0.6 (`Config::version()`, `ArrayCache`, `Logger`, `Component::$components`).
Il n'est pas un conteneur de services : les dépendances arrivent par les constructeurs.

## 4. Extraction

### 4.1 Registres d'extracteurs

Patterns : **Chain of Responsibility** + **Registry**. Chaque aspect a son interface et son registre :

```php
interface ResponseExtractor
{
    public function supports(Type $type, OperationContext $operation): bool;

    /** @return list<ResponseDescription> */
    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array;
}
```

| Registre | Entrée | Sortie | Exemples |
| --- | --- | --- | --- |
| `OperationExtractors` | `OperationContext` | `OperationDescription` enrichie | PHPDoc, `#[Operation]`, `#[Tag]`, stratégies de nommage |
| `RequestExtractors` | `OperationContext` | `RequestDescription` enrichie | Paramètres de chemin, `FormRequest`, `DescribableRequest`, attributs |
| `RuleMappers` | une règle de validation | modification d'un `Schema` | `RequiredRule`, `EnumRule`, `PasswordRule`, `FileRule` |
| `ResponseExtractors` | type de retour de l'action | `ResponseDescription[]` | `JsonResource`, `ResourceCollection`, `JsonResponse`, fichiers, flux |

- `OperationContext` porte la route, la méthode HTTP, le chemin OpenAPI, l'action résolue (réflexion) et les types
  de requête et de retour de l'action.
- **Deux modes de registre.** Opérations et requêtes **enrichissent** : tous les extracteurs qui répondent `true` à
  `supports()` s'exécutent par priorité **croissante**, chacun affinant la description du précédent (les attributs,
  de priorité haute, l'emportent sur la PHPDoc). Réponses et samplers **choisissent** : le premier extracteur qui
  répond `true`, par priorité **décroissante**, traite l'entrée. Les règles sont indexées par nom ou par classe.
- La priorité est donnée par `priority(): int`, qui renvoie une constante publique `PRIORITY` documentée, et non
  par l'ordre d'un tableau de configuration (cause principale des erreurs de configuration en v0.6).
- L'utilisateur ajoute ou remplace un extracteur par la configuration (`extensions`) ou par un fournisseur de
  services ; un extracteur d'intégration a une priorité supérieure à celle des extracteurs génériques qu'il
  spécialise (`JsonApiResource` avant `JsonResource`).
- **Un extracteur ne lève pas d'exception pour une route qu'il ne comprend pas** : il ajoute un diagnostic et
  renvoie une description partielle. Une seule route ratée ne bloque jamais la génération (sauf mode `--strict`).

### 4.2 Règles de validation : une règle = une classe

Pattern **Strategy**, comme les rewriters du writer : chaque comportement de règle Laravel a son `RuleMapper`,
indexé par les noms (`'required'`, `'max'`) ou les classes (`Illuminate\Validation\Rules\Enum`, `Password`, `File`)
qu'il traite ; les règles de même effet partagent une classe (`required_with`, `required_without`…).

```php
interface RuleMapper
{
    /** @return list<string|class-string> */
    public function handles(): array;

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription;
}
```

- `RuleInvocation` porte le nom, les arguments analysés et l'objet règle éventuel ; `FieldDescription` porte le
  `Schema` du champ, son caractère obligatoire, ses conditions (`required_if`…) et ses types de média.
- Les règles qui dépendent du type (`max`, `size`, `between`) lisent le type déjà établi par les autres règles :
  le `RuleParser` applique d'abord les règles de type, puis les contraintes (ordre explicite, testé).
- Les règles inconnues produisent un diagnostic de niveau information, jamais une erreur.

### 4.3 Système de types

`Types\TypeResolver` lit les types natifs et la PHPDoc (`@return`, `@param`, `@var`, `@extends`, `@property`) via
`phpstan/phpdoc-parser`, et produit un arbre `Type` (`NamedType`, `GenericType`, `ListType`, `ArrayShapeType`,
`UnionType`, `IntersectionType`, `NullableType`, `LiteralType`, `MixedType`). Il résout les noms par rapport aux `use` du fichier et aux templates déclarés.
Il remplace `Support\Reflection` et `Reflection\Type<T, U>` de la v0.6.

## 5. Échantillonnage

Les réponses Laravel se décrivent mal statiquement : une `JsonResource` construit son tableau à l'exécution. Le
générateur exécute donc la ressource sur une instance représentative, et infère le schéma du résultat.

| Pattern | Classe | Rôle |
| --- | --- | --- |
| **Chain of Responsibility** | `Sampler` + `Samplers` | Trouver une instance : `#[ResourceFactory]` → factory `create()` → factory `make()` → cas d'enum → `FakeObject` |
| **Unit of Work** | `SamplingSession` | Ouvre les transactions sur les connexions configurées, garantit le rollback, même en cas d'erreur |
| **Interpreter** | `SchemaInferrer` | Valeur PHP + `Type` connu → `Schema` (formats de date, entiers, `null`, listes, objets) |

- Aucune donnée n'est jamais conservée : sans transaction possible, on n'utilise que `make()`.
- Le schéma inféré est complété par les types déclarés (casts du modèle, propriétés `@property`) : une valeur
  `null` dans l'échantillon ne suffit pas à rendre un champ seulement `null`.

## 6. Passes

Pattern **Pipeline** : chaque passe est une classe qui reçoit et renvoie une `DocumentDescription`.

```php
interface Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription;
}
```

- L'ordre est explicite, dans `Passes::default()`, et testé : sécurité → réponses d'erreur → tags et groupes →
  `operationId` → composants et `$ref` → nettoyage.
- Une passe traite **une** préoccupation transverse (`MiddlewareSecurityPass`, `ValidationErrorResponsePass`,
  `ComponentExtractionPass`…). Les transformateurs de middlewares de la v0.6 deviennent des passes ou des
  `MiddlewareMapper` utilisés par `MiddlewareSecurityPass`.

## 7. Écriture

- `DocumentBuilder` est le seul **Mapper** entre la description et `openapi-writer`. Il ne prend aucune décision
  métier : tout ce qu'il écrit est déjà dans la description.
- La version OpenAPI et le mode strict ou permissif viennent de la configuration du document et sont passés au
  writer à l'écriture (`toJson(Version::…)`). Les fonctionnalités propres à une version (groupes de tags en
  `Tag.parent` en 3.2, `x-tagGroups` avant) sont décrites une fois et converties par le writer ou par une passe.
- `Output\DocumentWriter` écrit sur un disque Laravel (port `Filesystem` des contrats) et valide le document avec
  le `Validator` du writer ; les violations rejoignent les `Diagnostics`.

## 8. Diagnostics et erreurs

- Pattern **Notification** : `Diagnostics` collecte tout (niveau, route, étape, message) au lieu de s'arrêter. La
  commande Artisan les affiche groupés par route ; `--strict` fait échouer la commande au premier avertissement.
- Toutes les exceptions implémentent `OpenApiGeneratorException`, étendent l'exception SPL qui correspond et se
  créent par des constructeurs nommés (`InvalidConfigurationException::forKey('documents.v1.routes', '…')`).
- Une exception ne sert qu'aux erreurs de configuration et aux erreurs de programmation ; ce qui dépend du code
  de l'application documentée devient un diagnostic.

## 9. Conventions de code

| Sujet | Règle |
| --- | --- |
| Classes | `final` par défaut ; abstraites seulement pour les bases (`Description`) |
| Traits | Interdits dans `src/` (`Concerns/` de la v0.6 compris) : composition ou base abstraite |
| État global | Aucun singleton, aucune propriété statique mutable, aucune façade ni helper global hors de `Laravel/` |
| Types | `declare(strict_types=1)`, toutes les signatures typées ; `mixed` réservé aux valeurs libres et documenté |
| PHPDoc | Génériques pour les collections (`list<ParameterDescription>`), pas de PHPDoc redondante |
| Nommage | Interfaces sans suffixe (`Sampler`, `Pass`) ; implémentations nommées d'après ce qu'elles traitent (`JsonResourceExtractor`, `EnumRuleMapper`, `MiddlewareSecurityPass`) ; pas de suffixe `Interface` ni `Contract` hors des unions |
| Extracteurs / passes | Suffixes `*Extractor`, `*RuleMapper`, `*Sampler`, `*Pass`, vérifiés par `phpat` |
| Fichiers | Une classe par fichier, PSR-4 sous `Ark4ne\OpenApi\`, dossier = couche |
| Langues | Code, commentaires, README, CHANGELOG en anglais ; `docs/` en français |

## 10. Patterns de test

- **Unitaires** (`tests/Unit/`) : un test par `RuleMapper` (fournisseur règle → schéma attendu), par extracteur,
  par sampler, par passe. Les objets de description sont construits par l'Object Mother
  `tests/Support/Descriptions.php`.
- **Fonctionnels** (`tests/Feature/`, Orchestra Testbench) : un scénario par fonctionnalité, avec son application
  minimale dans `tests/Fixtures/` (routes, contrôleurs, requêtes, ressources, modèles, factories) et ses documents
  attendus `tests/Feature/<Scénario>/expected/<ligne>.json` (`3.0`, `3.1`, `3.2`).
- **Chaque document généré est validé** par le `Validator` d'`openapi-writer` (schéma officiel + règles
  sémantiques) avant la comparaison : zéro violation.
- `composer fixtures:update` réécrit les documents attendus depuis la sortie réelle ; chaque modification se relit
  dans le diff.
- **Architecture** (`tests/Architecture/`) : règle de dépendance, classes `final`, aucun trait, nommage.
- Structure Arrange / Act / Assert, nom du test en phrase (`test_max_rule_on_string_sets_max_length`).
- Assertions partagées dans une classe de base `TestCase`, pas dans des traits.

## 11. Recettes

### Documenter une nouvelle règle de validation

1. Créer `src/Extraction/Rules/Mappers/<Rule>RuleMapper.php`, déclarer la règle dans `handles()`.
2. L'enregistrer dans `RuleMappers::default()`.
3. Ajouter les cas au test unitaire (une ligne par variante d'arguments et de type).

### Documenter un nouveau type de réponse

1. Créer un `ResponseExtractor` dans `src/Extraction/Responses/`, avec sa constante de priorité.
2. L'enregistrer dans `ResponseExtractors::default()` (ou dans le fournisseur de son intégration).
3. Ajouter un test unitaire et un scénario fonctionnel avec ses documents attendus.

### Ajouter une préoccupation transverse

1. Créer une `Pass` dans `src/Generation/Passes/` et l'insérer à sa place dans `Passes::default()`.
2. Tester la passe seule (description en entrée et en sortie) et son ordre dans la chaîne.

## 12. Garde-fous automatiques

| Garde-fou | Ce qu'il vérifie |
| --- | --- |
| PHPStan niveau max + Larastan + règles strictes | Types, code mort, comparaisons, API Laravel |
| `phpat` | Règle de dépendance, classes `final`, aucun trait, nommage |
| `spaze/phpstan-disallowed-calls` | Façades, helpers globaux et `dd()` / `dump()` hors de `Laravel/` |
| PHP-CS-Fixer (PER-CS) | Style |
| Validation des documents générés | Toute sortie des tests fonctionnels respecte le schéma officiel et les règles du writer |
| Infection | Qualité des tests (MSI ≥ 80 %) |
