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
| **Plugin** | `vitacare-crm` **v1.15.3** |
| **DB schema** | **v5** (`vitacare_crm_db_version`) — sin cambios de esquema desde D-27; D-26 agregó `wp_vitacare_crm_email_campaigns` + `wp_vitacare_crm_campaign_recipients`; D-25 agregó `wp_vitacare_crm_link_clicks`; D-24 agregó `wp_vitacare_crm_leads` + columna `lead_id` en conversations; sin cambios de esquema en C-3/PR-6/PR-6b/D-19/D-20/C-6/D-22/D-23 |
| **Última actualización docs** | 2026-08-05 |
| **Último commit de referencia** | Fix v1.15.3: causa raíz real del fallo OAuth Facebook — `wp_safe_redirect()` sin `www.facebook.com` en `allowed_redirect_hosts` (este commit) |

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

## 3. Estado actual del código (v1.11.0) — YA EN GITHUB

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
| **D-24 Fase 2 pipeline de leads** | **1.8.0** | DB v3: tabla nueva `wp_vitacare_crm_leads` + columna real `lead_id` en `wp_vitacare_crm_conversations` (ya se referenciaba defensivamente, nunca existía). `Vitacare_Crm_Leads_Repo` (CRUD, opt-in/opt-out con rastro de origen/fecha, import CSV, dedupe por teléfono/correo). Auto-alta de lead (`consent_status='unknown'`) en cada contacto nuevo o existente sin lead vía `Vitacare_Crm_Conversations_Repo::upsert_contact()`. Admin `CRM VITACARE → Leads` (crear manual, filtrar, opt-in/opt-out, importar CSV, "Convertir a conversación"). Endpoints REST `GET/POST /leads`, `GET/PUT /leads/{id}`, `PUT /leads/{id}/consent`, `POST /leads/import`. Deep link `?c=ID` agregado a la bandeja (`crm.js`) para que "Ver hilo" desde Leads abra la conversación directamente. |
| **D-25 Fase 3 enlaces con seguimiento propio** | **1.9.0** | DB v4: tabla nueva `wp_vitacare_crm_link_clicks`. `Vitacare_Crm_Links_Repo` genera códigos cortos únicos (7 caracteres, alfabeto sin ambigüedades 0/O/1/l/I), incrusta UTM (`utm_source=vitacare-crm&utm_medium=crm&utm_campaign={tag}`) en la URL de destino al crear el enlace, y cuenta clics. Redirector público `GET /wp-json/vitacare-crm/v1/go/{code}` (sin auth, ver nota de diseño abajo) — `wp_redirect()` normal (no `wp_safe_redirect()`) porque el destino nunca sale del request público, siempre viene de lo que el staff guardó al crear el código. Admin `CRM VITACARE → Enlaces` (generar enlace, tabla con clics). Reportes (Fase 1) suma sección "Clics por campaña". Endpoints REST `GET/POST /links` para que Fase 4 (campañas de correo) reutilice la lógica al insertar un enlace de seguimiento en el cuerpo del correo. |
| **D-26 Fase 4 campañas de correo con opt-in** | **1.10.0** | DB v5: tablas nuevas `wp_vitacare_crm_email_campaigns` + `wp_vitacare_crm_campaign_recipients`. `Vitacare_Crm_Email_Campaigns_Repo` congela el segmento (solo leads `consent_status='opted_in'` con correo, opcionalmente filtrados por etiqueta) como cola de destinatarios al crear la campaña; el despacho real lo hace un cron (`vitacare_crm_five_minutes`) en lotes de 10, **re-verificando el opt-in de cada lead en el momento del envío** (no solo al crear la campaña) y respetando un `daily_cap` propio de cada campaña (default 200/día). `Vitacare_Crm_Zoho::send_campaign_email()` / `Vitacare_Crm_Gmail::send_campaign_email()` (Zoho principal, Gmail si Zoho no está conectado) envían el correo con pie de baja agregado automáticamente. Baja pública sin login: `GET/POST /wp-json/vitacare-crm/v1/unsubscribe/{token}` (token HMAC sin estado, ver `Vitacare_Crm_Leads_Repo::unsubscribe_token()`), marca `consent_status='opted_out'`. Admin `CRM VITACARE → Campañas de correo` (crear borrador, iniciar/pausar envío, ver progreso). |
| **D-27 Fase 5 (última) Insights gratis de Meta** | **1.11.0** | `Vitacare_Crm_Facebook_Oauth::SCOPES` gana `read_insights`/`instagram_manage_insights`. Métodos nuevos `get_page_insights()` (impresiones + interacciones de la Página, últimos 7 días, `page_impressions`/`page_post_engagements` — se evitó `page_impressions_unique` porque Meta lo deprecó) y `get_instagram_insights()` (`reach`/`profile_views` de la cuenta IG vinculada), ambos cacheados 30 min, sin gasto en anuncios. Sección "Insights de Meta" nueva en Reportes (Fase 1), con degradación clara si falta el permiso (pide reconectar Facebook) o si el canal no está conectado. **Cuentas ya conectadas antes de este cambio no tienen el permiso nuevo automáticamente** — hay que pulsar "Reconectar / cambiar cuenta" en CRM VITACARE → Facebook una vez. **Cierra el plan de 5 fases de métricas/marketing gratuito** (D-23 a D-27). |

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
- **Fases 3-5 pendientes** (enlaces con seguimiento propio, campañas de correo con opt-in, Insights gratis de Meta): **no implementadas todavía** — el usuario pidió entrega fase por fase con su visto bueno explícito antes de cada una siguiente. No adelantar sin confirmación.

