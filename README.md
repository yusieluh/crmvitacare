# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja multi-canal (WhatsApp, Facebook Messenger, Instagram Direct, Gmail) y gestión de conversaciones.

| | |
|---|---|
| **Respaldo / código** | https://github.com/yusieluh/crmvitacare |
| **URL producción** | https://vitacareec.org/crm |
| **Versión** | **1.6.0** |
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

## Qué incluye v1.6.0

- Bandeja en `/crm` (lista, hilo, compositor)
- WhatsApp: recibir + enviar (Cloud API), incluida media
- Facebook: OAuth, elegir Página, Messenger in/out, incluida media
- Instagram Direct: cuenta profesional vinculada a la Página, in/out de texto (misma Página, mismo token); media entrante sí, saliente todavía no
- Gmail: OAuth, sync INBOX, responder desde bandeja
- Media (imagen/audio/video/documento): se recibe y se puede **adjuntar al responder** por WhatsApp/Messenger (botón 📎 en la bandeja)
- **Ficha de VITACARE en la bandeja**: al abrir una conversación, si el contacto coincide (por teléfono o correo) con un usuario real de vitacareec.org, se muestra su nombre, rol, membresía, citas recientes y pendiente de pago — todo de **solo lectura**, sin tocar el sistema principal
- **Despliegue automático a Hostinger** vía GitHub Actions en cada push a `main` (requiere configurar los secrets del repo, ver `docs/CONTINUAR.md`)
- **TikTok (Login Kit)**: conecta y verifica una cuenta de TikTok. No es un canal de mensajes — TikTok no tiene API pública de DMs para apps de terceros, así que no envía/recibe nada en la bandeja (ver `ESTADO_CRM.md`, decisión D-21)
- **Zoho Mail**: segundo proveedor de correo, mismo canal "Correo" que Gmail — cada conversación recuerda qué buzón la maneja para responder por el correcto (ver `ESTADO_CRM.md`, decisión D-22)
- Admin: Cuentas, WhatsApp Coexistence, Facebook (+ estado Instagram), TikTok, Gmail, Zoho Mail, Credenciales

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

Ver sección **Pendiente** en [`ESTADO_CRM.md`](./ESTADO_CRM.md) (conectar Facebook/Instagram/Gmail/Zoho en el sitio real, media saliente por Instagram).
