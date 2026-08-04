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
| **Plugin** | `vitacare-crm` **v1.7.0** |
| **DB schema** | **v2** (`vitacare_crm_db_version`) — sin cambios de esquema en C-3/PR-6/PR-6b/D-19/D-20/C-6/D-22/D-23 |
| **Última actualización docs** | 2026-08-04 |
| **Último commit de referencia** | D-23 Fase 1 de métricas/marketing gratuito: dashboard de Reportes, salud de WhatsApp, cupo saliente endurecido a bloqueo real (este commit) |

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
7. **Una sola rama de trabajo: `main`.** No crear ramas `feature/*` ni de sesión — todo commit va directo a `main`. Si alguna herramienta (Grok, Claude Code, otra) encuentra una rama distinta a `main` en el remoto, es residual: no tiene código que `main` no tenga ya, ignorarla o borrarla, nunca seguir desarrollando ahí. Esto es lo que permite que Grok y Claude Code se turnen sobre el mismo repo sin duplicar trabajo ni generar conflictos de merge.

Detalle operativo: [`docs/PROCESS.md`](./docs/PROCESS.md) · Handoff para otra IA: [`docs/CONTINUAR.md`](./docs/CONTINUAR.md)

---

## 2. Qué es el producto

Plugin WordPress **independiente** instalado **junto a** el sitio VITACARE (Hostinger shared):

- Bandeja unificada multi-canal en `/crm`
- Admin: cuentas, WhatsApp Coexistence, Facebook OAuth+Página, Gmail OAuth, credenciales Meta
- No es un CRM externo (Chatwoot/erxes) ni un VPS aparte

---

## 3. Estado actual del código (v1.7.0) — YA EN GITHUB

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
| C-3 Instagram | 1.1.0 | Cuenta profesional vinculada a la Página, webhook `object=instagram`, in/out Graph, bandeja |
| PR-6 Media | 1.2.0 | Descarga opaca de adjuntos WA/Messenger/IG, servidor propio con cap, imagen/audio/video/documento en la bandeja |
| **PR-6b Media saliente** | **1.3.0** | Adjuntar archivo al responder por WhatsApp/Messenger: subida multipart directa a Graph (sin URL pública), botón de clip en la bandeja |
| **D-19 Puente VITACARE + D-20 Despliegue** | **1.4.0** | Ficha de solo lectura del contacto real de VITACARE en el panel de contexto de la bandeja (nombre, correo, rol, membresía, citas recientes, pendiente de pago) + workflow de GitHub Actions para desplegar el plugin a Hostinger automáticamente en cada push a `main` |
| **C-6 TikTok Login Kit** | **1.5.0** | Conector OAuth v2 oficial (`Vitacare_Crm_Tiktok_Oauth`): conecta y verifica una cuenta de TikTok (nombre, avatar, ID). **Spike concluido: TikTok no tiene ninguna API pública para DMs ni comentarios de terceros** — por eso no se agregó canal de mensajería ni webhook, a diferencia de WhatsApp/Messenger/Instagram/Gmail. Ver D-21. |
| **D-22 Zoho Mail** | **1.6.0 / 1.6.1** | `Vitacare_Crm_Zoho`, OAuth v2 oficial de Zoho Mail API (authorize, oauth/token, cuentas, carpetas, mensajes). Mismo canal `email` de la bandeja que ya usa Gmail — cada conversación guarda en `meta.mail_provider` cuál buzón la maneja. **Zoho Mail es el correo institucional y el proveedor por defecto del canal; Gmail queda como secundario/opcional** (ajuste en 1.6.1: el fallback de envío pasó de `gmail` a `zoho`, orden de tarjetas en Cuentas conectadas invertido). |
| **D-23 Fase 1 métricas/marketing gratuito** | **1.7.0** | `Vitacare_Crm_Reports` (dashboard `CRM VITACARE → Reportes`: mensajes por canal, volumen diario, conversaciones por estado, tiempo de primera respuesta, carga por agente — todo calculado sobre datos ya guardados). `Vitacare_Crm_Graph::get()` nuevo (antes solo `post()`) para leer `quality_rating`/`whatsapp_business_manager_messaging_limit` del número de WhatsApp (`Vitacare_Crm_Channel_Whatsapp::health()`, cacheado 15 min), mostrado como badge en Cuentas conectadas y en Reportes. **El cupo mensual de mensajes salientes (`vitacare_crm_outbound_soft_limit`) pasa de solo-loguear a bloquear de verdad el envío** al superarse, aplicado ahora también a Messenger e Instagram (antes solo WhatsApp lo tenía, y ni siquiera bloqueaba). Sin tablas nuevas ni cambio de DB schema. |

