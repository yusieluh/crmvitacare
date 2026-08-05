# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja multi-canal (WhatsApp, Facebook Messenger, Instagram Direct, Gmail) y gestión de conversaciones.

| | |
|---|---|
| **Respaldo / código** | https://github.com/yusieluh/crmvitacare |
| **URL producción** | https://vitacareec.org/crm |
| **Versión** | **1.15.3** |
| **Fuente de información** | [`ESTADO_CRM.md`](./ESTADO_CRM.md) |
| **Continuar después / Claude Code** | [`docs/CONTINUAR.md`](./docs/CONTINUAR.md) |
| **Proceso docs + push** | [`docs/PROCESS.md`](./docs/PROCESS.md) |

> **Leer primero:** [`ESTADO_CRM.md`](./ESTADO_CRM.md).  
> **Retomar en otra IA o más tarde:** [`docs/CONTINUAR.md`](./docs/CONTINUAR.md).

## Límites fijos

- CRM **solo** en `/crm` — no se toca la raíz del sitio  
- **No** se modifica `vitacare-core`, tema ni WooCommerce  
- Integraciones **oficiales** (Meta, Google); WhatsApp = Cloud API + Coexistence  
- Todo cambio se documenta en el repo y se **sube a GitHub**

## Qué incluye v1.15.3

