# Couverture de la spécification OpenAPI

Ce document confronte chaque élément d'OpenAPI 3.0 → 3.2 à ce que le générateur produit. L'écriture elle-même est
couverte par `openapi-writer` ; la question ici est : **d'où le générateur tire-t-il la valeur ?**

Statuts : **Prévu** (un livrable d'épique le couvre), **Non planifié** (priorité basse, à reprendre après la 1.0), **Hors
périmètre** (rien dans une application Laravel ne le décrit, ou sans intérêt pour un générateur).

## Document

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| `info` (dont `summary`, `license.identifier` 3.1) | 3.0+ | Prévu (E8) | Configuration |
| `servers`, variables | 3.0+ | Prévu (E7, E8) | Configuration ; routes à domaine |
| `Server.name` | 3.2 | Prévu (E8) | Clé `name` des serveurs de la configuration |
| `paths` | 3.0+ | Prévu (E2) | Routes |
| `webhooks` | 3.1+ | Prévu (E12) | `#[Webhook]` sur une action |
| `security` global | 3.0+ | Prévu (E7) | Option de configuration |
| `tags` | 3.0+ | Prévu (E3, E7) | Stratégies, `#[Tag]` |
| `externalDocs` | 3.0+ | Prévu (E8) | Configuration du document |
| `jsonSchemaDialect` | 3.1+ | Hors périmètre | Le dialecte par défaut (2020-12) suffit |
| `$self` | 3.2 | Prévu (E8) | Clé `self` du document : URL publique du fichier généré |

## Path Item et opérations

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| `summary`, `description` d'un chemin | 3.0+ | Hors périmètre | Aucune source naturelle ; les opérations portent les leurs |
| `parameters` partagés d'un chemin | 3.0+ | Prévu (E7) | Remonter au chemin les paramètres de chemin communs à toutes ses méthodes (sortie plus courte) |
| `get` … `trace` | 3.0+ | Prévu (E2) | Méthodes de la route |
| `query` (méthode QUERY) | 3.2 | Prévu (E2, E8) | Route déclarant la méthode `QUERY` ; rejet strict / retrait avant 3.2 |
| `additionalOperations` | 3.2 | Prévu (E2, E8) | Méthodes non standard (`Route::match(['PURGE'], …)`) ; ignorées avec diagnostic avant 3.2 |
| `Operation.summary`, `description`, `tags`, `operationId`, `deprecated` | 3.0+ | Prévu (E3) | PHPDoc, `#[Operation]`, stratégies |
| `Operation.externalDocs` | 3.0+ | Prévu (E3, E8) | Argument `externalDocs` de `#[Operation]` (celui de `#[Tag]` va sur le tag) |
| `Operation.callbacks` | 3.0+ | Prévu (E12) | `#[Callback]` |
| `Operation.security` | 3.0+ | Prévu (E7) | Middlewares, `#[Security]` |
| `Operation.servers` | 3.0+ | Prévu (E2, E7) | Routes à domaine |

## Paramètres, corps et contenus

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| Paramètres `path`, `query`, `header` | 3.0+ | Prévu (E2, E4) | Routes, règles, attributs |
| Paramètres `cookie` | 3.0+ | Prévu (E4) | Attribut `#[Cookie]` (aucune règle ne décrit un cookie) |
| `in: querystring` | 3.2 | Prévu (E4) | Mode de `QueryStrategy` : toute la query comme un seul objet (`content`) en 3.2 |
| `style`, `explode` | 3.0+ | Prévu (E4) | `QueryStrategy` |
| `allowEmptyValue`, `allowReserved` | 3.0+ | Hors périmètre | Déconseillés par la spec, aucune source |
| `Parameter.content` | 3.0+ | Hors périmètre | Couvert par `querystring` en 3.2 |
| `RequestBody.content`, `required` | 3.0+ | Prévu (E4) | Règles, `#[RequestBody]` |
| `Encoding` d'un corps `multipart/form-data` (`contentType` d'un fichier) | 3.0+ | Prévu (E4, E8) | Règles `mimes` / `mimetypes` / `File::types()` → `encoding.<propriété>.contentType` du seul `multipart/form-data` ; une règle sur `photos.*` donne la propriété `photos` |
| `prefixEncoding`, `itemEncoding` | 3.2 | Hors périmètre | Corps multipart positionnels : sans équivalent Laravel |
| `MediaType.itemSchema` (flux) | 3.2 | Prévu (E6) | `eventStream()` ; à étendre à `application/jsonl` si une réponse le déclare |
| `MediaType.description` | 3.2 | Hors périmètre | |
| `Components.mediaTypes` | 3.2 | Hors périmètre | Utile seulement à la main |