### 3.2h Fase 2 — Pipeline de leads, DB v3 (D-24)

- **Tabla `wp_vitacare_crm_leads`** (DB v3, `Vitacare_Crm_Upgrader::upgrade_to_3()` / `Vitacare_Crm_Activator::install_tables_v3()`): `id, name, phone, email, source (manual|whatsapp|facebook|instagram|email|import), tags (JSON), consent_status (unknown|opted_in|opted_out), consent_source, consent_at, notes, assigned_to, conversation_id, created_at, updated_at`. La columna `lead_id` en `wp_vitacare_crm_conversations` (ya referenciada defensivamente desde antes de esta fase) se crea de verdad aquí.
- **Un lead ≠ opt-in de marketing.** `Vitacare_Crm_Conversations_Repo::upsert_contact()` crea (o reutiliza, por teléfono/correo) un lead automáticamente en `consent_status='unknown'` para cualquier contacto nuevo o ya existente sin lead — eso solo registra al contacto, **no** habilita campañas. Marcar `opted_in` es siempre una acción explícita del staff desde `CRM VITACARE → Leads`, vía `Vitacare_Crm_Leads_Repo::set_consent()` (deja rastro de `consent_source`/`consent_at`).
- **Dedupe por contacto** (`Vitacare_Crm_Leads_Repo::find_by_contact()`): antes de crear un lead nuevo automáticamente, busca uno existente por correo exacto o por los últimos 8 dígitos del teléfono (mismo criterio que el puente D-19) y lo reutiliza — evita duplicados cuando el mismo número/correo ya tenía lead o escribe por dos canales distintos.
- **Admin `CRM VITACARE → Leads`** (`Vitacare_Crm_Leads`, formularios WP nativos con nonce, mismo patrón que Cuentas/Reportes): alta manual, filtro por fuente/consentimiento/búsqueda, botones opt-in/opt-out por fila, importar CSV (columnas `name,phone,email,tags` — tags separados por `;` dentro de la celda), y **"Convertir a conversación"**: crea el hueco de conversación (WhatsApp si hay teléfono, si no correo vía Zoho) para que el staff pueda abrir el hilo desde `/crm` — no envía nada; WhatsApp igual exige que el contacto escriba primero dentro de la ventana de 24h, eso no cambia.
- **Deep link a la bandeja**: `assets/js/crm.js` ahora lee `?c=ID` al cargar y abre esa conversación directamente — lo usa el enlace "Ver hilo" de Leads, pero sirve para cualquier enlace externo a una conversación puntual.
- **Endpoints REST** (`vitacare-crm/v1`, cap `vitacare_crm_access`): `GET/POST /leads`, `GET/PUT /leads/{id}`, `PUT /leads/{id}/consent`, `POST /leads/import` (multipart `file` o JSON `{csv: "..."}"`). Pensados para que las Fases 3-4 (enlaces con seguimiento, campañas de correo) los consuman sin duplicar lógica.

### 3.2i Fase 3 — Enlaces con seguimiento propio (D-25)

- **Tabla `wp_vitacare_crm_link_clicks`** (DB v4, `Vitacare_Crm_Upgrader::upgrade_to_4()` / `Vitacare_Crm_Activator::install_tables_v4()`): `id, code (único), target_url, campaign_tag, lead_id, clicks_count, created_at, last_click_at`.
- **100% autohospedado, sin Bitly ni acortador de terceros** (coherente con "todo gratis"). `Vitacare_Crm_Links_Repo::create()` genera un código de 7 caracteres (alfabeto sin 0/O/1/l/I para evitar confusión al transcribir a mano) y guarda `target_url` con UTM ya incrustado (`utm_source=vitacare-crm`, `utm_medium=crm`, `utm_campaign={campaign_tag}`) — así cualquier analítica del sitio destino (Google Analytics, Meta Pixel, etc.) también ve la campaña.
- **Decisión de diseño — por qué `/wp-json/vitacare-crm/v1/go/{code}` y no un `/crm/go/{code}` "bonito"**: un rewrite rule custom de WordPress exige `flush_rewrite_rules()` bien sincronizado y no tiene fallback automático si el sitio usa permalinks "Simple" (rompería en 404 sin aviso). El namespace REST del plugin ya está probado en producción (webhook, `/leads`, etc.) y WordPress le da fallback automático (`?rest_route=/vitacare-crm/v1/go/{code}`) incluso con permalinks planos — mismo criterio que ya usa `Vitacare_Crm_Settings::webhook_url()`. Se prioriza que el enlace **nunca** se rompa sobre que sea más corto.
- **No es un open-redirect**: el handler `GET /go/{code}` usa `wp_redirect()` (no `wp_safe_redirect()`) a propósito — el destino jamás sale del propio request público, siempre es el `target_url` que el staff (cap `manage_options`) guardó al crear el enlace desde el admin. El endpoint es público sin auth porque así lo va a abrir el contacto final (WhatsApp/correo/redes), pero solo lee de la tabla, nunca acepta una URL arbitraria del visitante.
- **Admin `CRM VITACARE → Enlaces`** (`Vitacare_Crm_Links`): formulario URL destino + etiqueta de campaña + lead opcional (ID numérico), tabla con el enlace corto (input de solo lectura, clic para seleccionar y copiar), destino, campaña, contador de clics y último clic.
- **Reportes** (Fase 1) suma la sección "Clics por campaña": agrega `SUM(clicks_count)` agrupado por `campaign_tag` — visible en el mismo dashboard donde ya está la salud de WhatsApp y el volumen de mensajes.
- **Endpoints REST**: `GET/POST /links` (cap `vitacare_crm_access`, para generar enlaces programáticamente — los usará Fase 4) y `GET /go/{code}` (público, redirector).

