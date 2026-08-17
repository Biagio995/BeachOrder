# Servio – API

Base URL: `http://localhost:8000/api`

## Pubbliche (cliente)

| Method | Path | Descrizione |
|--------|------|-------------|
| GET | `/menu?locale=it&code=umbrella12` | Menu + location |
| GET | `/categories?locale=it` | Categorie con prodotti |
| GET | `/locations/code/{code}` | Location da QR code |
| POST | `/orders` | Crea ordine |
| GET | `/orders/{id}?session=` | Dettaglio ordine cliente |
| POST | `/waiter-call` | Chiama cameriere |

### POST /orders

```json
{
  "location_code": "umbrella12",
  "customer_name": "Anna",
  "customer_session": "uuid",
  "notes": "senza ghiaccio",
  "items": [
    { "product_id": 1, "quantity": 2, "notes": null }
  ]
}
```

### POST /waiter-call

```json
{
  "location_code": "umbrella12",
  "reason": "bill",
  "note": null,
  "customer_session": "uuid"
}
```

Reasons: `bill`, `assistance`, `ashtray`, `water`, `other`

## Auth staff

| Method | Path |
|--------|------|
| POST | `/login` |
| GET | `/me` |
| POST | `/logout` |

Header: `Authorization: Bearer {token}`

## Staff

| Method | Path | Ruoli |
|--------|------|-------|
| GET | `/orders?status=ready,delivering` | admin, bartender, waiter |
| PATCH | `/orders/{id}/status` | admin, bartender, waiter |
| GET | `/waiter-calls` | admin, waiter |
| PATCH | `/waiter-calls/{id}/status` | admin, waiter |

## Admin

CRUD: `/admin/categories`, `/admin/products`, `/admin/locations`, `/admin/users`

| Method | Path |
|--------|------|
| POST | `/admin/locations/{id}/regenerate-qr` |
| GET | `/admin/reports/summary` |

## Broadcast channels

- `orders` → `order.updated`
- `orders.{id}` → `order.updated`
- `waiter-calls` → `waiter-call.created`
- `location.{id}`