### 3.2 Canales en la bandeja

| Canal | Inbound | Outbound texto | Media in | Media out | Cómo se conecta |
|---|---|---|---|---|---|
| WhatsApp | ✅ webhook | ✅ Graph | ✅ descarga + sirve con cap | ✅ subida multipart | Credenciales + Coexistence (asistente admin) |
| Facebook Messenger | ✅ webhook page | ✅ page token | ✅ descarga + sirve con cap | ✅ subida multipart | OAuth + elegir Página |
| Instagram Direct | ✅ webhook instagram | ✅ Graph (`{ig-id}/messages`) | ✅ descarga + sirve con cap | ❌ (requiere URL pública, ver 3.2c) | Misma OAuth/Página de Facebook; requiere cuenta IG profesional vinculada |
| Gmail (`email`) | ✅ cron ~5 min | ✅ Gmail API | ❌ (fuera de alcance PR-6) | ❌ | OAuth Google |
| Zoho Mail (`email`, D-22) | ✅ cron ~5 min | ✅ Zoho Mail API | ❌ | ❌ | OAuth Zoho — mismo canal `email` que Gmail, ver 3.2f |
| TikTok | ❌ (no existe API) | ❌ (no existe API) | ❌ | ❌ | Login Kit conecta la cuenta (C-6), pero **no es un canal de mensajes** — ver 3.2e/D-21 |

### 3.2b Media entrante (PR-6)

- Descarga inbound de imágenes/audio/video/documentos de WhatsApp (Graph, dos pasos: media id → URL temporal → descarga con el mismo token) y de Messenger/Instagram (URL de adjunto del webhook).
- Almacenamiento **opaco**: `wp-content/uploads/vitacare-crm-media/{YYYY}/{MM}/{uuid}.{ext}`, con `.htaccess` deny-all + `index.php` vacío — no accesible por URL directa.
- Se sirve únicamente vía `GET /wp-json/vitacare-crm/v1/media/{message_id}`, protegido por la capability `vitacare_crm_access` (mismo permission_callback que el resto de la API del CRM).
- Tope de 25 MB por archivo (`limit_response_size` de `wp_remote_get`); si la descarga falla o excede el tope, el mensaje queda sin adjunto reproducible pero no rompe el webhook (se loguea y sigue).
- La API nunca expone media ids de Meta ni URLs de CDN de terceros — `media_url` en las respuestas REST es `null` o la URL propia `/media/{id}`.

### 3.2c Media saliente (PR-6b)

- `POST /wp-json/vitacare-crm/v1/media/upload` (multipart, cap `vitacare_crm_access`): staff sube un archivo, se valida el mime real (finfo, no el que manda el navegador) contra una lista blanca, tope 25 MB, se guarda en el mismo almacenamiento opaco y devuelve un `media_attachment_id` (el ref opaco, no una ruta de disco).
- `POST /conversations/{id}/messages` acepta `media_attachment_id`: WhatsApp sube el binario a `/{phone-id}/media` (multipart) y envía `type=image|audio|video|document` con el id devuelto + caption opcional en el mismo mensaje; Messenger sube a `/me/message_attachments` (multipart, `is_reusable:false`) y envía con `attachment_id` — si hay caption, va como una segunda burbuja de texto (Messenger no combina adjunto+texto en un mensaje).
- El binario **nunca sale de nuestro servidor hacia una URL pública**: ambos flujos usan subida multipart directa a Graph con el token del canal, coherente con el diseño "opaco" de PR-6.
- **Instagram queda fuera** de esta entrega: su Send API (hasta donde se pudo confirmar) espera `attachment.payload.url` públicamente accesible, no un endpoint de subida multipart tipo Messenger — exponer el almacenamiento privado por URL pública contradice el diseño de PR-6, así que se deja pendiente de una decisión de producto explícita en vez de improvisar algo no verificado en un canal usado para comunicación con pacientes.
- UI: botón 📎 en el compositor (solo WhatsApp/Messenger), sube al elegir archivo, muestra chip con el nombre y permite quitarlo antes de enviar.

