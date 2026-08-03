# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.6.0** (PR-5 bandeja UI) |
| **DB schema** | **v2** |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm** — no tocar la raíz del sitio
3. **No modificar** el sistema instalado (`vitacare-core`, tema, WooCommerce, etc.)

---

## Requisitos de producto — conexión de cuentas (2026-08-03)

**Solicitado por VITACARE / Yusiel.** Objetivo UX: el staff **no pega tokens a mano**; el CRM **pide acceso a la cuenta** del proveedor (OAuth / “Continuar con…”) y guarda credenciales de forma segura.

### 1. Facebook

| Requisito | Enfoque oficial |
|---|---|
| Integración **directa** solicitando acceso a la cuenta | **Facebook Login for Business** / Meta OAuth (scopes de páginas y mensajería) |
| Al conectar, **elegir la Página** que administra el perfil | Pantalla post-OAuth: listar Pages del usuario (`/me/accounts`) y **seleccionar una** (Page ID + page access token de larga duración) |
| Mensajería | Facebook Messenger vía Graph API + webhooks (mismo patrón que ya tenemos en `/webhooks/meta`) |

**UI prevista (admin / CRM Ajustes → Conectar Facebook):**  
`[ Conectar con Facebook ]` → login Meta → **selector de Página** → confirmar → canal activo.

### 2. Instagram

| Requisito | Enfoque oficial |
|---|---|
| Acceso a la cuenta | OAuth Meta; cuenta IG **Professional** vinculada a una **Facebook Page** |
| Mensajería | Instagram Messaging API (Graph) + webhooks Meta |

Flujo típico: conectar Facebook (o IG) → elegir Page → elegir/vincular cuenta Instagram asociada a esa Page.

### 3. Google

| Requisito | Enfoque oficial |
|---|---|
| Acceso a la cuenta | **Google OAuth 2.0** (“Iniciar sesión con Google”) |

**Pendiente de confirmar (OQ-G1):** ¿qué producto de Google se integra?

- **Google Business Profile** (reseñas / mensajes de ficha), y/o  
- **Gmail** (correo entrante/saliente), y/o  
- **Google Ads / Analytics** (no mensajería CRM)

Hasta definir OQ-G1 no se implementa el conector Google.

### 4. TikTok

| Requisito | Enfoque oficial |
|---|---|
| Acceso a la cuenta | **TikTok Login Kit / TikTok for Business OAuth** |

**Limitación conocida:** TikTok **no** ofrece un webhook de mensajería DM equivalente a Meta. La integración “directa por cuenta” puede servir para:

- autenticar cuenta Business / mostrar perfil, y/o  
- APIs de contenido/publicidad según permisos aprobados por TikTok,

pero **no** garantiza bandeja de DMs 1:1 como WhatsApp/Messenger. Sigue en investigación (fase 7) con UX de “Conectar TikTok” cuando haya API usable.

### 5. WhatsApp — Cloud API + celular (y el tema del código QR)

| Requisito del negocio | Qué se puede hacer **oficialmente** |
|---|---|
| Seguir usando **WhatsApp Business en el celular** y el CRM a la vez | **WhatsApp Cloud API + Coexistence** (Meta, 2025+): el número se gestiona con la app del teléfono **y** la API en paralelo, sincronizados. **Ya es la base de PR-1…PR-5.** |
| “Escanear **código QR** y agregar como **dispositivo vinculado**” al estilo WhatsApp Web | **No es el camino oficial** para un CRM en servidor. Las librerías tipo Baileys / whatsapp-web.js simulan un dispositivo vinculado: **violan Términos de Servicio de WhatsApp**, riesgo real de **bloqueo del número**, inaceptable para pacientes/salud. |

#### Decisión de arquitectura (actualizada, no reabrir a la ligera)

| ID | Decisión |
|---|---|
| **D-04** | WhatsApp en producción = **solo Cloud API + Coexistence** (Meta). Credenciales vía App Meta / Business Manager. |
| **D-04b** | **Prohibido** en este proyecto: Baileys, whatsapp-web.js, Evolution API no oficial, o cualquier “vincular por QR como dispositivo” no autorizado por Meta. |
| **D-12** | Canales Meta/Google/TikTok: conexión preferente por **OAuth “Conectar cuenta”** en el admin del CRM (no solo pegar tokens). Facebook incluye **selector de Página**. |
| **D-13** | Tokens OAuth y page tokens: preferir `wp-config` / almacenamiento cifrado; **nunca** en Git. |

#### Cómo se “parece” al QR sin romper las reglas

La experiencia que Meta **sí** soporta es:

