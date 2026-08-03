# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.5.0** (PR-4 WhatsApp outbound) |
| **DB schema** | **v2** |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm**
3. **No modificar** sistema instalado

---

## Plan de fases

| PR | Contenido | Estado |
|---|---|---|
| 0 | Hardening | ✅ |
| 1 | Settings Meta | ✅ |
| 2 | REST + DB v2 | ✅ |
| 3 | WhatsApp inbound | ✅ v0.4.0 |
| **4** | **WhatsApp outbound** | ✅ **v0.5.0** |
| 5 | Inbox UI | ⏳ |
| 6 | Media | ⏳ |

---

## PR-4 entregado (v0.5.0)

### `POST /wp-json/vitacare-crm/v1/conversations/{id}/messages`

```json
{ "body": "Hola, ¿en qué te ayudo?" }
```

- Auth: login + `vitacare_crm_access`
- Solo canal `whatsapp`; body max 4096; sin media (PR-6)
- Graph: `POST /{phone-number-id}/messages` timeout 15s, UA `VITACARE-CRM/{ver}`
- Persiste mensaje `outbound`/`staff` con wamid; dedupe si webhook llegó antes
- Cupo soft mensual (option `vitacare_crm_outbound_count_YYYY_MM`) — log + `soft_limit_warning`

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `vitacare_crm_outside_window` | 409 | Fuera de ventana 24h (sin templates HSM en MVP) |
| `vitacare_crm_rate_limited` | 429 | Rate limit Meta (+ cola AS si existe) |
| `vitacare_crm_graph_error` | 502 | Token, red, Graph genérico |
| `vitacare_crm_invalid_param` | 400 | Body vacío / canal incorrecto |
| `vitacare_crm_not_found` | 404 | Conversación inexistente |

Token 401/403 Graph → option `vitacare_crm_graph_token_health=invalid` (visible en `/ping` como `graph_token_health`).

### Archivo nuevo

- `includes/class-vitacare-crm-graph.php`

### Requisitos para enviar

1. Flag WhatsApp ON  
2. Access Token + Phone Number ID  
3. Conversación existente con `external_contact_id` (wa_id)  
4. Cliente dentro de ventana 24h (habla primero)

---

## Siguiente paso

1. **PR-5:** UI bandeja en `/crm` (lista + hilo + compositor que llama a esta API).
2. Probar envío real con un hilo inbound previo.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-2 | `475c4a9`…`bf9d6f6` |
| 2026-08-03 | PR-3 inbound v0.4.0 | `0c6d51f` |
| 2026-08-03 | **PR-4 outbound v0.5.0** | este update |
