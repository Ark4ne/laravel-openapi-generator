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
- Le nom vient de l'attribut, sinon du nom de la route ; sans l'un ni l'autre, diagnostic et route ignorée.
- Le générateur range l'opération dans `DocumentDescription::$webhooks` dès l'extraction : les passes liées aux
  routes (middlewares, sécurité, erreurs 401/403/404/429) ne la voient jamais. Le chemin de la route n'apparaît pas.
- Une route qui **reçoit** les webhooks d'un tiers (Stripe, GitHub…) reste un chemin ordinaire, sans attribut.

## Callbacks

```php
#[Callback('orderShipped', url: '{$request.body#/callbackUrl}', webhook: 'order.shipped')]
#[Callback('paymentFailed', url: '{$request.body#/callbackUrl}', resource: PaymentResource::class, status: 204)]
public function store(StoreOrderRequest $request): OrderResource
```

Le callback décrit sa requête soit en réutilisant un webhook (même payload, mêmes réponses attendues), soit par une
ressource ou un type PHPDoc et le statut attendu.

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
- [ ] Lecture de `#[Callback]` : URL, méthode, payload (webhook référencé, ressource échantillonnée ou type),
      réponse attendue ; diagnostic quand le webhook référencé n'existe pas

### Passes et écriture

- [ ] Noms de webhooks en double signalés ; méthodes d'un même nom regroupées dans un seul Path Item
- [ ] Passes transverses appliquées aux webhooks et aux callbacks : tags, `operationId` uniques, composants
- [ ] `DocumentBuilder` : `OpenApi::webhooks()`, `Operation::callbacks()` ; document sans `paths` accepté en 3.1+
- [ ] 3.0 : `x-webhooks` (selon la décision ci-dessous), sinon rejet strict / retrait permissif

## Critères de sortie

- [ ] Tests unitaires de l'extraction des deux attributs et du rangement
- [ ] Scénario fonctionnel « webhooks et callbacks » : documents attendus en 3.0, 3.1 et 3.2

## Dépendance à openapi-writer

`x-webhooks` et `x-tagGroups` sont demandés à `openapi-writer` (2026-10-28, à la session qui gère le projet) : une
option de sérialisation, désactivée par défaut, qui écrit ces extensions au lieu de retirer `webhooks` (avant 3.1)
et `Tag.parent` (avant 3.2). Une fois livrée, le générateur l'active par une clé de configuration du document et
n'a plus de conversion propre à la version (`TagGroupPass` d'E7 compris).