## Réponses, exemples et liens

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| Réponses, `headers`, `content` | 3.0+ | Prévu (E6, E7) | Types de retour, échantillons, `#[Response]` |
| `Response.summary` | 3.2 | Prévu (E6, E8) | Argument `summary` de `#[Response]` |
| `Response.links` | 3.0+ | Non planifié | Attribut `#[Link]` (par exemple `store` → `show` avec l'`id` de la réponse) ; priorité basse |
| `example` d'un schéma | 3.0+ | Prévu (E5) | Valeurs échantillonnées |
| `Example` nommés (`examples`), `dataValue` / `serializedValue` 3.2 | 3.0+ | Non planifié | Argument `examples` des attributs de paramètre et de corps ; priorité basse |

## Schémas

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| Types, formats, bornes, `enum`, `required`, `nullable` | 3.0+ | Prévu (E4, E5) | Règles, échantillons, casts |
| `oneOf` + `discriminator` (polymorphisme) | 3.0+ | Prévu (E6) | Union de ressources pour un même statut (`CatResource\|DogResource`) → `oneOf`, discriminateur si une propriété commune porte une valeur constante |
| `readOnly`, `writeOnly` | 3.0+ | Hors périmètre | Requêtes et réponses ont déjà des schémas distincts |
| `deprecated` d'une propriété | 3.1+ | Hors périmètre | Aucune source fiable |
| `contentMediaType`, `contentEncoding` | 3.1+ | Non planifié | Champs base64 (aucune règle de validation ne le dit : attribut ou type PHPDoc) ; priorité basse |
| `const`, plusieurs types | 3.1+ | Prévu (E4, E5) | Écrits par le modèle 3.1 et convertis par le writer |
| `xml` | 3.0+ | Hors périmètre | |

## Sécurité

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| `http` bearer / basic, `apiKey` | 3.0+ | Prévu (E7) | `auth:sanctum`, `auth.basic`, CSRF |
| `oauth2` et ses flux | 3.0+ | Prévu (E7) | Passport ; flux et URLs à compléter par la configuration |
| Scopes OAuth2 | 3.0+ | Prévu (E7) | Middlewares `scopes:` / `scope:` de Passport et `Passport::tokensCan()` |
| Scopes sur un schéma non OAuth2 | 3.1+ | Prévu (E7) | Middlewares `abilities:` / `ability:` de Sanctum → scopes du bearer (3.1+), description avant |
| `openIdConnect` | 3.0+ | Prévu (E7) | Configuration uniquement |
| `mutualTLS` | 3.1+ | Hors périmètre | Se configure côté serveur, invisible pour l'application |
| `deviceAuthorization`, `oauth2MetadataUrl`, `SecurityScheme.deprecated` | 3.2 | Prévu (E7) | Configuration des schémas de sécurité |

## Tags

| Élément | Versions | Statut | Source / proposition |
| --- | --- | --- | --- |
| `name`, `description` | 3.0+ | Prévu (E3, E7) | Stratégies, `#[Tag]` |
| `parent` | 3.2 | Prévu (E7) | Stratégie de groupe, `#[Tag(parent:)]` ; `x-tagGroups` avant 3.2 |
| `summary`, `kind` | 3.2 | Prévu (E3, E8) | Arguments de `#[Tag]` |
| `externalDocs` | 3.0+ | Prévu (E3, E8) | Argument de `#[Tag]` |

## Extensions de fournisseurs en 3.0

`x-webhooks` (Redoc, avant 3.1) et `x-tagGroups` (Redoc, avant 3.2) sont écrits par `openapi-writer` avec l'option
`VendorExtensions::Redoc`, que le générateur active par la clé `vendor_extensions` du document (voir
[E12](E12-webhooks.md)).
