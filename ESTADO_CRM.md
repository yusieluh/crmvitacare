# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.4.0** (PR-3 WhatsApp inbound) |
| **DB schema** | **v2** |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm**
3. **No modificar** sistema instalado (`vitacare-core`, tema, Woo, etc.)

---

## Plan de fases

| Fase / PR | Contenido | Estado |
|---|---|---|
| PR-0 | Hardening | ✅ v0.1.1 |
| PR-1 | Settings Meta | ✅ v0.2.0 |
| PR-2 | REST + DB v2 | ✅ v0.3.0 |
| **PR-3** | **WhatsApp inbound + statuses** | ✅ **v0.4.0** |
| PR-4 | WhatsApp outbound | ⏳ |
| PR-5 | Inbox UI | ⏳ |
| PR-6 | Media download | ⏳ |
| Post-MVP | FB/IG, email, leads… | ⏳ |

---

## PR-3 entregado (v0.4.0)

### Webhook `POST /wp-json/vitacare-crm/v1/webhooks/meta`

1. Fail-closed: flag off / secret vacío / firma inválida → **403**
2. HMAC `X-Hub-Signature-256` (`Vitacare_Crm_Webhook::valid_signature`)
3. JSON inválido → **200** sin writes
4. Payload WA → `Vitacare_Crm_Channel_Whatsapp::handle_payload`
5. Error de persistencia → **500** (Meta reintenta)

### Mensajes

| Evento | Acción |
|---|---|
| `messages[]` del contacto | Upsert conversación `whatsapp`+wa_id; insert mensaje `inbound`/`contact`; +unread |
| `messages[]` desde negocio (Coexistence, `from` ≈ display_phone) | `outbound`/`staff`; contact = `to` |
| Dedupe | UNIQUE `external_message_id` (wamid) |
| `statuses[]` | Update `delivery_status` (sent/delivered/read/failed); si no hay fila → log + no fantasma |
| Tipos | text, image, audio, video, document, interactive, etc. (body texto o placeholder); media id como `meta:{id}` hasta PR-6 |

### Archivos nuevos

- `includes/class-vitacare-crm-channel-whatsapp.php`
- `includes/class-vitacare-crm-logger.php` (uploads/vitacare-crm/logs + deny)
- `tests/test-webhook-hmac.php` — `php tests/test-webhook-hmac.php`

### Requisitos ops para mensajes reales

1. CRM VITACARE (admin): App Secret, Verify Token, flag WhatsApp ON  
2. Meta webhook URL: `https://vitacareec.org/wp-json/vitacare-crm/v1/webhooks/meta`  
3. Pretty permalinks ON  
4. Suscribir campo `messages`

---

## Siguiente paso

1. **PR-4:** envío saliente desde CRM (Graph API + POST messages).
2. Con App Meta: verificar challenge GET y un mensaje de prueba inbound.
3. **PR-5:** UI bandeja que consuma la API.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0 hardening v0.1.1 | `475c4a9` |
| 2026-08-03 | PR-1 settings v0.2.0 | `ad74f50` |
| 2026-08-03 | PR-2 REST+DB v2 v0.3.0 | `bf9d6f6` |
| 2026-08-03 | **PR-3 WhatsApp inbound v0.4.0** | este update |