### 3.2d Puente de solo lectura a VITACARE (D-19)

- `GET /wp-json/vitacare-crm/v1/conversations/{id}/vitacare-contact` devuelve `{"matched": false}` si no hay coincidencia, o `{"matched": true, "user_id", "name", "email", "roles", "appointments": [...], "membership", "pending_payments_minor"}` si la encuentra.
- Match: canal `email` → `get_user_by('email', external_contact_id)`; resto de canales → normaliza `contact_phone` a solo dígitos y busca por los últimos 9 dígitos contra `wp_vitacare_profiles.phone` (columna de texto libre, sin formato garantizado).
- Sin escritura en ninguna tabla `wp_vitacare_*` — solo `SELECT`. Si `vitacare-core` no está instalado o alguna tabla no existe (instalación vieja), responde `matched: false` en vez de un error 500.
- UI: panel de contexto de la bandeja (`crm-shell.php`/`crm.js`), sección "Contacto en VITACARE" — nombre, correo, rol, membresía activa, hasta 5 citas recientes, total pendiente de pago. Se consulta al abrir cada conversación.

### 3.2e TikTok Login Kit (C-6) — conecta cuenta, no es un canal de mensajes

- `Vitacare_Crm_Tiktok_Oauth`: OAuth v2 oficial contra los endpoints reales de TikTok for Developers (`https://www.tiktok.com/v2/auth/authorize/` para autorizar, `https://open.tiktokapis.com/v2/oauth/token/` para intercambiar/renovar token, `https://open.tiktokapis.com/v2/user/info/` para leer perfil básico), scope único `user.info.basic`, state anti-CSRF con `hash_equals()` (mismo patrón que Facebook OAuth).
- Guarda `open_id`, `union_id`, `display_name`, `avatar_url`, access/refresh token (cifrados igual que el resto de secretos) y su expiración. Página propia en `CRM VITACARE → TikTok` (conectar/renovar/desconectar), tarjeta en Cuentas conectadas.
- **Hallazgo del spike (confirmado contra la documentación oficial de TikTok for Developers, 2026-08): no existe ningún producto público de TikTok para enviar/recibir DMs ni leer/responder comentarios desde una app de terceros.** Los productos disponibles son Login Kit, Share Kit, Content Posting API, Display API, Webhooks (solo eventos de contenido), Data Portability API, Research API y Business API — ninguno cubre mensajería. Por eso, a diferencia de WhatsApp/Messenger/Instagram/Gmail, TikTok **no aparece como canal en la bandeja `/crm`** ni tiene tabla de conversaciones ni webhook de mensajes: solo verifica que la cuenta está conectada. Esto es lo que D-14–16 dejaban como condición ("DMs solo si existen") — la respuesta es que no existen.

### 3.2f Zoho Mail — canal principal de correo, institucional (D-22)

- `Vitacare_Crm_Zoho`: OAuth v2 oficial contra los endpoints reales de Zoho Mail API (`accounts.zoho.{dc}/oauth/v2/auth` para autorizar, `.../oauth/v2/token` para intercambiar/renovar; `mail.zoho.{dc}/api/accounts` para resolver `accountId`+correo, `.../folders` para ubicar el Inbox, `.../messages/view` para listar, `.../folders/{id}/messages/{id}/content` para el cuerpo, `POST .../messages` para enviar). `{dc}` es el data center de la cuenta (com/eu/in/com.au/jp), configurable en la página del conector porque Zoho tiene varios centros de datos regionales y el dominio equivocado rompe todo.
- **Zoho Mail es el correo institucional de VITACARE — canal principal de correo del CRM.** Vive bajo el mismo `channel = 'email'` de la bandeja que Gmail (no uno nuevo, "Correo" sigue siendo un solo filtro), pero **Gmail es el proveedor secundario/opcional**: `post_message()` en `class-vitacare-crm-rest.php` responde por Zoho por defecto y solo usa Gmail si la conversación quedó explícitamente marcada `meta.mail_provider = 'gmail'` (mensaje realmente recibido por ese buzón). Cada conversación guarda ese campo al importar cada mensaje entrante.
- El flag de canal `vitacare_crm_feature_email` es compartido: `disconnect()` de cada proveedor solo lo apaga si el otro tampoco está conectado (nunca se pisan entre sí). En Cuentas conectadas, la tarjeta de Zoho Mail aparece antes que la de Gmail.
- Sin soporte de adjuntos, igual que Gmail (fuera de alcance).

