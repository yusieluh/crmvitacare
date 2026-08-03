# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Se actualiza con **cada cambio** y **cada plan** → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.3.0** (PR-2 REST + DB v2) |
| **DB schema** | **v2** |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm** — no tocar la raíz
3. **No modificar** sistema instalado; solo lectura de datos WP/WC

---

## Plan de fases

| Fase / PR | Contenido | Estado |
|---|---|---|
| 0 | Arquitectura | ✅ |
| 1 | Esqueleto | ✅ |
| 1H / PR-0 | Hardening v0.1.1 | ✅ |
| 1S / PR-1 | Settings Meta v0.2.0 | ✅ |
| **PR-2** | **REST read + DB v2 v0.3.0** | ✅ |
| PR-3 | WhatsApp inbound + statuses | ⏳ |
| PR-4 | WhatsApp outbound | ⏳ |
| PR-5 | Inbox UI | ⏳ |
| PR-6 | Media | ⏳ |
| Post-MVP | FB/IG, email, leads, polish | ⏳ |

---

## PR-2 entregado (v0.3.0)

### DB v2

- **conversations:** `unread_count`, `updated_at`, `meta`; UNIQUE `(channel, external_contact_id)`; índices `assigned_to`, `last_message_at`, `status_last_msg`
- **messages:** `message_type`, `media_mime`, `delivery_status`; UNIQUE `external_message_id`
- Upgrader idempotente `1 → 2` en `plugins_loaded` (sin re-activar)

### REST (`vitacare-crm/v1`) — requiere login + `vitacare_crm_access`

| Método | Ruta | Notas |
|---|---|---|
| GET | `/conversations` | `channel`, `status` (default open), `assigned_to`, `page`, `per_page`≤50, `q` |
| GET | `/conversations/{id}` | Detalle |
| PATCH | `/conversations/{id}` | Solo `status`, `assigned_to`, `wp_user_id`, `lead_id` (lead si hay col v3) |
| GET | `/conversations/{id}/messages` | `limit`≤50, `before_id` cursor |
| GET | `/ping` | Público; incluye `db` version |

Errores: `vitacare_crm_unauthorized` / `forbidden` / `not_found` / `invalid_param` (vía WP_Error status).

### Archivos nuevos

- `includes/class-vitacare-crm-db.php`
- `includes/class-vitacare-crm-conversations-repo.php`
- `includes/class-vitacare-crm-messages-repo.php`

---

## Siguiente paso

1. **PR-3:** ingesta WhatsApp en webhook (inbound + `message_status` → `delivery_status`) sobre DB v2.
2. App Meta + flag WhatsApp + secrets (PR-1).
3. Probar: `GET …/conversations` con cookie/nonce admin; sin auth → 401.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Esqueleto Fase 1 | `509fa94` |
| 2026-08-03 | Docs + fuente de verdad | `8d1a2ce`…`5ba672e` |
| 2026-08-03 | PR-0 hardening v0.1.1 | `475c4a9` |
| 2026-08-03 | PR-1 settings v0.2.0 | `ad74f50` |
| 2026-08-03 | **PR-2 REST + DB v2 v0.3.0** | este update |