### 3.2j Fase 4 — Campañas de correo con opt-in (D-26)

- **Tablas** (DB v5, `Vitacare_Crm_Upgrader::upgrade_to_5()` / `Vitacare_Crm_Activator::install_tables_v5()`): `wp_vitacare_crm_email_campaigns` (subject, body, segment_tag, status draft/sending/paused/done, daily_cap, total_recipients, sent_count) y `wp_vitacare_crm_campaign_recipients` (campaign_id, lead_id, email, status pending/sent/failed/skipped_opted_out, sent_at, error) — cola de destinatarios, una fila por lead por campaña.
- **El segmento se congela al crear la campaña, pero el opt-in se re-verifica al despachar.** `create_campaign()` usa `Vitacare_Crm_Leads_Repo::all_opted_in_with_email()` (solo `consent_status='opted_in'`, con correo, filtrado por `segment_tag` si se puso una etiqueta) para poblar `campaign_recipients` en estado `pending`. Al momento de enviar cada correo, el cron vuelve a leer el `consent_status` actual del lead — si alguien se dio de baja entre la creación y el envío, ese destinatario se marca `skipped_opted_out` y **no recibe nada**. Esto es intencional: el check de opt-in vive a nivel de query/dispatch, no solo en la UI de creación.
- **Despacho por lotes, nunca todo de una vez**: `Vitacare_Crm_Email_Campaigns_Repo::dispatch_batch()` corre en el cron `vitacare_crm_five_minutes` (mismo intervalo que ya usan Gmail/Zoho para sync), procesa hasta 10 destinatarios pendientes por campaña por tick, y respeta el `daily_cap` propio de cada campaña (opción por fecha `vitacare_crm_campaign_{id}_sent_{Y_m_d}`) — si se llega al tope del día, la campaña sigue al día siguiente sin intervención manual. **Nota de alcance**: el `daily_cap` es por campaña, no un tope global de la cuenta de correo — si hubiera varias campañas `sending` a la vez, el total del día podría sumar más de un `daily_cap`; se acepta porque en la práctica solo corre una campaña activa a la vez y el default (200) ya deja margen de sobra frente a los límites reales de Zoho/Gmail.
- **Envío**: `Vitacare_Crm_Zoho::send_campaign_email()` / `Vitacare_Crm_Gmail::send_campaign_email()` (nuevos métodos, mismo POST crudo que ya usaba `send_text()` pero sin conversación/hilo de soporte asociado — un envío de campaña no es un mensaje de la bandeja). Zoho se intenta primero (correo institucional, D-22); Gmail solo si Zoho no está conectado.
- **Pie de baja obligatorio, agregado automáticamente** (el staff no lo escribe a mano): cada correo enviado incluye un enlace `GET/POST /wp-json/vitacare-crm/v1/unsubscribe/{token}` público, sin login. El token es una firma HMAC del `lead_id` con las sales de WordPress (`Vitacare_Crm_Leads_Repo::unsubscribe_token()`/`resolve_unsubscribe_token()`) — no requiere columna nueva ni tabla de tokens; si las sales de WP rotan, los enlaces viejos dejan de servir (riesgo aceptado). Al abrirlo, marca el lead `consent_status='opted_out'` y muestra una página HTML mínima de confirmación (no JSON, se abre directo desde el cliente de correo).
- **Admin `CRM VITACARE → Campañas de correo`**: crear campaña (asunto, cuerpo, etiqueta de segmento opcional, cupo diario) que queda en `draft` con el conteo real de destinatarios ya calculado; botones "Iniciar envío"/"Pausar"/"Reanudar"; tabla con progreso (`sent_count / total_recipients`). Reportes (Fase 1) suma una tabla resumen de campañas.

### 3.2k Fase 5 — Insights gratis de Meta (D-27, última fase del plan)

