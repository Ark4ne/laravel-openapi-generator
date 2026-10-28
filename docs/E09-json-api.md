# E9 — Intégration JSON:API

**Objectif.** Documenter les ressources, collections et requêtes de `ark4ne/laravel-json-api` comme une
extension isolée, active seulement quand le paquet est installé.

**Dépend de :** E8.
**Architecture :** [architecture.md](architecture.md) §2 (Integrations).

## Livrables

### Enregistrement (`src/Integrations/JsonApi/`)

- [x] Fournisseur de l'intégration : `JsonApiIntegration` (contrat `Generation\Integration`) donne un format de
      ressource, des mappers de règles et des corps d'erreur ; `BuiltInIntegrations::all()` la liste, le `Generator`
      ne la retient que si `ark4ne/laravel-json-api ^1.9` est présent (le fournisseur de services d'E10 la lui passe)
- [x] Type de média `application/vnd.api+json`

### Réponses

- [x] `JsonApiResourceFormat` (contrat `Extraction\Responses\ResourceFormat`, consulté par `ResourceSchemas` avant
      l'écriture de Laravel) : `data` (`id`, `type`, `attributes`, `relationships`, `links`, `meta`), `included`
- [x] Types des attributs lus dans les descripteurs du paquet (`Ark4ne\JsonApi\Descriptors`) avant tout échantillonnage
- [x] Relations simples et multiples, liens et méta des relations
- [x] Collections (`JsonApiCollection<PostResource>`, `PostResource::collection()`) et pagination (`links` et `meta`
      de Laravel, que le paquet ne change pas)

### Requêtes

- [x] Règles `Includes` et `Fields` → paramètres `include` et `fields[type]` (`deepObject`)
- [x] Paramètres de tri, de filtre et de pagination quand le paquet les déclare : la version 1.9 n'en déclare aucun
      (ses `Filters` filtrent les relations à l'écriture, pas la requête) ; les règles de la `FormRequest`
      (`filter.*`, `page`) restent décrites par E4

### Erreurs

- [x] Schéma d'erreur JSON:API (`errors[]`) pour les réponses d'erreur de E7, sur option (`JsonApiIntegration(errors: true)`)

## Critères de sortie

- [x] Scénario fonctionnel JSON:API ; parité avec le document attendu v0.6 `openapi-jsonapi.json` (écarts justifiés)
- [x] Suite complète verte sans le paquet installé (l'intégration ne charge aucune de ses classes)

## Notes de réalisation

- **Format de ressource plutôt qu'extracteurs.** Le plan prévoyait `JsonApiResourceExtractor` et
  `JsonApiCollectionExtractor`, de priorité supérieure aux extracteurs génériques. Ils n'auraient servi que les types
  de retour. Un `ResourceFormat`, consulté par `ResourceSchemas`, écrit la ressource partout où elle apparaît :
  - retournée ;
  - déclarée (`#[Response(resource:, paginator:)]`, `#[RequestBody(resource:)]`, `#[Callback(resource:)]`) ;
  - imbriquée ;
  - dans une collection.

  Les extracteurs génériques prennent le type de média du format.
- **Modèle d'une ressource.** `@extends JsonApiResource<User>` lie le template du paquet, pas celui de
  `JsonResource` ; `ResourceModels` lit maintenant le modèle donné au template d'un parent (correction du cœur).
- **Composant.** Seuls `id` et `type` sont obligatoires : les champs creux (`fields[type]`) et les conditions
  (`when`, `whenLoaded`…) peuvent retirer tout autre membre. Ce qui est décrit :
  - `id` : typé par `toIdentifier()` sur un échantillon, ou par la clé du modèle échantillonné ;
  - `type` : une `const` ;
  - les descripteurs : nullables comme le paquet les configure (`nullable` par défaut) ; une énumération nullable
    compte `null` parmi ses valeurs ;
  - le format d'une date : celui de son descripteur, traduit par `date_formats`.
- **Relations.** Chaque relation porte un identifiant de ressource : `{type, id}`, nullable pour une relation simple,
  une liste pour une relation multiple. Les `links` et `meta` sont ceux des closures de la relation, exécutées sur
  le modèle factice du paquet.
- **`included`.** Le document liste toute ressource atteignable par les relations, parcourues en largeur (une
  inclusion peut être imbriquée : `posts.comments`) :
  - plusieurs ressources : `oneOf` avec un discriminateur sur `type` ;
  - une seule ressource : sa référence.
- **Requêtes.**
  - Description de `include` : les chemins qui ne repassent pas par une ressource déjà traversée.
  - `fields` : une propriété par type atteint, avec les champs du squelette du paquet. Les champs d'une structure
    imbriquée y figurent pointés (`profile.scores.rank`), sans la structure elle-même.
  - Changement du cœur : un champ objet laissé en un seul paramètre de requête (stratégie `all` ou `last`) est
    maintenant un `deepObject`.
- **Erreurs.** Le paquet laisse les erreurs de Laravel telles quelles. Les corps JSON:API (`JsonApiErrors`, type de
  média du paquet) ne s'appliquent donc que sur option. Ils s'appliquent alors à toutes les réponses d'erreur : celles
  des passes d'E7 et celles des exceptions déclarées (`@throws`, `#[Response]` sans corps sur une exception, E6).
  - Le contrat `ErrorBodies` donne aussi le type de média des erreurs.
  - Il a quitté `Generation` pour `Extraction\Exceptions`, afin que les mappers d'exceptions le reçoivent.
- **Ressources déclarées.**
  - `#[RequestBody(resource:)]` envoie ce que le format envoie (`ResourceFormat::request()` : l'objet ressource sous
    `data`), dans son type de média.
  - `#[Callback(resource:)]` prend le type de média du format.
  - Une classe de collection (`#[Response(resource: UserCollection::class)]`, relation vers une collection) est la
    liste de la ressource qu'elle collecte. Celle-ci est trouvée comme ailleurs (`$collects`, puis la convention de
    nommage de Laravel).
- **Robustesse.**
  - Les closures `links` et `meta` d'une relation multiple reçoivent une collection, comme dans le paquet.
  - Deux ressources incluses d'un même `type` retirent le discriminateur, avec un avertissement.
  - Une règle `Includes` ou `Fields` dont la ressource est illisible donne un avertissement, jamais une exception.
- **Écarts avec le document v0.6** (`openapi-jsonapi.json`) :
  - **Noms de composants** : `User`, `Post`, `Comment` au lieu de `resource-user` et `resource-6c97a4-post-resource`.
  - **`type` et `id`** : `type` est une `const` (au lieu d'un `default`) ; `id` est typé (`integer`) au lieu d'être
    toujours `string`.
  - **Extensions** : plus de `x-ref` sur les identifiants, ni de `x-type` ou `x-name` sur les composants.
  - **Relations** : la relation simple est nullable ; liens et méta des relations sont décrits.
  - **`included`** : transitif, en `oneOf` avec discriminateur, au lieu de l'`anyOf` des seules relations directes.
  - **Valeurs non typées** : `{}` au lieu de `{"type": "object", "example": "mixed"}`.
  - **En-têtes datés** : plus de `Date` ni de `Cache-Control`.
  - **Pagination** : déclarée par `#[Response(paginator:)]` (décision d'E6).