- Bandeja en `/crm` (lista, hilo, compositor)
- WhatsApp: recibir + enviar (Cloud API), incluida media
- Facebook: OAuth, elegir Página, Messenger in/out, incluida media
- Instagram Direct: cuenta profesional vinculada a la Página, in/out de texto (misma Página, mismo token); media entrante sí, saliente todavía no
- Gmail: OAuth, sync INBOX, responder desde bandeja
- Media (imagen/audio/video/documento): se recibe y se puede **adjuntar al responder** por WhatsApp/Messenger (botón 📎 en la bandeja)
- **Ficha de VITACARE en la bandeja**: al abrir una conversación, si el contacto coincide (por teléfono o correo) con un usuario real de vitacareec.org, se muestra su nombre, rol, membresía, citas recientes y pendiente de pago — todo de **solo lectura**, sin tocar el sistema principal
- **Despliegue automático a Hostinger** vía GitHub Actions en cada push a `main` (requiere configurar los secrets del repo, ver `docs/CONTINUAR.md`)
- **TikTok (Login Kit)**: conecta y verifica una cuenta de TikTok. No es un canal de mensajes — TikTok no tiene API pública de DMs para apps de terceros, así que no envía/recibe nada en la bandeja (ver `ESTADO_CRM.md`, decisión D-21)
- **Zoho Mail**: correo institucional y **canal principal de correo** (mismo canal "Correo" que Gmail, que queda secundario/opcional) — cada conversación recuerda qué buzón la maneja para responder por el correcto (ver `ESTADO_CRM.md`, decisión D-22)
- **Reportes** (Fase 1 de métricas/marketing gratuito, D-23): mensajes por canal, volumen diario, conversaciones por estado, tiempo de primera respuesta, carga por agente — todo sobre datos ya guardados en el CRM. Badge de salud de WhatsApp (calidad + límite de mensajería, vía Graph API). El cupo mensual de mensajes salientes ahora **bloquea de verdad** el envío al superarse (antes solo avisaba), aplicado a WhatsApp, Messenger e Instagram
- **Leads** (Fase 2, D-24): pipeline de contactos de marketing separado de la bandeja de soporte. Cada contacto nuevo (o existente) recibe automáticamente un lead con consentimiento "desconocido" — escribir al CRM no es opt-in, eso se marca a mano. Alta manual, filtros, import CSV, opt-in/opt-out con rastro de origen, y "Convertir a conversación" para abrir el hilo desde `/crm`
- **Enlaces** (Fase 3, D-25): enlaces cortos propios con UTM incrustado y contador de clics — sin depender de Bitly ni ningún acortador de terceros. Redirector público, sección "Clics por campaña" en Reportes
- **Campañas de correo** (Fase 4, D-26): envío masivo (Zoho principal/Gmail secundario) solo a leads con opt-in explícito — el consentimiento se re-verifica en cada envío, no solo al crear la campaña. Despacho por lotes vía cron respetando un cupo diario, pie de baja obligatorio con enlace público sin login
- **Insights de Meta** (Fase 5, D-27, última fase): impresiones/interacciones de la Página y alcance/visitas de perfil de Instagram, gratis y sin gasto en anuncios, en el dashboard de Reportes. Cuentas ya conectadas antes de este cambio deben reconectar Facebook una vez para autorizar los permisos nuevos
- **Fix v1.11.1**: panel de contexto de la bandeja (ficha de contacto) más ancho, texto largo ya no se corta de forma agresiva
- **D-28 Fase 2 (v1.12.0)**: respaldo/restauración manual de credenciales de integraciones Meta (`CRM VITACARE → Credenciales → Respaldo de integraciones Meta`)
- **D-28 Fase 3 (v1.13.0, cierra el plan de reestructuración de integraciones)**: sección **`CRM VITACARE → Integraciones`** con pestañas por canal (Meta general/WhatsApp/Messenger/Instagram/Gmail/Zoho Mail/Diagnóstico) — muestra estado sin duplicar las páginas de conexión ya existentes. Dentro de WhatsApp: asistente oficial **Embedded Signup** (WhatsApp Business App Coexistence, `featureType: whatsapp_business_app_onboarding`) para conectar el número real sin QR local ni librerías no oficiales — el número sigue funcionando en la app del teléfono mientras el CRM recibe y envía por Cloud API. No conecta nada por sí solo: requiere completar el diálogo oficial de Meta desde `CRM VITACARE → Integraciones → WhatsApp` cuando el administrador esté listo
- **D-28 Fase 4 (v1.14.0)**: Credenciales reorganizada en 4 bloques con ancla propia (`#meta`/`#whatsapp`/`#messenger`/`#instagram`) — App ID/Secret ya no están bajo "WhatsApp / Meta", el token de WhatsApp pasó a llamarse "WhatsApp System User Access Token" (constante preferida `VITACARE_CRM_WA_SYSTEM_USER_TOKEN`), y Messenger/Instagram ahora tienen su propio bloque de estado (solo lectura, gestionado por OAuth). Botón "Borrar" independiente por secreto, con confirmación
- **Fix v1.14.1**: el `hub.challenge` de la verificación GET del webhook Meta se devolvía envuelto en JSON (`"12345"`); ahora se responde como texto plano exacto (`12345`), como exige Meta
- **D-29 (v1.15.0)**: botón "Ver" en Credenciales para revelar **App Secret** y **Verify Token** en pantalla (a pedido explícito del usuario) — no aplica a WhatsApp System User Token ni TikTok Client Secret
- **Fix v1.15.1**: "Conectar con Facebook" caía en `/wp-admin/` sin completar el OAuth (`add_query_arg()` no codificaba `redirect_uri`, corrompiendo la URL del diálogo). Corregido con `http_build_query` + RFC3986; el callback ahora siempre vuelve a `admin.php?page=vitacare-crm-facebook&vitacare_oauth=success|error`, nunca a una URL genérica. Nueva prueba de diagnóstico en la página Facebook
- **Fix v1.15.2**: la app usa **Facebook Login for Business** con una Configuration ya creada en Meta, pero el diálogo OAuth nunca enviaba `config_id` — Meta dejaba avanzar la autorización pero volvía a `/wp-admin/` sin `code`/`state`. Agregado `config_id` (campo propio en la página Facebook, separado del App ID) y scopes de Messenger acotados a los 4 que se necesitan hoy
- **Fix v1.15.3 — causa raíz real**: auditoría forense (sin conjeturas) demostró que "Conectar con Facebook" caía en `/wp-admin/` porque `wp_safe_redirect()` de WordPress rechaza por defecto cualquier host externo no declarado en `allowed_redirect_hosts`, y `www.facebook.com` nunca estuvo ahí — así que el navegador nunca llegaba a Meta, ni con la codificación RFC3986 (v1.15.1) ni con el `config_id` (v1.15.2) corregidos. Agregado el filtro con únicamente `www.facebook.com` (no `graph.facebook.com`: los intercambios con Graph API son server-side, no por redirección del navegador). Nueva validación estricta de HTTPS/host/ruta antes de devolver la URL del diálogo, y botón admin "Mostrar URL OAuth generada" (sin secretos, `state` parcialmente oculto en la vista)
- Admin: Cuentas, Reportes, Leads, Enlaces, Campañas de correo, Integraciones, WhatsApp Coexistence, Facebook (+ estado Instagram), TikTok, Gmail, Zoho Mail, Credenciales