- **Scopes**: `Vitacare_Crm_Facebook_Oauth::SCOPES` gana `read_insights` (Página) e `instagram_manage_insights` (cuenta IG profesional vinculada). Meta no retro-otorga permisos nuevos a tokens ya emitidos — **cualquier cuenta conectada antes de este cambio necesita pulsar "Reconectar / cambiar cuenta" en CRM VITACARE → Facebook una sola vez** para que el nuevo scope quede autorizado; mientras tanto, los widgets de Insights muestran el error de Graph con esa instrucción en vez de romper la pantalla.
- **`Vitacare_Crm_Facebook_Oauth::get_page_insights()`**: `GET /{page-id}/insights?metric=page_impressions,page_post_engagements&period=day&since=...&until=...` (últimos 7 días), suma los valores diarios devueltos. Se evitó a propósito `page_impressions_unique` y las demás métricas `*_unique`/`post_impressions*` porque Meta las declaró deprecadas (verificado contra la documentación oficial antes de implementar, no por intuición).
- **`Vitacare_Crm_Facebook_Oauth::get_instagram_insights()`**: mismo patrón sobre `/{ig-id}/insights?metric=reach,profile_views&period=day` — se usó `reach` en vez de `impressions` porque `impressions` viene perdiendo soporte en las versiones recientes de la Graph API para cuentas de Instagram.
- Ambos métodos cachean 30 min (`vitacare_crm_fb_page_insights`/`vitacare_crm_ig_insights`, vía transient) para no golpear Graph en cada carga de Reportes; se limpian al desconectar Facebook.
- **Sección "Insights de Meta" en Reportes**: dos tarjetas (Página / Instagram), cada una con tres estados — no conectado (enlaza a Facebook), error de Graph (muestra el mensaje + sugiere reconectar), o datos reales. Cero costo: son las únicas llamadas de esta fase, y son de solo lectura sobre datos que Meta ya expone gratis a cualquier Página/cuenta profesional propia.
- **Con esta fase se completa el plan de 5 fases de métricas/marketing gratuito pedido por el usuario 2026-08-04** (D-23 Reportes/salud WhatsApp/cupo endurecido, D-24 Leads, D-25 Enlaces, D-26 Campañas de correo, D-27 Insights de Meta). No hay una Fase 6 planeada — cualquier trabajo nuevo de marketing/métricas a partir de aquí es una tarea nueva, no continuación automática de este plan.

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
│   ├── class-vitacare-crm-leads-repo.php  ← D-24: CRUD leads + auto-alta + consentimiento (Fase 2, ver 3.2h)
│   ├── class-vitacare-crm-leads.php       ← D-24: admin Leads (crear/filtrar/opt-in/import CSV/convertir)
│   ├── class-vitacare-crm-links-repo.php  ← D-25: códigos + UTM + clics (Fase 3, ver 3.2i)
│   ├── class-vitacare-crm-links.php       ← D-25: admin Enlaces (generar/listar)
│   ├── class-vitacare-crm-email-campaigns-repo.php  ← D-26: cola de destinatarios + despacho por cron (Fase 4, ver 3.2j)
│   ├── class-vitacare-crm-email-campaigns.php       ← D-26: admin Campañas de correo
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
| `GET/POST /leads` | D-24: listar (filtros source/consent_status/tag/q) / crear lead manual |
| `GET/PUT /leads/{id}` | D-24: detalle / actualizar campos editables (no consent_status) |
| `PUT /leads/{id}/consent` | D-24: marcar opt-in/opt-out con rastro de origen |
| `POST /leads/import` | D-24: importar CSV (multipart `file` o JSON `{csv}`) |
| `GET/POST /links` | D-25: listar / crear enlace con seguimiento (UTM incrustado) |
| `GET /go/{code}` | D-25: redirector público — cuenta el clic y redirige a `target_url` (sin auth) |
| `GET/POST /unsubscribe/{token}` | D-26: baja pública de campañas de correo (sin auth), marca `consent_status='opted_out'` |
| `GET .../ping` | Health (público, sin secretos) |

### 3.5 Admin WP (capability `manage_options`)

