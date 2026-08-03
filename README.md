# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja multi-canal (WhatsApp, Facebook Messenger, Instagram Direct, Gmail) y gestión de conversaciones.

| | |
|---|---|
| **Respaldo / código** | https://github.com/yusieluh/crmvitacare |
| **URL producción** | https://vitacareec.org/crm |
| **Versión** | **1.3.0** |
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

## Qué incluye v1.3.0

- Bandeja en `/crm` (lista, hilo, compositor)
- WhatsApp: recibir + enviar (Cloud API), incluida media
- Facebook: OAuth, elegir Página, Messenger in/out, incluida media
- Instagram Direct: cuenta profesional vinculada a la Página, in/out de texto (misma Página, mismo token); media entrante sí, saliente todavía no
- Gmail: OAuth, sync INBOX, responder desde bandeja
- Media (imagen/audio/video/documento): se recibe y se puede **adjuntar al responder** por WhatsApp/Messenger (botón 📎 en la bandeja)
- Admin: Cuentas, WhatsApp Coexistence, Facebook (+ estado Instagram), Gmail, Credenciales

## Instalación

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
| WhatsApp (oficial) | Checklist Coexistence |
| Facebook | Conectar + elegir Página |
| Gmail | OAuth + sincronizar |
| Credenciales | App Meta / flags |

## Redirect URIs (prod)

- Facebook: `…/wp-admin/admin.php?page=vitacare-crm-facebook`  
- Gmail: `…/wp-admin/admin.php?page=vitacare-crm-gmail`  
- Webhook Meta: `…/wp-json/vitacare-crm/v1/webhooks/meta`  

## Documentación

| Archivo | Rol |
|---|---|
| `ESTADO_CRM.md` | **Fuente de verdad** — estado, plan, decisiones |
| `docs/CONTINUAR.md` | Handoff para Claude Code / otra sesión |
| `docs/PROCESS.md` | Checklist al cerrar tareas |
| `docs/DESIGN.md` | Diseño histórico (secundario a ESTADO) |

## Siguiente trabajo

Ver sección **Pendiente** en [`ESTADO_CRM.md`](./ESTADO_CRM.md) (C-6 TikTok, media saliente por Instagram, despliegue prod).
