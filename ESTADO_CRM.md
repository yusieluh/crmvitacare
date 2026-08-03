# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **LEER PRIMERO EN CUALQUIER SESIÓN NUEVA** (Grok, Claude Code, Cursor, humano).  
> Cada cambio de código o plan **debe** actualizar este archivo + **commit + push** a GitHub.  
> Si no está aquí y en GitHub, **no está documentado**.

| Campo | Valor |
|---|---|
| **Repositorio** | https://github.com/yusieluh/crmvitacare |
| **Rama** | `main` |
| **Clone local típico** | `C:\Users\User\Documents\crmvitacare` |
| **Sitio (raíz — NO TOCAR)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Plugin** | `vitacare-crm` **v1.1.0** |
| **DB schema** | **v2** (`vitacare_crm_db_version`) — sin cambios de esquema en C-3 (canal es texto libre) |
| **Última actualización docs** | 2026-08-03 |
| **Último commit de referencia** | C-3 Instagram (este commit) |

---

## 1. Reglas inviolables (no negociar sin actualizar este archivo)

1. **`ESTADO_CRM.md` = fuente de información** del proyecto (estado, plan, decisiones, changelog, siguiente paso).
2. Superficie del CRM: **solo** https://vitacareec.org/crm — **no** alterar la home ni la raíz del sitio.
3. **No modificar** el sistema ya instalado: `vitacare-core`, `vitacare-theme`, WooCommerce, otros plugins.
4. Integración con el sistema actual = **solo lectura** de datos WP cuando haga falta; datos CRM en tablas `wp_vitacare_crm_*`.
5. Integraciones de canales = **APIs oficiales** (Meta, Google).  
   - WhatsApp: **Cloud API + Coexistence** (celular + CRM).  
   - **Prohibido:** Baileys / whatsapp-web.js / QR “dispositivo vinculado” no oficial (riesgo de ban del número).
6. Al **completar cada tarea**: actualizar este archivo → README/PROCESS si aplica → **commit + push** a `main`.

Detalle operativo: [`docs/PROCESS.md`](./docs/PROCESS.md) · Handoff para otra IA: [`docs/CONTINUAR.md`](./docs/CONTINUAR.md)

---

## 2. Qué es el producto

Plugin WordPress **independiente** instalado **junto a** el sitio VITACARE (Hostinger shared):

- Bandeja unificada multi-canal en `/crm`
- Admin: cuentas, WhatsApp Coexistence, Facebook OAuth+Página, Gmail OAuth, credenciales Meta
- No es un CRM externo (Chatwoot/erxes) ni un VPS aparte

---

## 3. Estado actual del código (v1.1.0) — YA EN GITHUB

### 3.1 Entregado y mergeado en `main`

| Entrega | Versión | Qué hace |
|---|---|---|
| PR-0 Hardening | 0.1.1 | Login `/crm`, noindex, upgrader, CSS, zip |
| PR-1 Settings | 0.2.0 | Credenciales Meta, flags, webhook stub |
| PR-2 REST + DB v2 | 0.3.0 | Conversations/messages API, UNIQUE channel+contact |
| PR-3 WA inbound | 0.4.0 | Webhook HMAC, mensajes WA, statuses |
| PR-4 WA outbound | 0.5.0 | Graph send texto |
| PR-5 Inbox UI | 0.6.0 | Bandeja 3 paneles en `/crm` |
| C-1 Cuentas | 0.7.0 | Hub de canales en admin |
| C-7 Coexistence UI | 0.7.0 | Checklist WA oficial (sin QR ilegal) |
| C-2 Facebook OAuth | 0.8.0 | Login Meta + **selector de Página** |
| C-4 Messenger | 0.9.0 | In/out Facebook Page en bandeja |
| C-5 Gmail | 1.0.0 | OAuth Google, sync INBOX, envío desde bandeja |
| **C-3 Instagram** | **1.1.0** | Cuenta profesional vinculada a la Página, webhook `object=instagram`, in/out Graph, bandeja |

### 3.2 Canales en la bandeja

| Canal | Inbound | Outbound | Cómo se conecta |
|---|---|---|---|
| WhatsApp | ✅ webhook | ✅ Graph | Credenciales + Coexistence (asistente admin) |
| Facebook Messenger | ✅ webhook page | ✅ page token | OAuth + elegir Página |
| Instagram Direct | ✅ webhook instagram | ✅ Graph (`{ig-id}/messages`) | Misma OAuth/Página de Facebook; requiere cuenta IG profesional vinculada |
| Gmail (`email`) | ✅ cron ~5 min | ✅ Gmail API | OAuth Google |
| TikTok | ❌ | ❌ | Pendiente C-6 (DMs solo si API oficial) |

### 3.3 Estructura de archivos (plugin = raíz del repo)

```
crmvitacare/
├── ESTADO_CRM.md              ← LEER PRIMERO
├── README.md
├── vitacare-crm.php           ← bootstrap v1.1.0
├── uninstall.php
├── bin/package-plugin.ps1
├── docs/
│   ├── CONTINUAR.md           ← handoff Grok/Claude/Cursor
│   ├── PROCESS.md             ← checklist post-tarea
│   ├── DESIGN.md              ← diseño largo (puede estar desfasado vs código; priorizar ESTADO)
│   └── OPS-HARD-DELETE.md
├── includes/
│   ├── class-vitacare-crm-activator.php
│   ├── class-vitacare-crm-upgrader.php
│   ├── class-vitacare-crm-settings.php
│   ├── class-vitacare-crm-accounts.php
│   ├── class-vitacare-crm-facebook-oauth.php
│   ├── class-vitacare-crm-gmail.php
│   ├── class-vitacare-crm-page.php
│   ├── class-vitacare-crm-rest.php
│   ├── class-vitacare-crm-webhook.php
│   ├── class-vitacare-crm-graph.php
│   ├── class-vitacare-crm-db.php
│   ├── class-vitacare-crm-logger.php
│   ├── class-vitacare-crm-conversations-repo.php
│   ├── class-vitacare-crm-messages-repo.php
│   ├── class-vitacare-crm-channel-whatsapp.php
│   ├── class-vitacare-crm-channel-messenger.php
│   └── class-vitacare-crm-channel-instagram.php
├── template-parts/crm-page.php, crm-shell.php
├── assets/css/crm.css, assets/js/crm.js
└── tests/test-webhook-hmac.php
```

