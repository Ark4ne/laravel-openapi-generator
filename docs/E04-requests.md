# E4 — Requêtes et règles de validation

**Objectif.** Décrire paramètres de chemin, query, en-têtes, cookies et corps de chaque opération, à partir des
`FormRequest`, des règles de validation Laravel 13, des descriptions manuelles et des attributs.

**Dépend de :** E3.
**Architecture :** [architecture.md](architecture.md) §4.1 et §4.2.

## Conception

```
FormRequest::rules()      ──► RuleParser ──► champ → RuleInvocation[] (types d'abord) ──► FieldsBuilder ──► FieldDescription[]
DescribesRequest::describe()                                                  (RuleMappers, DescribesRule)        │
#[QueryParameter] #[Header] #[Cookie] #[PathParameter] #[RequestBody] ──► TypeSchemas (type PHPDoc → Schema)     │
                                                                                                                  ▼
                                               FieldPlacer : query (QueryStrategy) ou corps (FieldTree, type de média, encodages)
```

- **Extracteurs** (`src/Extraction/Requests/`), qui enrichissent par priorité croissante : chemin (0), `FormRequest`
  (100), description manuelle (200), attributs (300).
- **Règles** (`src/Extraction/Rules/`) : `RuleParser` normalise les règles d'un champ (chaînes, tableaux, objets,
  `Rule::when()`), `FieldsBuilder` applique les `RuleMapper`, `FieldTree` assemble les champs imbriqués en un objet.
- **Un `RuleMapper` par comportement de règle** : les règles de même effet partagent une classe
  (`required_with`, `required_with_all`, `required_without`… → `ConditionalRequiredRuleMapper`).
- Les conditions d'une règle (`required_if`, `exists`, `after`…) deviennent des phrases de la description du champ.

## Livrables

### Extracteurs (`src/Extraction/Requests/`)