### 3.2g Fase 1 de métricas y marketing gratuito (D-23)

Primera de 5 fases de un plan mayor (herramientas de marketing/métricas respetando políticas de plataforma, todo gratis — ver plan completo pedido por el usuario 2026-08-04). Antes de diseñar se auditó el código existente (ningún canal tenía rate limiting real ni tabla de métricas) y se investigaron las políticas oficiales de cada plataforma; el detalle completo de las 5 fases queda en el historial de la sesión, no repetido aquí — lo que importa para continuar es:

- **Dashboard de Reportes** (`Vitacare_Crm_Reports`, página `CRM VITACARE → Reportes`): mensajes recibidos/enviados por canal (30 días), volumen diario (14 días, barras simples sin librería externa), conversaciones por estado, tiempo promedio de primera respuesta (aproximación: primer inbound vs. primer outbound por conversación, no SLA contractual), carga por agente. Todo son `SELECT` sobre `wp_vitacare_crm_conversations`/`_messages` ya existentes — sin tablas nuevas, sin llamadas externas.
- **Salud de WhatsApp**: `Vitacare_Crm_Channel_Whatsapp::health()` hace un GET de solo lectura a Graph (`quality_rating` + `whatsapp_business_manager_messaging_limit` del phone number), cacheado 15 min en transient. Se muestra como badge en la tarjeta de WhatsApp de Cuentas conectadas (si la calidad no es `GREEN`, la tarjeta pasa a estado de error) y en la sección "Salud de WhatsApp" de Reportes. Requirió agregar `Vitacare_Crm_Graph::get()` (antes la clase solo tenía `post()`).
- **Cupo de envíos salientes endurecido**: `vitacare_crm_outbound_soft_limit` (Credenciales) antes solo escribía un log al superarse y el envío seguía; ahora **bloquea de verdad** (`WP_Error` 429, `vitacare_crm_quota_exceeded`) en WhatsApp (texto y media) y, nuevo, también en Messenger e Instagram (antes sin ningún contador). Cada canal lleva su propio contador mensual (`vitacare_crm_outbound_count_{channel}_{Y_m}`, salvo WhatsApp que conserva su option histórica sin sufijo de canal por compatibilidad).
- **Explícitamente descartado en esta fase (decisión del usuario)**: plantillas de marketing de WhatsApp fuera de la ventana de 24h (tienen costo por mensaje de Meta, choca con "todo gratis") y mensajes promocionales por Messenger/Instagram fuera de ventana (Meta ya no permite esos *message tags*, sería violar política). WhatsApp/Messenger/Instagram siguen siendo solo texto libre dentro de la ventana de 24h, igual que antes.
- **Fases 2-5 pendientes** (leads pipeline DB v3, enlaces con seguimiento propio, campañas de correo con opt-in, Insights gratis de Meta): **no implementadas todavía** — el usuario pidió entrega fase por fase con su visto bueno explícito antes de cada una siguiente. No adelantar sin confirmación.

### 3.3 Estructura de archivos (plugin = raíz del repo)