| Menú | Página slug |
|---|---|
| Cuentas conectadas | `vitacare-crm-accounts` |
| Reportes | `vitacare-crm-reports` |
| Leads | `vitacare-crm-leads` |
| Enlaces | `vitacare-crm-links` |
| Campañas de correo | `vitacare-crm-campaigns` |
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
| D-23 | Fase 1 de un plan de 5 fases de métricas/marketing gratuito pedido por el usuario 2026-08-04 (herramientas de marketing/publicidad/métricas respetando políticas de cada plataforma para no arriesgar bloqueo de número/cuentas, todo el sistema gratuito). Esta fase: dashboard de Reportes local (sin tablas nuevas, solo agregados SQL sobre datos ya guardados), salud de WhatsApp vía GET de solo lectura a Graph, y el cupo de envíos salientes pasa de solo-loguear a bloquear de verdad (WhatsApp + nuevo en Messenger/Instagram). **Descartado explícitamente en esta fase por decisión del usuario**: plantillas de marketing de WhatsApp fuera de ventana (cuestan dinero a Meta más allá de la franja gratuita) y *message tags* promocionales de Messenger/Instagram fuera de ventana (Meta ya no los permite). Ver 3.2g. |
| D-24 | Fase 2 del mismo plan (leads pipeline, DB v3), aprobada por el usuario 2026-08-04 tras entregarse y verificarse la Fase 1. Tabla `wp_vitacare_crm_leads` + columna real `lead_id` en conversations (ya referenciada defensivamente desde antes). Auto-alta de lead en `consent_status='unknown'` para todo contacto nuevo o existente sin lead (con dedupe por teléfono/correo) — **escribir al CRM nunca es opt-in de marketing por sí solo**, eso solo se marca explícitamente en `CRM VITACARE → Leads`. Admin de leads + endpoints REST + deep link `?c=ID` en la bandeja. Ver 3.2h. |
| D-25 | Fase 3 del mismo plan (enlaces con seguimiento propio), aprobada por el usuario 2026-08-04 tras Fase 2. Tabla `wp_vitacare_crm_link_clicks` (DB v4), códigos cortos únicos con UTM incrustado, redirector público **`GET /wp-json/vitacare-crm/v1/go/{code}`** (no `/crm/go/{code}` como bosquejó el plan original — se prefirió el namespace REST ya probado en producción, con fallback automático `?rest_route=` si el sitio usa permalinks planos, en vez de un rewrite rule custom sin ese fallback; ver 3.2i para el razonamiento completo). Admin Enlaces + sección "Clics por campaña" en Reportes + endpoints REST `/links`. |
| D-26 | Fase 4 del mismo plan (campañas de correo con opt-in y límites duros), aprobada por el usuario 2026-08-04 tras Fase 3. Tablas `wp_vitacare_crm_email_campaigns` + `wp_vitacare_crm_campaign_recipients` (DB v5). El segmento se congela al crear la campaña (`Vitacare_Crm_Leads_Repo::all_opted_in_with_email()`, solo opt-in con correo) pero **el opt-in se re-verifica en el momento de cada envío** vía el cron de despacho (`vitacare_crm_five_minutes`, lotes de 10, tope diario propio de cada campaña, default 200). Zoho principal / Gmail secundario para el envío (`send_campaign_email()` nuevo en ambas clases, sin tocar conversaciones/hilos de soporte). Pie de baja obligatorio agregado automáticamente, con token HMAC sin estado (`Vitacare_Crm_Leads_Repo::unsubscribe_token()`) y endpoint público `GET/POST /unsubscribe/{token}`. Admin Campañas de correo (crear/iniciar/pausar) + resumen en Reportes. Ver 3.2j. |
| D-27 | Fase 5 (última) del mismo plan (Insights gratis de Meta), aprobada por el usuario 2026-08-04 tras Fase 4. Sin tablas nuevas — solo agrega scopes OAuth (`read_insights`, `instagram_manage_insights`) y dos métodos de solo lectura (`get_page_insights()`, `get_instagram_insights()`) que leen impresiones/interacciones de la Página y alcance/visitas de perfil de Instagram, cacheados 30 min, mostrados en una sección nueva de Reportes. **Cuentas Facebook ya conectadas antes de este cambio deben reconectar una vez** para autorizar los scopes nuevos — Meta no los agrega solo a tokens existentes. Se evitaron a propósito las métricas que Meta ya declaró deprecadas (`page_impressions_unique`, `impressions` de Instagram) — verificado contra documentación oficial antes de implementar. **Con esta fase se cierra el plan de 5 fases de métricas/marketing gratuito (D-23 a D-27) — no hay Fase 6 planeada.** Ver 3.2k. |
| D-28 | **Nuevo plan (2026-08-05), en curso por fases — reestructuración segura del módulo de integraciones Meta** (WhatsApp/Messenger/Instagram, con Embedded Signup vía WhatsApp Business App Coexistence para el número real +593 98 469 2001, `featureType: whatsapp_business_app_onboarding`). **Fase 1 (diagnóstico, sin cambios) entregada**: confirmado que Messenger/Instagram YA usan almacenamiento propio (`vitacare_crm_fb_page_token`, opción separada del `access_token` de WhatsApp) — no hay reutilización incorrecta de credenciales entre canales como se sospechaba al iniciar el plan; lo que falta es solo reordenar la UI en una sección "Integraciones" con pestañas por canal. Confirmado también que el 403 del webhook (`/wp-json/vitacare-crm/v1/webhooks/meta`) es **fail-closed intencional** (ningún canal tiene su feature flag activado todavía en producción — `whatsapp_flag:false` vía `/ping`), no un bug. **Fase 2 (respaldo) entregada en este commit**: `Vitacare_Crm_Backup` — botón "Generar respaldo ahora" en Credenciales que copia los valores actuales de App ID/Secret, tokens, WABA/Phone Number ID y credenciales de Facebook/Instagram a un JSON fuera del directorio público del plugin (mismo `VITACARE_PRIVATE_STORAGE_DIR` que ya usa vitacare-core), sin desencriptar ni mostrar secretos; restauración por archivo con confirmación explícita server-side. **Fase 3 entregada en este commit**: `Vitacare_Crm_Integrations_Page` — submenú "Integraciones" con pestañas Meta general/WhatsApp/Messenger/Instagram/Gmail/Zoho Mail/Diagnóstico, reusando componentes ya existentes de wp-admin (no rediseña nada, enlaza a Credenciales/Facebook/Gmail/Zoho en vez de duplicar su lógica de edición). `Vitacare_Crm_Whatsapp_Embedded_Signup` — asistente oficial de WhatsApp Business App Coexistence (FB.login + `featureType: whatsapp_business_app_onboarding`, sin QR local ni librerías no oficiales): el navegador solo recibe App ID (público) y Configuration ID (no es secreto); el `code` temporal se intercambia servidor-a-servidor vía AJAX admin (nonce + `manage_options`) por un access token real que nunca vuelve al navegador; WABA ID/Phone Number ID llegan del evento `postMessage` oficial de Meta. **No conecta ningún activo real por sí solo** — el usuario debe completar el diálogo de Meta manualmente cuando esté listo (número real: +593 98 469 2001). El campo `access_token`/`vitacare_crm_meta_access_token` se reutiliza tal cual (ya es de uso exclusivo WhatsApp desde antes de este plan, confirmado en Fase 1) — no hubo migración de datos porque no hacía falta. **Fase 4 entregada en este commit** (corrección puntual pedida tras revisar Fase 3 en vivo: Credenciales seguía mezclando App ID/Secret bajo "WhatsApp / Meta" y no reflejaba Messenger/Instagram como canales propios): Credenciales reorganizada en 4 bloques con ancla (`#meta`, `#whatsapp`, `#messenger`, `#instagram`) — Meta general (App ID/Secret/Verify Token/Graph version/Webhook URL/Redirect URI de solo lectura), WhatsApp (renombrado "WhatsApp System User Access Token", + Business ID, Configuration ID, número internacional, estado de coexistencia y de webhook — todos editables), Messenger e Instagram (solo lectura desde `Vitacare_Crm_Facebook_Oauth`, ya que se gestionan por OAuth y pegar un token a mano los desincronizaría del real). Nuevas opciones no destructivas: `vitacare_crm_wa_business_id`, `vitacare_crm_wa_embedded_config_id`, `vitacare_crm_wa_phone`. Nueva constante preferida `VITACARE_CRM_WA_SYSTEM_USER_TOKEN` (con fallback a la antigua `VITACARE_CRM_META_ACCESS_TOKEN`, marcada obsoleta pero funcional) y constantes opcionales `VITACARE_CRM_MESSENGER_PAGE_ID`/`_PAGE_ACCESS_TOKEN`/`VITACARE_CRM_INSTAGRAM_ACCOUNT_ID`/`_ACCESS_TOKEN` en `Vitacare_Crm_Facebook_Oauth` (sin tocar el flujo de OAuth en sí). Botón "Borrar" independiente por secreto con confirmación server-side. Feature flags de Messenger/Instagram/Correo ya no dicen "(futuro)". **No hay Fase 5 planeada por ahora** — conectar los activos reales queda a criterio y ritmo del usuario, usando lo ya entregado. |
| D-29 | **Excepción explícita a la regla de "nunca mostrar secretos completos" (2026-08-05), confirmada con el usuario vía pregunta directa** (opciones: copiar sin mostrar / mostrar en pantalla con botón ojo / dejarlo como estaba — eligió **mostrar en pantalla**). Se agregó botón "Ver" solo a **App Secret** y **Verify Token** en Credenciales — `Vitacare_Crm_Settings::ajax_reveal_secret()`, AJAX admin gateado por nonce + `manage_options` + lista blanca (`REVEALABLE_KEYS`), que devuelve el valor real (funciona igual si viene de wp-config o de la BD) y lo pone en el campo (`type=password` → `type=text`). Un segundo clic ("Ocultar") vuelve a `password` y **limpia** el campo, para no reenviar el valor real si se guarda el formulario sin querer cambiarlo. Cada revelado queda auditado en el logger (usuario + nombre del campo, nunca el valor). **WhatsApp System User Token y TikTok Client Secret NO son revelables** — el usuario solo pidió los dos campos de Meta general, no se amplió el alcance. |

