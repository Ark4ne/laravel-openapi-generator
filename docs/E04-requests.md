# E4 — Requêtes et règles de validation

**Objectif.** Décrire paramètres de chemin, query, en-têtes, cookies et corps de chaque opération, à partir des
`FormRequest`, des règles de validation Laravel 13, des descriptions manuelles et des attributs.

**Dépend de :** E3.
**Architecture :** [architecture.md](architecture.md) §4.1 et §4.2.

## Livrables

### Extracteurs (`src/Extraction/Requests/`)

- [ ] `FormRequestExtractor` : instance par le conteneur sans `authorize()` ni validation, `rules()` avec injection
      de méthode, paramètres de route liés à des échantillons quand `rules()` en dépend
- [ ] `DescribableRequestExtractor` : nouvelle API fluide de description manuelle (en-têtes, query, corps par type
      de média, sécurité), qui accepte des `Schema` du writer
- [ ] `AttributeRequestExtractor` : `#[QueryParameter]`, `#[Header]`, `#[RequestBody]` sur l'action ou la requête
- [ ] Emplacement : méthodes sans corps (`GET`, `HEAD`, `DELETE`) → query ; sinon corps
- [ ] Type de média du corps : `application/json` par défaut, `multipart/form-data` dès qu'une règle de fichier est
      présente, configurable
- [ ] Sérialisation des query imbriquées (`filter.*.name`) : modes `all`, `last`, `none` de la v0.6 conservés, en
      `QuerySerialization` (`style` / `explode` / `deepObject`)

### Analyse des règles (`src/Extraction/Rules/`)

- [ ] `RuleParser` : chaînes `a|b:c,d`, tableaux, objets règles, `Rule::when()` (les deux branches), closures
      ignorées avec diagnostic, champs imbriqués `*` et `a.b.c`
- [ ] Ordre d'application : règles de type d'abord, contraintes ensuite (testé)
- [ ] `RuleMapper` pour chaque règle, par famille :
    - [ ] Présence : `required`, `required_if`, `required_unless`, `required_with*`, `required_without*`,
          `required_if_accepted`, `required_if_declined`, `required_array_keys`, `sometimes`, `nullable`, `present*`,
          `filled`, `missing*`, `prohibited*`, `prohibits`, `exclude*`, `bail`
    - [ ] Types : `string`, `integer`, `numeric`, `decimal`, `boolean`, `array`, `list`, `json`, `file`, `image`,
          `date`, `accepted*`, `declined*`
    - [ ] Formats : `email`, `url`, `active_url`, `uuid`, `ulid`, `ip`, `ipv4`, `ipv6`, `mac_address`, `hex_color`,
          `timezone`, `date_format`, `ascii`, `lowercase`, `uppercase`, `alpha*`, `regex`, `not_regex`,
          `starts_with`, `ends_with`, `doesnt_start_with`, `doesnt_end_with`
    - [ ] Bornes selon le type : `min`, `max`, `between`, `size`, `gt`, `gte`, `lt`, `lte`, `digits`,
          `digits_between`, `min_digits`, `max_digits`, `multiple_of`
    - [ ] Dates : `after`, `after_or_equal`, `before`, `before_or_equal`, `date_equals` (formats de la configuration)
    - [ ] Ensembles : `in`, `not_in`, `Rule::in()`, `Rule::notIn()`, `Rule::enum()` (avec `only()` / `except()`),
          `distinct`, `exists`, `unique`, `Rule::array()` (clés autorisées), `Rule::contains()`
    - [ ] Fichiers : `mimes`, `mimetypes`, `extensions`, `dimensions`, `File::types()`, `File::image()` avec leurs bornes
    - [ ] Objets fluides : `Password` (et `Password::defaults()`), `Rule::date()`, `Rule::numeric()`, `Rule::email()`,
          `Rule::anyOf()` (→ `anyOf`)
    - [ ] Relations entre champs : `confirmed` (champ `*_confirmation`), `same`, `different`, `in_array`, `gt:champ`…
          (description)
- [ ] Règles conditionnelles : `required` seulement si inconditionnel ; condition écrite dans la description
- [ ] Règles de l'application : `DescribesRule` implémentée par la règle, ou `RuleMapper` enregistré
- [ ] Règle inconnue : diagnostic `Info`, champ conservé sans contrainte

## Critères de sortie

- [ ] Un test unitaire par `RuleMapper` (une ligne par variante d'arguments et de type)
- [ ] Scénarios fonctionnels « requêtes » (JSON, multipart, query imbriquées, description manuelle)
- [ ] Parité avec les requêtes du document attendu v0.6 `openapi-jsonresource.json` (écarts justifiés)

## Hors périmètre V1

- Validation en ligne `$request->validate([...])` dans l'action : demande une analyse statique du code ; à étudier
  après la 1.0.