```
crmvitacare/
├── ESTADO_CRM.md              ← LEER PRIMERO
├── README.md
├── vitacare-crm.php           ← bootstrap v1.4.0
├── uninstall.php
├── bin/package-plugin.ps1
├── .github/workflows/deploy-hostinger.yml  ← D-20: despliegue automático (rsync SSH) a wp-content/plugins/vitacare-crm/
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
│   ├── class-vitacare-crm-reports.php  ← D-23: dashboard de métricas locales (Fase 1, ver 3.2g)
│   ├── class-vitacare-crm-facebook-oauth.php
│   ├── class-vitacare-crm-tiktok-oauth.php  ← C-6: Login Kit, solo verifica cuenta (sin DMs, ver 3.2e)
│   ├── class-vitacare-crm-gmail.php
│   ├── class-vitacare-crm-zoho.php  ← D-22: segundo proveedor de correo, mismo canal "email" que Gmail (ver 3.2f)
│   ├── class-vitacare-crm-page.php
│   ├── class-vitacare-crm-rest.php
│   ├── class-vitacare-crm-webhook.php
│   ├── class-vitacare-crm-graph.php
│   ├── class-vitacare-crm-media.php     ← PR-6/6b: descarga/subida/almacenamiento opaco
│   ├── class-vitacare-crm-vitacare-bridge.php  ← D-19: consultas SQL de solo lectura a wp_vitacare_* (sin dependencia de vitacare-core)
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
| `GET .../media/{message_id}` | PR-6: sirve el adjunto descargado (cap `vitacare_crm_access`) |
| `POST .../media/upload` | PR-6b: sube un archivo de staff (multipart, cap `vitacare_crm_access`) |
| `GET .../conversations/{id}/vitacare-contact` | D-19: ficha de solo lectura del contacto en VITACARE si hay match por teléfono/correo (cap `vitacare_crm_access`) |
| `GET .../ping` | Health (público, sin secretos) |

### 3.5 Admin WP (capability `manage_options`)

| Menú | Página slug |
|---|---|
| Cuentas conectadas | `vitacare-crm-accounts` |
| Reportes | `vitacare-crm-reports` |
| WhatsApp (oficial) | `vitacare-crm-whatsapp` |
| Facebook | `vitacare-crm-facebook` |
| TikTok | `vitacare-crm-tiktok` |
| Zoho Mail | `vitacare-crm-zoho` |
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
| D-18 | Media saliente por Instagram queda pendiente: su Send API espera `attachment.payload.url` pública; no se expone el almacenamiento privado por URL solo para cumplir eso sin decisión de producto explícita (ver 3.2c) |
| D-19 | Puente de solo lectura a VITACARE (`Vitacare_Crm_Vitacare_Bridge`): consulta directa por SQL a `wp_users`/`wp_vitacare_profiles`/`wp_vitacare_appointments`/`wp_vitacare_membership_orders`/`wp_vitacare_payments` — sin depender de clases de `vitacare-core` (coherente con D-01/D-02: plugin independiente, cero acoplamiento de código). Match por correo exacto en canal `email`; por teléfono, normalizando a solo dígitos y comparando los últimos 9 (número significativo nacional Ecuador) contra `wp_vitacare_profiles.phone` (texto libre sin formato garantizado). Si una tabla no existe (vitacare-core no instalado o versión vieja), se degrada a "sin match" en vez de fallar. |
| D-20 | Despliegue automático a Hostinger vía GitHub Actions (`.github/workflows/deploy-hostinger.yml`), mismo patrón SSH+rsync que ya usa `vitacare-demo`, mismos 4 secrets (`HOSTINGER_SSH_KEY`/`HOST`/`PORT`/`USER`) pero copiados aparte a los Settings → Secrets propios de este repo (los secrets de GitHub son por-repo). El rsync sincroniza solo `wp-content/plugins/vitacare-crm/`, nunca toca el resto del sitio; excluye `.git`/`.github`/`dist`/`tests`/`bin`/`.idea`/`.vscode`/`.gitignore` (mismo criterio que el ZIP manual de `bin/package-plugin.ps1`). Sin `--delete`, mismo criterio de seguridad que `vitacare-demo`. |
| D-21 | **C-6 TikTok — hallazgo del spike:** TikTok for Developers no publica ningún producto de API pública para que una app de terceros envíe/reciba DMs o lea/responda comentarios (confirmado contra su documentación oficial, 2026-08: Login Kit, Share Kit, Content Posting API, Display API, Webhooks de contenido, Data Portability, Research API, Business API — ninguno cubre mensajería). Se implementó únicamente Login Kit (OAuth v2 oficial) para conectar/verificar la cuenta; TikTok **no** se agrega como canal de la bandeja ni tiene webhook de mensajes. Si TikTok publica una API de mensajería en el futuro, recién ahí se revisita como canal real. |
| D-22 | Zoho Mail (`Vitacare_Crm_Zoho`) se agrega bajo el mismo canal `email` que ya usa Gmail, no como canal nuevo — desde la bandeja, "Correo" sigue siendo un solo filtro. **Zoho Mail es el correo institucional de VITACARE y el proveedor principal/por defecto; Gmail queda secundario/opcional** (confirmado por el usuario 2026-08-04). Cada conversación guarda `meta.mail_provider` (`gmail`/`zoho`) al importar cada mensaje entrante, y `post_message()` en el REST controller lee ese valor para responder por el buzón correcto — default `zoho` si el campo no está presente. El flag de canal `vitacare_crm_feature_email` es compartido: el `disconnect()` de cada proveedor solo lo apaga si el otro tampoco está conectado. Zoho tiene varios data centers regionales (com/eu/in/com.au/jp) — el dominio se configura explícitamente en la página del conector, con `com` como default razonable, porque un dominio equivocado rompe la conexión entera. |
| D-23 | Fase 1 de un plan de 5 fases de métricas/marketing gratuito pedido por el usuario 2026-08-04 (herramientas de marketing/publicidad/métricas respetando políticas de cada plataforma para no arriesgar bloqueo de número/cuentas, todo el sistema gratuito). Esta fase: dashboard de Reportes local (sin tablas nuevas, solo agregados SQL sobre datos ya guardados), salud de WhatsApp vía GET de solo lectura a Graph, y el cupo de envíos salientes pasa de solo-loguear a bloquear de verdad (WhatsApp + nuevo en Messenger/Instagram). **Descartado explícitamente en esta fase por decisión del usuario**: plantillas de marketing de WhatsApp fuera de ventana (cuestan dinero a Meta más allá de la franja gratuita) y *message tags* promocionales de Messenger/Instagram fuera de ventana (Meta ya no los permite). **Fases 2-5 (leads pipeline DB v3, enlaces con seguimiento propio, campañas de correo con opt-in, Insights gratis de Meta) requieren visto bueno explícito del usuario antes de cada una** — no asumir continuación automática. Ver 3.2g. |

---

## 5. Pendiente (continuar aquí)

| Prioridad | ID | Trabajo |
|---|---|---|
| Alta | **Ops** | Configurar Meta (App ID/Secret) y conectar Facebook (Página) + Instagram vinculado; agregar producto Instagram en la App de Meta; probar `/crm` en vivo con un mensaje real |
| Media | **Ops** | Si se quiere usar el conector TikTok (C-6): crear una app en TikTok for Developers con producto Login Kit, registrar la redirect URI (`wp-admin/admin.php?page=vitacare-crm-tiktok`) y cargar Client Key/Secret en CRM → Credenciales. Opcional — el conector solo verifica la cuenta, no habilita mensajería (ver D-21). |
| Alta | **Ops** | Conectar Zoho Mail (D-22) — es el correo institucional y canal principal de correo del CRM: crear una app "Server-based Applications" en la Zoho API Console, registrar la redirect URI (`wp-admin/admin.php?page=vitacare-crm-zoho`), confirmar el data center de la cuenta, y cargar Client ID/Secret en esa misma página. |
| Media | **D-18** | Decidir si vale la pena exponer media públicamente (con firma/expiración) para habilitar envío de adjuntos por Instagram |
| Alta | **D-23 Fase 2** | Pipeline de leads (DB v3): tabla `wp_vitacare_crm_leads`, columna real `lead_id` en conversations, auto-creación de lead al primer mensaje, UI Leads, opt-in/opt-out. **Esperando visto bueno del usuario para arrancar** (entrega fase por fase acordada). |
| Media | **D-23 Fase 3** | Enlaces con seguimiento propio (UTM/clics autohospedado, `GET /crm/go/{code}`). Depende de Fase 2 solo para asociar clics a un lead (opcional). |
| Media | **D-23 Fase 4** | Campañas de correo (Zoho principal/Gmail secundario) con segmento por `consent_status = 'opted_in'`, límite diario duro, enlace de baja. Depende de Fase 2 (necesita leads con opt-in). |
| Baja | **D-23 Fase 5** | Insights gratis de Meta (alcance/impresiones de Página/Instagram) en el dashboard de Reportes. |
| Baja | Polish | Roles staff, notificaciones, limpieza de adjuntos subidos y nunca enviados |
| Baja | **D-19 UI** | El match VITACARE por ahora solo se muestra en el panel de contexto de la bandeja; falta decidir si conviene una acción rápida desde ahí (ej. "Ver ficha completa" hacia `panel-administrador`/`panel-rrhh` de vitacare-demo) — no implementado, solo lectura de datos, ver 3.2d |

**Ops resuelto (2026-08-04):** el despliegue automático a Hostinger (D-20) quedó verificado en producción — se cargaron los 4 secrets en este repo, el workflow corrió y sincronizó los archivos (un primer intento falló por el mismo error transitorio de `ssh-keyscan` que ya se documentó en `vitacare-demo`, se resolvió con un reintento), y el plugin se activó manualmente desde WP Admin → Plugins. `https://vitacareec.org/crm` responde 302 a login (correcto, exige sesión + capability) en vez de 404. Sección 5b (vía SSH/SFTP interactiva) queda obsoleta, ya no hace falta.