1. Crear/usar App en **Meta for Developers** + **WhatsApp Business Platform**.  
2. Activar **Coexistence** para el número que ya usa la app WhatsApp Business del celular.  
3. El teléfono **sigue siendo el principal**; el CRM envía/recibe por Cloud API.  
4. La “vinculación” se hace en el flujo **oficial de Meta / Business Manager**, no con un QR de WhatsApp Web generado por nuestro servidor.

Si en el futuro Meta ofrece un flujo embebido con QR **oficial** documentado para partners, se evaluará e implementará **solo** ese flujo. Mientras tanto, un QR de “dispositivo vinculado” casero **no se implementará**.

**Resumen para el equipo:**  
- Facebook / Instagram / Google / TikTok → **sí**, botones “Conectar cuenta” (OAuth).  
- Facebook → **sí**, elegir Página.  
- WhatsApp → **sí** celular + CRM (Coexistence).  
- WhatsApp → **no** QR no oficial tipo Web.

---

## Plan de fases

### MVP técnico actual (código en `main`)

| PR | Contenido | Estado |
|---|---|---|
| 0 | Hardening | ✅ |
| 1 | Settings Meta (tokens manuales) | ✅ v0.2.0 |
| 2 | REST + DB v2 | ✅ |
| 3 | WhatsApp inbound | ✅ |
| 4 | WhatsApp outbound | ✅ |
| 5 | Inbox UI | ✅ v0.6.0 |
| 6 | Media WhatsApp | ⏳ |

### Conectores OAuth (nuevo tramo de plan — post o en paralelo a PR-6)

| ID | Contenido | Estado |
|---|---|---|
| **C-1** | UI “Cuentas conectadas” en admin CRM (lista canales + estado) | ⏳ |
| **C-2** | **Facebook OAuth** + **selector de Página** + guardar page token | ⏳ |
| **C-3** | **Instagram** (cuenta vinculada a Page) + webhooks mensajería | ⏳ |
| **C-4** | Ampliar webhook Meta para `object=page` (Messenger/IG) sobre bandeja actual | ⏳ |
| **C-5** | **Google OAuth** (tras OQ-G1: producto exacto) | ⏳ bloqueado por OQ-G1 |
| **C-6** | **TikTok OAuth** (scopes disponibles; sin DM si no hay API) | ⏳ |
| **C-7** | WhatsApp: guía/asistente **Coexistence** en UI (checklist Meta; **sin** QR no oficial) | ⏳ |
| **C-8** | Migrar settings manuales a “conectado vía OAuth” con fallback tokens | ⏳ |

Orden recomendado: **C-1 → C-2 → C-4 → C-3 → C-7 → PR-6 media → C-5/C-6**.

---

## Decisiones previas (siguen vigentes)

| ID | Decisión |
|---|---|
| D-00 | `ESTADO_CRM.md` = fuente de información |
| D-01 | Plugin propio en Hostinger shared |
| D-02 | No modificar sistema instalado; solo lectura de datos WP/WC |
| D-03 | Solo https://vitacareec.org/crm |
| D-04 / D-04b | WhatsApp oficial Coexistence; **no** QR no oficial |
| D-08 | GitHub respaldo al cerrar tarea |
| D-12 / D-13 | OAuth + selector Page; secretos fuera de Git |

---

## Preguntas abiertas (necesitan respuesta de Yusiel / VITACARE)

| # | Pregunta | Bloquea |
|---|---|---|
| **OQ-G1** | Google: ¿Business Profile, Gmail, u otro? | C-5 |
| **OQ-T1** | TikTok: ¿solo branding/ads o se espera DM en bandeja (puede no existir)? | Alcance C-6 |
| **OQ-M1** | ¿Ya existe App en Meta for Developers + Business Manager de VITACARE? | C-2/C-3 y prueba real WA |
| **OQ-W1** | Confirmar aceptación de **Coexistence** (oficial) en lugar de QR no oficial | C-7 / compliance |

---

## PR-5 entregado (v0.6.0) — resumen

Bandeja 3 paneles en `/crm`: lista, hilo, compositor WhatsApp, polling 20s. Ver commits `8481c64`.

---

## Siguiente paso de implementación

1. Confirmar **OQ-W1** (Coexistence vs QR) y **OQ-G1** (Google).  
2. Empezar **C-1 + C-2**: pantalla Conectar Facebook + selector de Página.  
3. Opcional en paralelo: **PR-6** media WhatsApp.  
4. Seguir documentando cada entrega en este archivo + push a GitHub.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-5 (MVP mensajería + bandeja) | …`8481c64` |
| 2026-08-03 | **Requisitos OAuth** FB/IG/Google/TikTok; **selector Página FB**; WhatsApp Coexistence vs **prohibición QR no oficial**; plan C-1…C-8 | este update |
