# Configuration : de la v0.6 à la V1

Table de correspondance entre `config/openapi.php` v0.6 et V1, base du guide de migration (`UPGRADE.md`, E11).
Les clés V1 sont en `snake_case` (convention Laravel) ; celles de la v0.6 étaient en `kebab-case`.

| v0.6 | V1 | Remarque |
| --- | --- | --- |
| `output-disk` | `output.disk` | |
| `output-dir` | `output.directory` | |
| `versions.<v>.output-file` | `documents.<v>.output.file` | Accepte `{document}` et `{locale}` ; la v0.6 préfixait la langue (`fr-openapi.json`) |
| — | `output.format`, `documents.<v>.output.format` | `json` ou `yaml` |
| `servers` | `servers` | `variable` devient `variables` (la v0.6 lisait `variables`, ses variables n'étaient jamais écrites) |
| `versions` | `documents` | La version OpenAPI est désormais `documents.<v>.openapi` |
| — | `documents.<v>.openapi`, `documents.<v>.strict` | 3.1.2 et strict par défaut (la v0.6 écrivait toujours 3.0.2) |
| — | `documents.<v>.vendor_extensions` | `redoc` : `x-webhooks` avant 3.1, `x-tagGroups` avant 3.2 (la v0.6 écrivait toujours `x-tagGroups`) |
| `versions.<v>.servers` | `documents.<v>.servers` | |
| `versions.<v>.title` | `documents.<v>.info.title` | |
| `versions.<v>.description` | `documents.<v>.info.description` | |
| — | `documents.<v>.info.version`, `summary`, `terms_of_service`, `contact`, `license` | La v0.6 écrivait la clé de version comme `info.version` |
| `versions.<v>.routes` | `documents.<v>.routes.include` | |
| — | `documents.<v>.routes.exclude` | |
| `versions.<v>.ignore-verbs` | `documents.<v>.routes.ignored_methods` | |
| `versions.<v>.name-by` (`by`, `regex`) | `documents.<v>.strategies.summary` (`by`, `pattern`) | `description` devient `doc` |
| `versions.<v>.tag-by` | `documents.<v>.strategies.tags` | `regex` devient `pattern` |
| `versions.<v>.group-by` | `documents.<v>.strategies.groups` | `Tag.parent` en 3.2, `x-tagGroups` avant |
| — | `documents.<v>.strategies.operation_id` | La v0.6 écrivait `"<method>:<route name>"` |
| `versions.<v>.parameters.query.flat` | `documents.<v>.strategies.query` | Mêmes valeurs : `all`, `last`, `none` |
| `versions.<v>.use-ref` | `documents.<v>.use_references` | |
| `parsers.requests` | `extensions.request_extractors` | Classes à priorité, et non plus carte ordonnée classe → parser |
| `parsers.rules` | `extensions.rule_mappers` | Ou interface `DescribesRule` sur la règle |
| `parsers.responses` | `extensions.response_extractors` | |
| `middlewares` (transformateurs) | `middlewares` (`MiddlewareMapper`) | Les mappers intégrés couvrent `auth:sanctum`, CSRF et XSRF |
| `format.date` | `date_formats` | Valeurs : formats OpenAPI (`date`, `date-time`) au lieu des constantes de `Parameter` |
| `connections.use-transaction` | `sampling.transactions` | |
| `connections.on-connections` | `sampling.connections` | |
| — | `sampling.collection_size`, `sampling.seed`, `sampling.examples` | |
| `languages` | `languages` | |
| — | `extensions.operation_extractors`, `samplers`, `passes` | Nouveaux points d'extension |