### 5b. Despliegue en producción — retomar aquí primero

> **Actualización v1.4.0:** desde D-20 existe una vía preferida que no requiere nada de lo de abajo — el usuario carga los 4 secrets de Hostinger en Settings → Secrets de este repo y `deploy-hostinger.yml` despliega solo en cada push a `main`, sin que ninguna sesión de IA necesite tocar una credencial SSH/SFTP en el chat. Lo de abajo (pasos 1-7) queda como plan B si el usuario prefiere no usar GitHub Secrets.

Contexto exacto para quien retome (Grok, Claude Code, u otra sesión):

1. Se generó y se le envió al usuario un `vitacare-crm.zip` (v1.3.0, commit `360fa0c`) vía `git archive --format=zip --prefix=vitacare-crm/ -o vitacare-crm.zip HEAD -- . ':!tests' ':!bin' ':!.gitignore'` — instalable manualmente desde WP Admin → Plugins → Subir. Esa vía sigue disponible como fallback si el acceso directo no cuaja.
2. El usuario después pidió instalarlo **directamente** (no manual) y dijo que da las credenciales que hagan falta.
3. Se le pidió elegir/mandar UNA de estas dos rutas:
   - **SSH con WP-CLI** (preferida): host/puerto SSH de Hostinger (hPanel suele dar algo tipo `subdominio` puerto `65002`), usuario, contraseña o clave privada, y confirmar si WP-CLI está habilitado. Con esto se puede subir el plugin a `wp-content/plugins/vitacare-crm/` y activarlo con `wp plugin activate vitacare-crm` sin pedirle la contraseña de wp-admin.
   - **Solo SFTP**: host/puerto/usuario/contraseña de FTP/SFTP (hPanel → Archivos → Cuentas FTP). Con esto solo se pueden subir los archivos; el usuario tiene que entrar a WP Admin y darle "Activar" él mismo.
