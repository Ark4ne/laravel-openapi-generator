# E12 — Webhooks et callbacks

**Objectif.** Documenter les requêtes que l'application **envoie** : les webhooks (`webhooks` du document, 3.1+)
et les callbacks d'une opération (`callbacks`, toutes versions).

**Dépend de :** E8 (écriture), E4 et E6 (décrire une requête et des réponses). Numéro attribué à l'ajout de
l'épique : il se place après E8 dans l'ordre de réalisation.
**Architecture :** [architecture.md](architecture.md) §1 (Extraction), §6 (Passes).

## Ce que dit la spécification

| Élément | 3.0.x | 3.1.x | 3.2.0 |
| --- | --- | --- | --- |
| `webhooks` (racine) : requêtes envoyées hors de tout appel, indexées par un nom | Absent | Oui ; un document peut n'avoir que des webhooks | Inchangé ; les nouveautés 3.2 s'y appliquent (`QUERY`, `itemSchema`…) |
| `Operation.callbacks` : requêtes envoyées en réaction à un appel, vers une URL donnée par cet appel (expression d'exécution `{$request.body#/callbackUrl}`) | Oui | Oui | Oui |

## Webhooks : un attribut, lu comme le reste

Un webhook est une action de contrôleur portant `#[Webhook]` (ou une action d'un contrôleur qui le porte). Il n'y
a ni liste de configuration ni découverte par dossier : **l'extracteur d'opération qui lit les attributs décide**,
route par route, s'il s'agit d'une opération ou d'un webhook.

```php
#[Webhook('order.created')]
#[Operation(summary: 'An order was created')]
#[Header('X-Signature', type: 'string', required: true)]
#[Response(204)]
public function orderCreated(OrderCreatedPayload $request): Response
```

- La requête (form request, règles, `#[RequestBody]`, `#[Header]`) décrit ce qui est envoyé ; les réponses, ce
  que le destinataire doit répondre ; `#[Operation]`, le résumé, les tags et l'`operationId`.
- Le nom vient de l'attribut, sinon du nom de la route ; sans l'un ni l'autre, diagnostic et route ignorée. Sur un
  contrôleur, le nom de l'attribut est un **préfixe** : `#[Webhook('order')]` donne `order.created`, `order.updated`
  (suffixe : nom de l'attribut de l'action, sinon nom de la méthode) ; deux actions n'ont jamais la même clé.
- Le générateur range l'opération dans `DocumentDescription::$webhooks` dès l'extraction : les passes liées aux
  routes (middlewares, sécurité, erreurs 401/403/404/429) ne la voient jamais. Le chemin de la route n'apparaît pas.
- Une route qui **reçoit** les webhooks d'un tiers (Stripe, GitHub…) reste un chemin ordinaire, sans attribut.

## Callbacks

```php
#[Callback('orderShipped', url: '{$request.body#/callbackUrl}', webhook: 'order.shipped')]
#[Callback('paymentFailed', url: '{$request.body#/callbackUrl}', resource: PaymentResource::class, status: 204)]
public function store(StoreOrderRequest $request): OrderResource
```

Le callback décrit sa requête par **une seule** source : un webhook réutilisé (même payload, mêmes réponses
attendues), une ressource ou un type PHPDoc, avec le statut attendu (200 par défaut). Plusieurs sources, ou `status`
avec `webhook`, lèvent une `InvalidAttributeException`. Répéter l'attribut sous le même nom donne plusieurs méthodes
ou plusieurs URL d'un même callback (`callbacks.<nom>.<expression>.<méthode>`).

## Version 3.0 et extensions de fournisseurs

Le writer refuse `webhooks` en 3.0 : un document strict échoue, un document permissif les retire avec un
avertissement. Écrire l'extension `x-webhooks` (Redoc) à la place est validé dans le principe ; **sa place est dans
`openapi-writer`** (à confirmer, voir questions), au même titre que `x-tagGroups` pour `Tag.parent` avant 3.2.

## Livrables

### Contrat (E1)

- [x] `#[Webhook]` (nom optionnel) sur une action ou un contrôleur ; `OperationDescription::webhook()`,
      `DocumentDescription::webhooks()`
- [x] `#[Callback]` répétable ; `CallbackDescription`, `OperationDescription::callbacks()`

### Extraction

- [ ] Lecture de `#[Webhook]` par l'extracteur d'attributs : nom (attribut, puis nom de route), diagnostic sans nom
- [ ] Rangement par le générateur : `operations` ou `webhooks` selon `OperationDescription::$webhook`
- [ ] Préfixe de `#[Webhook]` posé sur un contrôleur
- [ ] Lecture de `#[Callback]` : URL, méthode, payload (ressource échantillonnée ou type), réponse attendue ;
      `InvalidAttributeException` sur plusieurs sources ou `status` + `webhook` ; un webhook référencé est seulement
      nommé (`CallbackDescription::fromWebhook()`)

### Passes et écriture

- [ ] Noms de webhooks en double signalés ; méthodes d'un même nom regroupées dans un seul Path Item
- [ ] `CallbackResolutionPass` : résout les webhooks référencés une fois toutes les routes extraites (indépendant de
      l'ordre des routes), diagnostic quand le webhook n'existe pas
- [ ] Callbacks d'un même nom regroupés : une expression → un Path Item, plusieurs méthodes ; même nom, expression et
      méthode en double signalés
- [ ] Passes transverses appliquées aux webhooks et aux callbacks : tags, `operationId` uniques, composants
- [ ] `DocumentBuilder` : `OpenApi::webhooks()`, `Operation::callbacks()` ; document sans `paths` accepté en 3.1+
- [ ] 3.0 : `x-webhooks` avec `vendor_extensions: redoc`, sinon rejet strict / retrait permissif

## Question ouverte

- Un webhook est une route réelle, donc appelable, et soumise aux filtres `routes.include` / `exclude`. Garder ce
  modèle et documenter qu'il faut protéger ou désactiver ces routes (recommandé), ou lire `#[Webhook]` sur des
  méthodes de contrôleurs connus sans route ?

## Critères de sortie

- [ ] Tests unitaires de l'extraction des deux attributs et du rangement
- [ ] Scénario fonctionnel « webhooks et callbacks » : documents attendus en 3.0, 3.1 et 3.2

## Dépendance à openapi-writer

Livré par `openapi-writer` (commit `09ec7af`, branche `m7-release`) : enum `VendorExtensions` (`None` par défaut,
`Redoc`), troisième argument de `Serializer::for()`, de `toJson()` / `toYaml()` / `toArray()` et de
`Validator::validate()`.

- Avant 3.1, `webhooks` est écrit tel quel sous `x-webhooks` (contenu converti pour la version), diagnostic
  `Converted`.
- Avant 3.2, `Tag.parent` est retiré et `x-tagGroups` écrit à la racine : chaque tag rejoint le groupe de son
  ancêtre le plus haut, un tag sans parent forme son propre groupe (rien n'est masqué par Redoc).
- Une extension `x-webhooks` / `x-tagGroups` déjà posée par le document l'emporte : la fonctionnalité redevient non
  prise en charge (exception en strict, retrait en permissif), jamais de fusion silencieuse.

Le générateur passe la clé `vendor_extensions` du document (E8) : il n'a aucune conversion propre à la version.