- [x] `RequestExtractors` : tri par priorité, `default()`
- [x] `PathParameterRequestExtractor` : paramètres de chemin typés par la découverte (E2)
- [x] `FormRequestExtractor` : instance sans validation ni `authorize()` (`RequestFactory`), `rules()` appelée par
      le conteneur (injection de méthode), paramètres de route liés à des échantillons (modèle neuf, premier cas
      d'enum, valeur typée) ; `validated` quand des règles existent, `authorized` quand `authorize()` est redéfinie ;
      erreur de `rules()` → diagnostic
- [x] `DescribableRequestExtractor` : interface `DescribesRequest` et constructeur immuable `RequestDescriber`
      (règles de query et de corps par type de média, `Schema` du writer, en-têtes, cookies, paramètres)
- [x] `AttributeRequestExtractor` : `#[QueryParameter]`, `#[Header]`, `#[Cookie]`, `#[PathParameter]`,
      `#[RequestBody]` sur l'action ou sur la classe de la requête ; types PHPDoc convertis par `TypeSchemas`
      (`src/Sampling/`) ; `#[RequestBody(resource:)]` décrit en E6
- [x] Emplacement (`FieldPlacer`) : méthodes sans corps (`GET`, `HEAD`, `DELETE`) → query ; sinon corps
- [x] Type de média du corps : `application/json` par défaut, `multipart/form-data` dès qu'un champ est un fichier,
      imposé par `RequestDescriber` ou `#[RequestBody(mediaType:)]`
- [x] Sérialisation des query imbriquées (`filter.*.name`) : modes `all`, `last`, `none` de la v0.6 conservés, en
      `QueryStrategy` (`style` / `explode` / `deepObject`)
- [x] Mode `querystring` de `QueryStrategy` : toute la query décrite comme un seul objet (`in: querystring`, 3.2),
      repli sur le mode `none` avant 3.2
- [x] Encodage des corps multipart : `encoding.<propriété>.contentType` du seul `multipart/form-data`, tiré de
      `mimes`, `mimetypes`, `image`, `File::types()` ; clé = propriété de premier niveau (`photos.*` → `photos`)

### Analyse des règles (`src/Extraction/Rules/`)

- [x] `RuleParser` : chaînes `a|b:c,d`, tableaux, objets règles (objets `Stringable` lus comme Laravel les lit),
      `Rule::when()` (les deux branches, marquées conditionnelles), closures ignorées avec diagnostic, champs
      imbriqués `*` et `a.b.c`, champ `*_confirmation` créé pour `confirmed`
- [x] Ordre d'application : règles de type d'abord, contraintes ensuite, `sometimes` en dernier (testé)
- [x] `FieldsBuilder` et `FieldTree` : champs imbriqués assemblés en objets et listes, `required` des parents,
      conditions en description, `distinct` → `uniqueItems`, champs interdits (`prohibited`, `missing`) retirés
- [x] `RuleMapper` pour chaque règle, par famille :
    - [x] Présence : `required`, `required_if`, `required_unless`, `required_with*`, `required_without*`,
          `required_if_accepted`, `required_if_declined`, `required_array_keys`, `sometimes`, `nullable`, `present*`,
          `filled`, `missing*`, `prohibited*`, `prohibits`, `exclude*`, `bail`
    - [x] Types : `string`, `integer`, `numeric`, `decimal`, `boolean`, `array`, `list`, `json`, `file`, `image`,
          `date`, `accepted*`, `declined*`
    - [x] Formats : `email`, `url`, `active_url`, `uuid`, `ulid`, `ip`, `ipv4`, `ipv6`, `mac_address`, `hex_color`,
          `timezone`, `date_format` (formats de `date_formats`), `ascii`, `lowercase`, `uppercase`, `alpha*`, `regex`,
          `not_regex`, `starts_with`, `ends_with`, `doesnt_start_with`, `doesnt_end_with`
    - [x] Bornes selon le type : `min`, `max`, `between`, `size`, `gt`, `gte`, `lt`, `lte`, `digits`,
          `digits_between`, `min_digits`, `max_digits`, `multiple_of`
    - [x] Dates : `after`, `after_or_equal`, `before`, `before_or_equal`, `date_equals`
    - [x] Ensembles : `in`, `not_in`, `Rule::in()`, `Rule::notIn()`, `Rule::enum()` (avec `only()` / `except()`),
          `distinct`, `exists`, `unique`, `Rule::array()` (clés autorisées), `Rule::contains()`
    - [x] Fichiers : `mimes`, `mimetypes`, `extensions`, `dimensions`, `File::types()`, `File::image()` avec leurs bornes
    - [x] Objets fluides : `Password` (et `Password::defaults()`), `Rule::date()`, `Rule::numeric()`, `Rule::email()`,
          `Rule::anyOf()` (→ `anyOf`)
    - [x] Relations entre champs : `confirmed` (champ `*_confirmation`), `same`, `different`, `in_array`, `gt:champ`…
          (description)
- [x] Règles conditionnelles : `required` seulement si inconditionnel ; condition écrite dans la description
- [x] Règles de l'application : `DescribesRule` implémentée par la règle, ou `RuleMapper` enregistré
- [x] Règle inconnue : diagnostic `Info`, champ conservé sans contrainte

## Critères de sortie

- [x] Un test des `RuleMapper` par famille (une ligne par règle, variante d'arguments et de type)
- [x] Scénarios « requêtes » : JSON, multipart, query imbriquées, description manuelle, attributs, règles liées à la
      route (`RequestExtractorsTest`, sous Testbench) ; ses documents attendus viennent avec l'écriture (E8)
- [x] Parité avec les requêtes du document attendu v0.6 `openapi-jsonresource.json` (écarts justifiés)

## Notes de réalisation

- **Un mapper par comportement** : 43 classes pour l'ensemble des règles listées ; les règles de même effet en
  partagent une (`ConditionalRequiredRuleMapper` traite les conditions de `required_*`, `present_*`, `missing_*`,
  `prohibited_*`, `accepted_if`, `declined_if`). `RuleMappers::with()` en enregistre d'autres, qui l'emportent.
- **Conditions** : ce qu'OpenAPI ne sait pas écrire (`required_if`, `exists`, `after`, `dimensions`, `regex` avec
  drapeaux, taille d'un fichier en Ko…) devient une phrase de la description du champ.
- **Types** : `date` reste une chaîne avec la phrase « a date » (Laravel accepte tout ce que PHP sait lire) ;
  `date_format` donne `date` / `date-time` selon `date_formats` ; un champ de query sans type est une chaîne.
- **Comportements de Laravel 13 pris en compte** : `FormRequest` n'a plus d'`authorize()` propre (seule une
  méthode redéfinie compte) ; `File::types()` reçoit types de contenu et extensions dans une même liste ;
  `Rule::requiredIf()` et ses pareilles s'évaluent en chaîne : elles sont lues comme des règles conditionnelles.
- **Parité v0.6** : mêmes paramètres de query et mêmes corps que `openapi-jsonresource.json` ; écarts : les champs
  obligatoires sont marqués (la v0.6 les perdait), `max:today` sur une date n'est plus écrit (ce n'est pas une
  taille).
- `#[RequestBody(resource:)]` attend l'échantillonnage des ressources (E6) : diagnostic `Info` en attendant.
- `symfony/mime` devient une dépendance directe (types de contenu des extensions de `mimes`, `image`, `File`).

## Hors périmètre V1

- Validation en ligne `$request->validate([...])` dans l'action : demande une analyse statique du code ; à étudier
  après la 1.0.