---

## 5. Pendiente (continuar aquí)

| Prioridad | ID | Trabajo |
|---|---|---|
| Alta | **Ops (D-28)** | Con el módulo de Integraciones ya listo (Fase 1-3): generar el respaldo en Credenciales, luego en `CRM VITACARE → Integraciones → WhatsApp` cargar el Configuration ID de Embedded Signup (se genera en Meta for Developers → WhatsApp → Configuración → Embedded Signup) y usar el botón "Conectar WhatsApp Business mediante QR seguro" cuando esté listo para conectar el número real +593 98 469 2001 |
| Alta | **Ops** | Configurar Meta (App ID/Secret) y conectar Facebook (Página) + Instagram vinculado; agregar producto Instagram en la App de Meta; probar `/crm` en vivo con un mensaje real |
| Media | **Ops (D-27)** | Si Facebook ya estaba conectado antes de esta fase: pulsar "Reconectar / cambiar cuenta" en CRM VITACARE → Facebook una vez, para autorizar los scopes nuevos `read_insights`/`instagram_manage_insights` — si no, la sección "Insights de Meta" de Reportes muestra error pidiendo reconectar. |
| Media | **Ops** | Si se quiere usar el conector TikTok (C-6): crear una app en TikTok for Developers con producto Login Kit, registrar la redirect URI (`wp-admin/admin.php?page=vitacare-crm-tiktok`) y cargar Client Key/Secret en CRM → Credenciales. Opcional — el conector solo verifica la cuenta, no habilita mensajería (ver D-21). |
| Alta | **Ops** | Conectar Zoho Mail (D-22) — es el correo institucional y canal principal de correo del CRM: crear una app "Server-based Applications" en la Zoho API Console, registrar la redirect URI (`wp-admin/admin.php?page=vitacare-crm-zoho`), confirmar el data center de la cuenta, y cargar Client ID/Secret en esa misma página. |
| Media | **D-18** | Decidir si vale la pena exponer media públicamente (con firma/expiración) para habilitar envío de adjuntos por Instagram |
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

