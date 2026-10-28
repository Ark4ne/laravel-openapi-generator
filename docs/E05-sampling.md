# E5 — Échantillonnage et inférence de schémas

**Objectif.** Obtenir sans effet de bord une instance représentative de ce qu'une ressource reçoit, et déduire un
schéma juste de ce qu'elle renvoie, valeurs et types déclarés combinés.

**Dépend de :** E3.
**Architecture :** [architecture.md](architecture.md) §5.

## Conception

- **Session** : `DatabaseSamplingSession` ouvre une transaction sur chaque connexion donnée dont le pilote figure dans
  `sampling.connections`, exécute la génération et annule tout dans un `finally`. `canPersist($connection)` dit si un
  modèle de cette connexion peut être enregistré. La graine `sampling.seed` est posée par `mt_srand()` (Faker en
  dépend) pendant la session.
- **Chaîne** (`Samplers`, priorité décroissante, le suivant est essayé quand un sampler ne rend rien) :
  `#[ResourceFactory]` (400) → factory du modèle (300) → cas d'enum (200) → objet factice (100).
- **Valeurs factices** (`FakeValues`) : une valeur par type déclaré (`int` → 1, `string` → `string`, date fixe, premier
  cas d'enum, liste de deux éléments…), utilisées par l'objet factice et pour remplir un modèle sans factory.
- **Modèle d'une ressource** (`ResourceModels`) et ressource d'une collection, dans l'ordre de la liste ci-dessous.
- **Inférence** (`SchemaInferrer`) : valeur échantillonnée + type déclaré → `Schema` ; `ModelSchemas` donne les
  schémas déclarés des attributs d'un modèle (casts, `@property`, colonnes) ; une ressource imbriquée devient un
  composant (`ComponentRegistry`) référencé par `$ref`.

## Livrables

### Session (`src/Sampling/`)

- [x] `DatabaseSamplingSession` : transactions sur les connexions dont le pilote est dans `sampling.connections`,
      rollback garanti (`finally`), connexions injoignables signalées et ignorées
- [x] Sans transaction possible (`sampling.transactions: false`, pilote absent, connexion injoignable) : `make()`
      uniquement, jamais d'écriture
- [x] Graine fixe (`sampling.seed`) : deux échantillonnages donnent les mêmes valeurs
- [x] `ComponentRegistry` : enregistrement par classe, nom de `#[SchemaName]` ou de la classe, collisions suffixées et
      signalées

### Modèle de la ressource

- [x] Résolution du modèle d'une ressource : type du constructeur, `@extends JsonResource<User>`, `@mixin`,
      attributs `#[UseResource]` / `#[UseResourceCollection]` des modèles chargés (lecture inverse), convention de
      nommage (`…\Http\Resources\UserResource` → `…\Models\User`, puis `App\Models\User`) en dernier recours
- [x] Ressource d'une collection : `#[Collects]`, `$collects`, génériques, convention de `collects()` de Laravel

### Chaîne de samplers

- [x] `ResourceFactorySampler` (`#[ResourceFactory]`)
- [x] `ModelFactorySampler` : `HasFactory`, attribut `#[UseFactory]`, `create()` en transaction puis `make()`
- [x] `EnumSampler` (un cas, ou plusieurs pour une collection)
- [x] `FakeObjectSampler` : modèle sans factory rempli d'après ses casts et `@property`, sinon objet factice
      (`FakeObject`) qui répond à toute propriété et méthode d'après les types déclarés
- [x] Nombre d'éléments pour une collection (2 par défaut, configurable)

### Inférence (`SchemaInferrer`)

- [x] Valeurs scalaires, `null`, listes et objets, listes vides (`items` tiré du type déclaré)
- [x] Formats : dates selon `date_formats` de la configuration, `Carbon` / `DateTimeInterface`, UUID
- [x] Types déclarés du modèle : casts (`datetime`, `immutable_date`, `decimal:2`, `array`, `json`, `AsCollection`,
      `AsEnumCollection`, enum), `@property`
- [x] Nullabilité tirée des colonnes (`Schema::getColumns()`) quand une connexion est disponible
- [x] `MissingValue` (`when()`, `whenLoaded()`, `whenCounted()`…) : propriété présente mais non requise
- [x] Ressources imbriquées : composant `$ref` plutôt que schéma recopié
- [x] Exemples (`example`) tirés de l'échantillon, désactivables

## Critères de sortie

- [x] Tests unitaires de chaque sampler, de `SamplingSession` (rollback après exception) et de l'inférence
- [x] Test de non-régression : aucune ligne ajoutée en base après un échantillonnage, même quand il échoue

## Notes de réalisation

- **Graine** : `mt_srand(sampling.seed)` pendant la session suffit à Faker ; elle est relâchée (`mt_srand()`) à la fin.
- **Factory** : `create()` seulement quand la connexion du modèle est dans une transaction de la session ; si
  l'enregistrement échoue (table absente, contrainte), `make()` ; une factory qui échoue est signalée et le sampler
  suivant prend le relais.
- **Valeurs factices** : `int` → 1, `float` → 1.5, `string` → `string`, `bool` → `true`, date fixe
  `2024-01-01T00:00:00+00:00`, premier cas d'enum, deux éléments par liste, objets sur deux niveaux au plus.
- **Casts** : ceux dont le JSON dépend de la valeur (`array`, `json`, `object`, casts personnalisés) ne déclarent
  rien, la valeur est lue ; `decimal:2` est une chaîne au motif `^-?[0-9]+\.[0-9]{2}$` ; `AsCollection` une liste ;
  `AsEnumCollection` une liste de l'enum.
- **Ressource imbriquée** : enregistrée dans `ComponentRegistry` avant que son schéma soit inféré, pour qu'une
  ressource contenue en elle-même soit une référence et non une boucle.
- **Configuration** : `sampling` et `date_formats` sont lus par `Configuration::fromArray()`.
- Les formats de date par défaut sont dans `Support\DateFormats`, partagés par l'inférence et `date_format` (E4).

## Questions à clore pendant E5

- Relations `whenLoaded()` : échantillonner avec les relations chargées (factories `has()` / `for()`) pour en
  décrire le schéma, ou les déclarer seulement optionnelles et sans forme ?
