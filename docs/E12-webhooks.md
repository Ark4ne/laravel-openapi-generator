# E12 — Webhooks

**Objectif.** Documenter les requêtes que l'application **envoie** à ses consommateurs, dans `webhooks` du document
(OpenAPI 3.1 et 3.2), qu'elles soient décrites par une classe ou par une action de contrôleur.

**Dépend de :** E8 (écriture), E4 et E6 (décrire un payload et des réponses). Numéro attribué à l'ajout de
l'épique : il se place après E8 dans l'ordre de réalisation.
**Architecture :** [architecture.md](architecture.md) §1 (Discovery), §6 (Passes).

## Ce que dit la spécification

| Version | `webhooks` | Notes |
| --- | --- | --- |
| 3.0.x | Absent | Seuls les `callbacks` d'une opération existent (requête envoyée en réaction à un appel, vers une URL donnée par cet appel) |
| 3.1.x | Map nom → Path Item, à la racine du document | Un document peut n'avoir que des webhooks, sans `paths` |
| 3.2.0 | Inchangé | Les nouveautés 3.2 s'y appliquent comme ailleurs : méthode `QUERY`, `additionalOperations`, `itemSchema` des flux, `Tag.parent` |

Le writer refuse `webhooks` en 3.0 : un document strict échoue avec un message qui nomme le webhook et la version,
un document permissif les retire avec un avertissement.

## Deux façons de déclarer un webhook

**Une classe, sans route** (événement, notification, job), listée dans `documents.<nom>.webhooks` :

```php
#[Webhook(name: 'order.created', resource: OrderResource::class, headers: ['X-Signature' => 'string'])]
#[Response(204)]
final class OrderCreated
{
    // …
}
```

**Une action de contrôleur** : l'opération quitte `paths` pour `webhooks.<nom>` ; sa requête (form request,
règles, `#[RequestBody]`) décrit le payload, ses réponses celles attendues du destinataire.

```php
#[Webhook(name: 'invoice.paid')]
public function invoicePaid(InvoicePaidPayload $request): Response
```

Une route qui **reçoit** les webhooks d'un tiers (Stripe, GitHub…) reste un chemin ordinaire.

## Livrables

### Contrat (E1)

- [x] Attribut `#[Webhook]` (nom, méthode, payload par ressource ou type PHPDoc, en-têtes, tags, operationId) ;
      `#[Response]` accepté sur une classe de webhook
- [x] `Discovery\WebhookDefinition`, `OperationDescription::webhook()`, `DocumentDescription::webhooks()`
- [x] Clé de configuration `documents.<nom>.webhooks`

### Découverte et extraction

- [ ] `WebhookCollector` : classes de la configuration, attribut obligatoire (diagnostic sinon)
- [ ] `WebhookExtractor` : opération depuis l'attribut — payload (`resource` échantillonné comme une réponse, ou
      `type`), en-têtes, réponses `#[Response]` (200 sans description par défaut), tags, `operationId`
- [ ] Action portant `#[Webhook]` : `OperationDescription::webhook()` posé par l'extracteur d'opération ; la route
      n'apparaît plus dans `paths`

### Passes et écriture

- [ ] `WebhookPass` : déplace les opérations marquées dans `webhooks`, regroupe les méthodes d'un même nom dans un
      seul Path Item, signale les noms en double
- [ ] Les passes transverses s'appliquent aux webhooks (tags, `operationId` uniques, composants) mais pas la sécurité
      ni les erreurs tirées des middlewares d'une route
- [ ] `DocumentBuilder` : `OpenApi::webhooks()` ; document sans `paths` accepté en 3.1+
- [ ] 3.0 : rejet en mode strict, retrait et avertissement en mode permissif (diagnostics du writer)

## Critères de sortie

- [ ] Tests unitaires du collecteur, de l'extracteur et de la passe
- [ ] Scénario fonctionnel « webhooks » : documents attendus en 3.1 et 3.2, échec strict et retrait permissif en 3.0

## Questions ouvertes

- En 3.0, écrire l'extension `x-webhooks` (lue par Redoc) plutôt que de retirer les webhooks ?
- Ajouter `#[Callback]` pour les callbacks d'une opération (3.0+), proches des webhooks ?
- Découvrir les classes de webhook par dossier (`app/Webhooks`) en plus de la liste de configuration ?