1. `git pull` del repo (ya en `main`, v1.11.0).  
2. **Despliegue en producción ya resuelto** (ver nota en sección 5) — `/crm` responde en `vitacareec.org`, plugin activo. No hace falta repetir nada de eso.  
3. Completar Coexistence WA + Facebook Page (y cuenta Instagram vinculada) + Gmail/Zoho Mail en admin, ya en el sitio real — es lo único operativo puro que sigue pendiente.  
4. **El plan de 5 fases de métricas/marketing gratuito (D-23 a D-27) está completo.** Verificar en producción: campaña de correo de prueba (leads con opt-in real, cron despachando en lotes, `daily_cap` respetado, enlace de baja funcionando, un lead `opted_out`/`unknown` nunca recibe nada — Fase 4); y, si Facebook ya estaba conectado, reconectarlo una vez para ver datos reales en la sección "Insights de Meta" de Reportes (Fase 5). **No hay Fase 6 planeada** — cualquier pedido nuevo de marketing/métricas es tarea nueva, no continuación de este plan.  
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
| 2026-08-04 | **D-23 Fase 1 v1.7.0**: dashboard `Vitacare_Crm_Reports` (mensajes por canal, volumen diario, estados, primera respuesta, carga por agente), `Vitacare_Crm_Graph::get()` + `Vitacare_Crm_Channel_Whatsapp::health()` (badge de calidad/límite de mensajería de WhatsApp), cupo de envíos salientes endurecido a bloqueo real en WhatsApp/Messenger/Instagram (antes solo WhatsApp lo tenía y ni bloqueaba) | `15eb0bb` |
| 2026-08-04 | **D-24 Fase 2 v1.8.0**: DB v3 (`wp_vitacare_crm_leads` + `lead_id` real en conversations), `Vitacare_Crm_Leads_Repo` (CRUD, opt-in/opt-out, dedupe por contacto, import CSV), auto-alta de lead desde `upsert_contact()`, admin `CRM VITACARE → Leads`, endpoints REST `/leads`, deep link `?c=ID` en la bandeja | `4c02fa7` |
| 2026-08-04 | **D-25 Fase 3 v1.9.0**: DB v4 (`wp_vitacare_crm_link_clicks`), `Vitacare_Crm_Links_Repo` (códigos únicos + UTM incrustado + contador de clics), redirector público `GET /go/{code}`, admin `CRM VITACARE → Enlaces`, sección "Clics por campaña" en Reportes, endpoints REST `/links` | `c1fcb2e` |
| 2026-08-04 | **D-26 Fase 4 v1.10.0**: DB v5 (`wp_vitacare_crm_email_campaigns` + `wp_vitacare_crm_campaign_recipients`), `Vitacare_Crm_Email_Campaigns_Repo` (segmento congelado por opt-in, despacho por cron en lotes con re-verificación de consentimiento, `daily_cap` por campaña), `send_campaign_email()` en Zoho/Gmail, baja pública `GET/POST /unsubscribe/{token}` con token HMAC sin estado, admin `CRM VITACARE → Campañas de correo`, resumen en Reportes | `4a18ff0` |
| 2026-08-04 | **D-27 Fase 5 (última) v1.11.0**: scopes `read_insights`/`instagram_manage_insights`, `get_page_insights()`/`get_instagram_insights()` en `Vitacare_Crm_Facebook_Oauth` (impresiones/interacciones de Página, alcance/visitas de perfil de Instagram, cacheados 30 min), sección "Insights de Meta" en Reportes — **cierra el plan de 5 fases de métricas/marketing gratuito** | este commit |
| 2026-08-04 | **Fix v1.11.1** (fuera del plan de 5 fases, pedido directo del usuario): panel de contexto de la bandeja (ficha "Contacto en VITACARE") más ancho — `.vcrm-container` de 1280px a 1520px, tercera columna del grid de `minmax(200px,240px)` a `minmax(280px,360px)`, `word-break: break-all` cambiado a `overflow-wrap: break-word` en `.vcrm-dl dd` para no cortar texto de forma agresiva. Solo CSS, sin cambios de esquema | `e978b7f` |
| 2026-08-05 | **D-28 Fase 1 v1.12.0** (diagnóstico, sin código): reestructuración del módulo de integraciones Meta — confirmado que Messenger/Instagram ya usan `vitacare_crm_fb_page_token` propio (no reutilizan el `access_token` de WhatsApp), y que el 403 del webhook es fail-closed intencional por falta de canales activos, no un bug | — |
| 2026-08-05 | **D-28 Fase 2 v1.12.0**: `Vitacare_Crm_Backup` — respaldo/restauración manual de credenciales de integraciones Meta (App ID/Secret, tokens, WABA/Phone Number ID, Facebook/Instagram) a JSON fuera del webroot público (`VITACARE_PRIVATE_STORAGE_DIR`), sin desencriptar ni mostrar secretos; botón en Credenciales, restauración por archivo con confirmación server-side | `62845f4` |
| 2026-08-05 | **D-28 Fase 3 v1.13.0**: `Vitacare_Crm_Integrations_Page` (submenú Integraciones, pestañas por canal) + `Vitacare_Crm_Whatsapp_Embedded_Signup` (asistente oficial FB.login + `featureType: whatsapp_business_app_onboarding`, sin QR local, code exchange servidor-a-servidor). No conecta ningún activo real — queda listo para que el usuario complete el diálogo de Meta cuando lo decida. **Cierra el plan de reestructuración de integraciones (D-28, Fases 1-3) — no hay Fase 4 planeada** | `a30b890` |
| 2026-08-05 | **D-28 Fase 4 v1.14.0** (corrección puntual pedida tras revisar Fase 3 en vivo): Credenciales reorganizada en 4 bloques con ancla propia (Meta/WhatsApp/Messenger/Instagram); nuevas opciones `wa_business_id`/`wa_embedded_config_id`/`wa_phone`; constante preferida `VITACARE_CRM_WA_SYSTEM_USER_TOKEN` (con fallback no destructivo a la antigua `VITACARE_CRM_META_ACCESS_TOKEN`); constantes opcionales para Messenger/Instagram en `Vitacare_Crm_Facebook_Oauth` sin tocar su OAuth; botón "Borrar" por secreto con confirmación; flags Messenger/Instagram/Correo ya no dicen "(futuro)". **Reabre y cierra D-28 en Fase 4 — no hay Fase 5 planeada** | `ac05a98` |
| 2026-08-05 | **Fix v1.14.1**: `Vitacare_Crm_Webhook::handle_get()` respondía el `hub.challenge` envuelto en JSON (`"12345"`) porque `WP_REST_Response` siempre pasa por el serializador JSON del REST server sin importar el header `Content-Type`. Ahora, solo en la rama de éxito, escribe la respuesta directo con `status_header()`/`header()`/`echo`/`exit`, devolviendo el challenge como texto plano exacto (`12345`, sin comillas). La rama de error (403, token inválido) y toda la lógica POST/firma `X-Hub-Signature-256` no se tocaron — confirmado en producción que `invalid_verify_token` sigue devolviendo 403 JSON igual que antes | `2d090b9` |
| 2026-08-05 | **D-29 v1.15.0**: botón "Ver" para revelar App Secret y Verify Token en Credenciales — excepción explícita y confirmada por el usuario a la regla de "nunca mostrar secretos completos". `ajax_reveal_secret()` gateado por nonce + `manage_options` + lista blanca, con auditoría en el logger (usuario + campo, nunca el valor). No aplica a WhatsApp System User Token ni TikTok Client Secret | `8d5cc92` |
| 2026-08-05 | **Fix v1.15.1**: OAuth de Facebook/Messenger redirigía a `/wp-admin/` sin `code`/`state`/`error` al pulsar "Conectar con Facebook". Causa raíz: `add_query_arg()` de WordPress no codifica los valores del querystring, y `redirect_uri` (que trae `?`/`=`) corrompía el querystring externo del diálogo OAuth y del intercambio de token. Nuevo helper `build_url()` (http_build_query + RFC3986) usado en el diálogo de autorización y en ambos intercambios de token; state ahora ligado a un transient por usuario admin (antes había una clave global duplicada sin usar); el callback redirige siempre exactamente a `admin.php?page=vitacare-crm-facebook` con `&vitacare_oauth=success|error`, nunca a `admin_url()` genérico; diagnóstico no sensible en el logger en cada paso; nueva prueba administrativa en la página Facebook (URI generada, callback registrado, state pendiente, estado de la última autorización) | `0923890` |
| 2026-08-05 | **Fix v1.15.2**: la app usa Facebook Login for Business con una Configuration ya creada en Meta ("VITACARE CRM Coexistencia") pero `start_oauth_url()` nunca enviaba `config_id` — Meta dejaba avanzar la autorización pero terminaba devolviendo a `/wp-admin/` sin `code`/`state`/`error`, porque el diálogo no correspondía al flujo que esa Configuration espera. Nuevo `login_config_id()` (constante `VITACARE_CRM_FB_LOGIN_CONFIG_ID` u opción propia, separado del App ID) agregado al querystring del diálogo (ya vía `build_url()`/RFC3986, no `add_query_arg()`) solo cuando hay valor configurado. `SCOPES` acotado a los 4 permisos que Messenger necesita hoy (`pages_show_list, pages_manage_metadata, pages_messaging, business_management`) — los de Instagram/Insights de D-27 Fase 5 se retiran por ahora, documentados para reincorporar cuando se conecte Instagram de nuevo. Nuevo campo en la página Facebook para guardar el Configuration ID (no es secreto). Diagnóstico ampliado con Configuration ID usado, si fue incluido, host del diálogo y scopes solicitados | `4beb402` |
| 2026-08-05 | **D-30 / Fix v1.15.3 — causa raíz real del fallo OAuth Facebook**: auditoría forense (inspección de código, sin conjeturas) demostró que ni el encoding RFC3986 (v1.15.1) ni el `config_id` (v1.15.2) eran la causa raíz. `render_page()` inicia el flujo con `wp_safe_redirect( $url )` hacia `https://www.facebook.com/.../dialog/oauth` — y `wp_safe_redirect()` de WordPress **rechaza por defecto cualquier host externo no declarado en el filtro `allowed_redirect_hosts`**, sustituyendo el destino silenciosamente por `admin_url()`. `www.facebook.com` nunca estuvo en ese filtro, así que el navegador nunca llegaba a Meta: caía directo a `/wp-admin/` en el mismo clic. Mismo defecto ya documentado antes en `vitacare-core` para Google Calendar (`ESTADO_PROYECTO.md`), pero `vitacare-crm` es un plugin separado y nunca lo heredó. Corrección (a pedido explícito del usuario, alcance mínimo): `allow_facebook_redirect_host()` agrega **únicamente** `www.facebook.com` (constante `DIALOG_HOST`) al filtro — deliberadamente **no** se agrega `graph.facebook.com`, porque los intercambios con Graph API ya se hacen server-side vía `wp_remote_get()`/`wp_remote_post()`, nunca por redirección del navegador. Refactor de `start_oauth_url()` en `build_dialog_url_for_state()` + nueva `validate_oauth_dialog_url()` (valida en estricto que la URL construida sea HTTPS, host exacto `www.facebook.com` y ruta `/dialog/oauth` antes de devolverla — no confía ciegamente en los literales). Nuevo botón admin "Mostrar URL OAuth generada" (`preview_oauth_url()`, gateado por `manage_options`, sin secretos: solo `client_id`/`redirect_uri`/`scope`/`config_id`/`response_type`, con el `state` parcialmente enmascarado en la vista aunque completo en el campo copiable). **Hallazgo colateral reportado, no corregido** (fuera de alcance de esta tarea, mismo patrón latente sin `allowed_redirect_hosts`): Gmail, Zoho Mail y TikTok usan también `wp_safe_redirect()` hacia su host OAuth externo respectivo — pendiente de confirmar/corregir en una tarea aparte si se decide priorizarlo. Único archivo modificado: `class-vitacare-crm-facebook-oauth.php` (+ bump de versión) | este commit |

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
