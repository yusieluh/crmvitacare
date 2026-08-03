# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Se lee primero en toda sesión. Se actualiza con **cada cambio**, **cada plan** y **cada tarea cerrada**.  
> Tras actualizarlo: **commit + push a GitHub** (https://github.com/yusieluh/crmvitacare).  
> Si un dato no está aquí, **no está documentado**.

| Campo | Valor |
|---|---|
| **Repositorio** | https://github.com/yusieluh/crmvitacare |
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.2.0** (Fase 1S / PR-1 settings) |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Proceso** | [`docs/PROCESS.md`](./docs/PROCESS.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información** — cada cambio y plan se documenta aquí + push a GitHub.
2. CRM **solo** en **https://vitacareec.org/crm** — **no tocar** la raíz https://vitacareec.org/
3. **No modificar** el sistema instalado (`vitacare-core`, tema, WooCommerce, etc.). Solo **lectura** de datos. Tablas propias del plugin.

```text
https://vitacareec.org/          ← NO TOCAR
https://vitacareec.org/crm      ← solo el CRM
```

---

## 0. Qué es el CRM

Plugin WordPress independiente (`vitacare-crm`) en el mismo WP de `vitacareec.org`: bandeja multi-canal + leads. Página `/crm`, REST `vitacare-crm/v1`, admin **CRM VITACARE** (settings).

---

## 1. Decisiones

| ID | Decisión |
|---|---|
| D-00 | `ESTADO_CRM.md` = fuente de información |
| D-01 | Plugin propio, sin Chatwoot/VPS |
| D-02 | No modificar sistema instalado; solo lectura |
| D-03 | Solo https://vitacareec.org/crm |
| D-04 | WhatsApp: Cloud API + Coexistence |
| D-05 | Canales WA → FB/IG → email; TikTok después |
| D-06 | Tablas `wp_vitacare_crm_*` propias |
| D-07 | Cap `vitacare_crm_access`; settings `manage_options` |
| D-08 | GitHub respaldo al cerrar tarea |
| D-09 | Diseño `docs/DESIGN.md`; MVP PR-0…PR-6 |
| D-10 | Fase 1H hardening v0.1.1 |
| D-11 | **Fase 1S:** settings Meta; secrets constant→option; webhooks registrados fail-closed v0.2.0 |

---

## 2. Plan de fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Arquitectura | ✅ |
| 1 | Esqueleto | ✅ |
| 1H | Hardening PR-0 | ✅ v0.1.1 |
| **1S** | **Settings Meta PR-1** | ✅ **v0.2.0** |
| 2 | WhatsApp inbound (PR-3) + REST read (PR-2) + outbound (PR-4) + UI (PR-5) + media (PR-6) | ⏳ |
| 3–7 | FB/IG, email, leads, polish, TikTok | ⏳ |

**MVP restante:** PR-2 → PR-3 → PR-4 → PR-5 → PR-6.

---

## 3. Qué incluye v0.2.0 (PR-1)

| Ítem | Detalle |
|---|---|
| Admin **CRM VITACARE** | Solo `manage_options` (secretos no visibles a staff solo-crm) |
| Campos | App ID, App Secret, Access Token, Verify Token, Phone Number ID, WABA ID, Graph version |
| Flags | whatsapp / facebook / instagram / email |
| Resolución secretos | `wp-config` constant **primero**, luego option; vacío en form no borra secreto |
| Cifrado opcional | Si `VITACARE_CRM_ENCRYPTION_KEY` en wp-config → AES-256-CBC en options |
| Webhook | `GET/POST /wp-json/vitacare-crm/v1/webhooks/meta` **siempre registrado** |
| Fail-closed | Sin flag/secret → **403**; GET verify con `hash_equals`; POST HMAC `X-Hub-Signature-256` |
| POST firmado | 200 `processed:false` hasta PR-3 (sin writes de mensajes) |
| `/crm` | Muestra estado flag/webhook + link a ajustes (admin) |
| `/ping` | Incluye `webhook_ready`, `whatsapp_flag`, `graph_version` (sin secretos) |

### Constants recomendadas (producción)

```php
define( 'VITACARE_CRM_META_APP_SECRET', '...' );
define( 'VITACARE_CRM_META_ACCESS_TOKEN', '...' );
define( 'VITACARE_CRM_META_VERIFY_TOKEN', '...' );
define( 'VITACARE_CRM_WA_PHONE_NUMBER_ID', '...' );
define( 'VITACARE_CRM_GRAPH_VERSION', 'v21.0' );
// opcional:
// define( 'VITACARE_CRM_ENCRYPTION_KEY', '...' );
```

### Runbook corto

1. **Apagar WhatsApp:** Admin → CRM VITACARE → desmarcar flag WhatsApp → guardar (POST webhook 403).
2. **Webhook 403 masivos:** revisar flag ON, App Secret, firma Meta; pretty permalinks ON.
3. **Token inválido (futuro Graph):** renovar en Meta; actualizar constant o settings.

---

## 4. Siguiente paso

1. **PR-2:** REST lectura conversations/messages + DB v2 (columnas unread, etc.).
2. **PR-3:** ingesta real WhatsApp (inbound + statuses) sobre el webhook ya firmado.
3. Crear App Meta + Coexistence; rellenar settings o wp-config; probar GET verify del webhook.
4. Instalar ZIP v0.2.0 en WP **sin tocar** el resto del sistema.

---

## 5. Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Fase 1 esqueleto | `509fa94` |
| 2026-08-03 | URL producción | `10d1f7c` |
| 2026-08-03 | Docs diseño + proceso | `8d1a2ce` |
| 2026-08-03 | ESTADO fuente de verdad | `5ba672e` |
| 2026-08-03 | PR-0 hardening v0.1.1 | `475c4a9` |
| 2026-08-03 | **PR-1 settings Meta v0.2.0** | este update |