4. **Antes de intentar conectar con lo que mande**, probar primero si el entorno de la sesión puede alcanzar el host por el puerto SSH/SFTP (22 o el que use Hostinger) — el sandbox donde corre Claude Code sale a internet por un proxy HTTPS que puede no dejar pasar tráfico SSH/FTP crudo. Si falla la conexión, decírselo claro al usuario y ofrecer la vía manual (ZIP + él lo sube) en vez de insistir.
5. Límite fijo: tocar únicamente `wp-content/plugins/vitacare-crm/`. Nunca `vitacare-core`, el tema, WooCommerce, ni nada fuera de esa carpeta (D-02).
6. Tras instalar: activar, verificar que `/crm` deja de dar 404 y muestra el panel (0 conversaciones), y recomendarle al usuario rotar/borrar la credencial temporal que haya compartido en el chat.
7. Una vez el plugin esté activo en producción, seguir con: configurar credenciales Meta/Google reales en CRM → Credenciales, completar el checklist de WhatsApp Coexistence, conectar Facebook (+ Instagram vinculado) y Gmail, y probar un mensaje real de ida y vuelta en cada canal antes de darlo por cerrado.

### Siguiente paso de ingeniería recomendado

1. `git pull` del repo (ya en `main`, v1.7.0).  
2. **Despliegue en producción ya resuelto** (ver nota en sección 5) — `/crm` responde en `vitacareec.org`, plugin activo. No hace falta repetir nada de eso.  
3. Completar Coexistence WA + Facebook Page (y cuenta Instagram vinculada) + Gmail/Zoho Mail en admin, ya en el sitio real — es lo único operativo puro que sigue pendiente.  
4. **D-23 Fase 1 (Reportes + salud WhatsApp + cupo endurecido) ya está resuelta.** Verificar en producción con datos reales (dashboard con conversaciones existentes, badge de calidad de WhatsApp) y pedir al usuario el visto bueno explícito antes de arrancar **D-23 Fase 2 (Leads pipeline DB v3)** — no adelantar sin esa confirmación, es un acuerdo explícito de la sesión que abrió el plan.  
5. Código nuevo secundario: decidir/implementar D-18 (media saliente Instagram). **C-6 TikTok y D-22 Zoho Mail ya están resueltos** — no reabrir TikTok salvo que publique una API de DMs; Zoho ya es un proveedor de correo completo (in/out) igual que Gmail.

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
| 2026-08-03 | C-3 Instagram v1.1.0 (OAuth Página→IG, webhook, in/out, UI) | `cee846e` |
| 2026-08-03 | Regla de rama única (main) para handoff Grok/Claude Code | `b189b27` |
| 2026-08-03 | PR-6 Media v1.2.0 (descarga opaca WA/Messenger/IG, endpoint `/media/{id}`, render en bandeja) | `f38597b` |
| 2026-08-03 | **PR-6b Media saliente v1.3.0** (subida multipart WA/Messenger, `/media/upload`, botón de clip en bandeja; Instagram pendiente D-18) | este commit |
| 2026-08-04 | **D-19 Puente solo-lectura a VITACARE + D-20 Despliegue automático v1.4.0** (`Vitacare_Crm_Vitacare_Bridge`, endpoint `/conversations/{id}/vitacare-contact`, ficha del contacto en el panel de contexto de la bandeja; workflow `.github/workflows/deploy-hostinger.yml` SSH+rsync a `wp-content/plugins/vitacare-crm/`) | `f8a8d50` |
| 2026-08-04 | **C-6 TikTok Login Kit v1.5.0** (`Vitacare_Crm_Tiktok_Oauth`, OAuth v2 oficial de verificación de cuenta; spike concluido: sin API pública de DMs/comentarios de terceros, TikTok no se agrega como canal de la bandeja — D-21) | `1b04c48` |
| 2026-08-04 | **Ops: despliegue verificado en producción** — 4 secrets de Hostinger cargados en este repo, workflow corrido (con un reintento por el mismo error transitorio de `ssh-keyscan` ya conocido de `vitacare-demo`), plugin activado manualmente en WP Admin, `/crm` confirmado respondiendo 302 a login en vez de 404 | (sin commit de código) |
| 2026-08-04 | **D-22 Zoho Mail v1.6.0** (`Vitacare_Crm_Zoho`, segundo proveedor de correo bajo el mismo canal `email` que Gmail — OAuth v2 oficial, sync entrante cron, envío saliente; `meta.mail_provider` por conversación decide qué buzón responde) | `7567bf9` |
| 2026-08-04 | **D-22 ajuste v1.6.1**: Zoho Mail pasa a ser el canal principal/por defecto de correo (correo institucional), Gmail queda secundario/opcional — fallback de envío cambiado de `gmail` a `zoho`, orden de tarjetas y textos actualizados | este commit |
| 2026-08-04 | **D-23 Fase 1 v1.7.0**: dashboard `Vitacare_Crm_Reports` (mensajes por canal, volumen diario, estados, primera respuesta, carga por agente), `Vitacare_Crm_Graph::get()` + `Vitacare_Crm_Channel_Whatsapp::health()` (badge de calidad/límite de mensajería de WhatsApp), cupo de envíos salientes endurecido a bloqueo real en WhatsApp/Messenger/Instagram (antes solo WhatsApp lo tenía y ni bloqueaba) | este commit |

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
