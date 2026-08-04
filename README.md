# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja multi-canal (WhatsApp, Facebook Messenger, Instagram Direct, Gmail) y gestión de conversaciones.

| | |
|---|---|
| **Respaldo / código** | https://github.com/yusieluh/crmvitacare |
| **URL producción** | https://vitacareec.org/crm |
| **Versión** | **1.11.0** |
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

## Qué incluye v1.11.0

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
- Admin: Cuentas, Reportes, Leads, Enlaces, Campañas de correo, WhatsApp Coexistence, Facebook (+ estado Instagram), TikTok, Gmail, Zoho Mail, Credenciales

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

Ver sección **Pendiente** en [`ESTADO_CRM.md`](./ESTADO_CRM.md) (conectar Facebook/Instagram/Gmail/Zoho en el sitio real, media saliente por Instagram). El plan de 5 fases de métricas/marketing gratuito (D-23 a D-27) está completo.
