# E5 — Échantillonnage et inférence de schémas

**Objectif.** Obtenir sans effet de bord une instance représentative de ce qu'une ressource reçoit, et déduire un
schéma juste de ce qu'elle renvoie, valeurs et types déclarés combinés.

**Dépend de :** E3.
**Architecture :** [architecture.md](architecture.md) §5.

## Livrables

### Session (`src/Sampling/`)

- [ ] `SamplingSession` : transactions sur les connexions configurées (`sampling.connections`), rollback garanti
      (`finally`), connexions injoignables signalées et ignorées
- [ ] Sans transaction possible : `make()` uniquement, jamais d'écriture
- [ ] Faker initialisé avec une graine fixe : deux générations donnent le même document

### Modèle de la ressource

- [ ] Résolution du modèle d'une ressource : type du constructeur, `@extends JsonResource<User>`, `@mixin`,
      `@property`, attributs `#[UseResource]` / `#[UseResourceCollection]` des modèles (lecture inverse), convention de nommage (`UserResource` → `App\Models\User`) en dernier recours
- [ ] Ressource d'une collection : `$collects`, `collects()`, génériques, convention

### Chaîne de samplers

- [ ] `ResourceFactorySampler` (`#[ResourceFactory]`)
- [ ] `ModelFactorySampler` : `HasFactory`, attribut `#[UseFactory]`, `create()` en transaction puis `make()`
- [ ] `EnumSampler` (un cas, ou plusieurs pour une collection)
- [ ] `FakeObjectSampler` : objet typé qui répond à toute propriété et méthode d'après les types déclarés
- [ ] Nombre d'éléments pour une collection (2 par défaut, configurable)

### Inférence (`SchemaInferrer`)

- [ ] Valeurs scalaires, `null`, listes et objets, listes vides (`items` tiré du type déclaré)
- [ ] Formats : dates selon `format.date` de la configuration, `Carbon` / `DateTimeInterface`, UUID
- [ ] Types déclarés du modèle : casts (`datetime`, `immutable_date`, `decimal:2`, `array`, `json`, `AsCollection`,
      `AsEnumCollection`, enum), `@property`
- [ ] Nullabilité tirée des colonnes (`Schema::getColumns()`) quand une connexion est disponible
- [ ] `MissingValue` (`when()`, `whenLoaded()`, `whenCounted()`…) : propriété présente mais non requise
- [ ] Ressources imbriquées : composant `$ref` plutôt que schéma recopié
- [ ] Exemples (`example`) tirés de l'échantillon, désactivables

## Critères de sortie

- [ ] Tests unitaires de chaque sampler, de `SamplingSession` (rollback après exception) et de l'inférence
- [ ] Test de non-régression : aucune ligne ajoutée en base après une génération

## Questions à clore pendant E5

- Relations `whenLoaded()` : échantillonner avec les relations chargées (factories `has()` / `for()`) pour en
  décrire le schéma, ou les déclarer seulement optionnelles et sans forme ?