## Instalación

**Automática:** con los 4 secrets de Hostinger cargados en Settings → Secrets de este repo, cada push a `main` despliega solo por sí mismo (`.github/workflows/deploy-hostinger.yml`, SSH+rsync a `wp-content/plugins/vitacare-crm/`).

**Manual (respaldo):**

```powershell
git clone https://github.com/yusieluh/crmvitacare.git
cd crmvitacare
powershell -ExecutionPolicy Bypass -File .\bin\package-plugin.ps1
```

Subir `dist/vitacare-crm.zip` en WordPress → Plugins → Activar.

O copiar el contenido del repo a `wp-content/plugins/vitacare-crm/`.

## Admin

| Menú | Uso |
|---|---|
| CRM VITACARE → Cuentas conectadas | Estado de canales |
| Integraciones | Pestañas por canal + asistente WhatsApp Embedded Signup |
| Reportes | Métricas locales + salud de WhatsApp |
| Leads | Pipeline de contactos de marketing |
| Enlaces | Enlaces cortos propios con UTM/clics |
| Campañas de correo | Envío masivo solo a leads con opt-in |
| WhatsApp (oficial) | Checklist Coexistence |
| Facebook | Conectar + elegir Página |
| TikTok | Conectar (solo verifica cuenta, sin mensajería) |
| Gmail | OAuth + sincronizar |
| Zoho Mail | OAuth + sincronizar (segundo buzón, mismo canal Correo) |
| Credenciales | App Meta / flags |

## Redirect URIs (prod)

- Facebook: `…/wp-admin/admin.php?page=vitacare-crm-facebook`  
- TikTok: `…/wp-admin/admin.php?page=vitacare-crm-tiktok`  
- Gmail: `…/wp-admin/admin.php?page=vitacare-crm-gmail`  
- Zoho Mail: `…/wp-admin/admin.php?page=vitacare-crm-zoho`  
- Webhook Meta: `…/wp-json/vitacare-crm/v1/webhooks/meta`  

## Documentación

| Archivo | Rol |
|---|---|
| `ESTADO_CRM.md` | **Fuente de verdad** — estado, plan, decisiones |
| `docs/CONTINUAR.md` | Handoff para Claude Code / otra sesión |
| `docs/PROCESS.md` | Checklist al cerrar tareas |
| `docs/DESIGN.md` | Diseño histórico (secundario a ESTADO) |

## Siguiente trabajo

Ver sección **Pendiente** en [`ESTADO_CRM.md`](./ESTADO_CRM.md) (conectar Facebook/Instagram/Gmail/Zoho/WhatsApp en el sitio real, media saliente por Instagram). El plan de 5 fases de métricas/marketing gratuito (D-23 a D-27) y el de reestructuración de integraciones Meta (D-28, Fases 1-4) están completos — no hay fases nuevas planeadas de ninguno de los dos.