### 3.4 Endpoints clave

| Ruta | Uso |
|---|---|
| `GET/POST /wp-json/vitacare-crm/v1/webhooks/meta` | Verify + inbound Meta (WA + Page + Instagram) |
| `GET /wp-json/vitacare-crm/v1/conversations` | Lista (auth + cap `vitacare_crm_access`) |
| `GET/POST .../conversations/{id}/messages` | Hilo / envío |
| `PATCH .../conversations/{id}` | status, assigned_to, wp_user_id |
| `GET .../ping` | Health (público, sin secretos) |

### 3.5 Admin WP (capability `manage_options`)

| Menú | Página slug |
|---|---|
| Cuentas conectadas | `vitacare-crm-accounts` |
| WhatsApp (oficial) | `vitacare-crm-whatsapp` |
| Facebook | `vitacare-crm-facebook` |
| Gmail | `vitacare-crm-gmail` |
| Credenciales | `vitacare-crm-settings` |

---

## 4. Decisiones de arquitectura (IDs)

| ID | Decisión |
|---|---|
| D-00 | ESTADO_CRM = fuente de verdad |
| D-01 | Plugin propio en Hostinger shared |
| D-02 | No tocar core/tema/Woo; solo lectura |
| D-03 | Solo URL `/crm` |
| D-04 | WA = Cloud API + Coexistence |
| D-04b | Prohibido cliente QR no oficial |
| D-12 | OAuth “Conectar cuenta” para Meta/Google/TikTok |
| D-13 | Secrets fuera de Git; opcional `VITACARE_CRM_ENCRYPTION_KEY` |
| D-14–16 | TikTok solo API oficial; DMs solo si existen |
| D-17 | Instagram no tiene OAuth propio: usa el mismo token de la Página de Facebook ya conectada (`instagram_business_account`); requiere cuenta IG profesional vinculada a esa Página en Meta Business Suite |

---

## 5. Pendiente (continuar aquí)

| Prioridad | ID | Trabajo |
|---|---|---|
| Alta | **Ops** | Instalar ZIP v1.1.0 en WP prod; configurar Meta + Google; agregar producto Instagram en la App de Meta; probar `/crm` |
| Media | **PR-6** | Media WhatsApp/Instagram (descarga, deny-direct, servir con cap) |
| Media | **C-6** | TikTok OAuth + spike DM/comentarios/métricas |
| Baja | Polish | Leads pipeline (DB v3), roles staff, notificaciones |

### Siguiente paso de ingeniería recomendado

1. `git pull` del repo.  
2. Empaquetar e instalar en Hostinger si aún no está v1.1.0.  
3. Completar Coexistence WA + Facebook Page (y cuenta Instagram vinculada) + Gmail en admin.  
4. Código: **PR-6 media** (WhatsApp + Instagram) o **C-6 TikTok**.

---

## 6. Cómo retomar (humano o Claude Code / Grok)

```bash
git clone https://github.com/yusieluh/crmvitacare.git
# o
cd C:\Users\User\Documents\crmvitacare && git pull origin main
```

1. Leer **este archivo** completo.  
2. Leer [`docs/CONTINUAR.md`](./docs/CONTINUAR.md).  
3. No reimplementar lo ya marcado ✅.  
4. Tras cada tarea: actualizar ESTADO + push (ver PROCESS.md).

---

## 7. Changelog (resumen)

| Fecha | Qué | Commit aprox. |
|---|---|---|
| 2026-08-03 | Fase 1 esqueleto | `509fa94` |
| 2026-08-03 | Docs diseño + proceso GitHub | `8d1a2ce` |
| 2026-08-03 | PR-0…PR-5 (WA + bandeja) | `475c4a9`…`8481c64` |
| 2026-08-03 | C-1, C-7, políticas OAuth/WA | `e4044e5`…`4a85c17` |
| 2026-08-03 | C-2 Facebook Página | `4a6f13f` |
| 2026-08-03 | C-4 Messenger | `053eca2` |
| 2026-08-03 | C-5 Gmail v1.0.0 | `67658d6` |
| 2026-08-03 | Handoff docs CONTINUAR + ESTADO completo | `38aca98` |
| 2026-08-03 | **C-3 Instagram v1.1.0** (OAuth Página→IG, webhook, in/out, UI) | este commit |

---

## 8. Secrets (nunca en Git)

Preferir `wp-config.php`:

```php
// Meta
define( 'VITACARE_CRM_META_APP_SECRET', '...' );
define( 'VITACARE_CRM_META_ACCESS_TOKEN', '...' );
define( 'VITACARE_CRM_META_VERIFY_TOKEN', '...' );
define( 'VITACARE_CRM_WA_PHONE_NUMBER_ID', '...' );
// Google
define( 'VITACARE_CRM_GOOGLE_CLIENT_ID', '...' );
define( 'VITACARE_CRM_GOOGLE_CLIENT_SECRET', '...' );
// Opcional cifrado options
define( 'VITACARE_CRM_ENCRYPTION_KEY', '...' );
```
