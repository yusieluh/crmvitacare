# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Se lee primero en toda sesión. Se actualiza con **cada cambio**, **cada plan** y **cada tarea cerrada**.  
> Tras actualizarlo: **commit + push a GitHub** (https://github.com/yusieluh/crmvitacare).  
> Si un dato no está aquí (o no se refleja el cambio aquí), **no está documentado**.

| Campo | Valor |
|---|---|
| **Repositorio** | https://github.com/yusieluh/crmvitacare |
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM (única superficie del plugin)** | **https://vitacareec.org/crm** |
| **Diseño de ingeniería (detalle)** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Proceso de trabajo** | [`docs/PROCESS.md`](./docs/PROCESS.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables (producto e instalación)

### 1. `ESTADO_CRM.md` = fuente de información

- Aquí vive el **estado**, el **plan de fases**, las **decisiones**, el **changelog** y el **siguiente paso**.
- Cada cambio de código, de alcance o de plan **actualiza este archivo** (y se sube a GitHub).
- `docs/DESIGN.md` es el detalle técnico de diseño; **no sustituye** a este archivo. Si el diseño cambia, se anota aquí y se alinea el DESIGN.
- El chat, notas locales o archivos temporales **no** son la fuente de verdad.

### 2. El CRM corre solo en `/crm`

- Superficie pública del CRM: **https://vitacareec.org/crm**
- El plugin **no redefine ni altera la raíz** del sitio (`https://vitacareec.org/`).
- La home, el catálogo, el checkout, las páginas existentes y el front general **siguen intactos**.
- No se cambia el front-controller del sitio ni se “monta” el CRM en la raíz.

### 3. El sistema ya instalado no se modifica

- **No se modifica, no se parchea y no se reescribe:**
  - `vitacare-core`
  - `vitacare-theme`
  - WooCommerce (configuración/código de negocio existente)
  - Otros plugins o plantillas del sitio en producción
- Integración = **solo lectura** de información del ecosistema (usuarios WP, roles, pedidos, etc.) para vincular conversaciones/leads.
- Datos del CRM → tablas/options **propias** del plugin (`wp_vitacare_crm_*`).
- Instalación = copiar/activar el plugin en `wp-content/plugins/`; **cero cambios** al código del sistema actual.

```text
https://vitacareec.org/          ← raíz y sistema actual (NO TOCAR)
https://vitacareec.org/crm      ← solo aquí vive el CRM (plugin vitacare-crm)
```

---

## 0. Qué es el CRM

- Plugin WordPress **independiente** (`vitacare-crm`) para bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads.
- Se instala en el **mismo WordPress** de `vitacareec.org`, **junto a** el sistema ya creado, sin sustituirlo.
- Al activarse crea la página slug `crm` → **https://vitacareec.org/crm**.
- Reutiliza header/footer del tema activo vía `template_include` **sin editar archivos del tema**.
- Código y documentación viven en este repo GitHub como respaldo.

---

## 0.1 Qué se actualiza al completar cada tarea

**Obligatorio siempre:**

1. **Este archivo (`ESTADO_CRM.md`)**
   - Estado de fases / plan
   - Siguiente paso
   - Changelog (fecha, qué, ref commit/PR)
   - “Última actualización”
2. **Commit + push** a `main` (o merge de PR) en GitHub

**Además, si aplica:**

3. `docs/DESIGN.md` — si cambió arquitectura, API, modelo de datos, seguridad o PR plan  
4. `README.md` — si cambió instalación o uso humano  

Detalle: [`docs/PROCESS.md`](./docs/PROCESS.md).

---

## 1. Decisiones ya tomadas (no reabrir sin razón nueva)

| ID | Decisión |
|---|---|
| D-00 | **`ESTADO_CRM.md` es la fuente de información** del proyecto; se actualiza con cada cambio y cada plan. |
| D-01 | CRM = plugin WordPress propio en hosting actual (Hostinger shared). Sin VPS/Docker Chatwoot/erxes. |
| D-02 | **No modificar** el sistema instalado (`vitacare-core`, tema, Woo, resto). Solo **lectura** de datos. |
| D-03 | CRM **solo** en **https://vitacareec.org/crm**. **No tocar** la raíz https://vitacareec.org/ |
| D-04 | WhatsApp: solo **Cloud API + Coexistence** (Meta). Prohibido Baileys / whatsapp-web.js. |
| D-05 | Canales: WhatsApp → FB/IG → correo; TikTok investigación aparte. |
| D-06 | Tablas propias: `wp_vitacare_crm_conversations`, `wp_vitacare_crm_messages` (+ leads después). |
| D-07 | Capability: `vitacare_crm_access` (admin nativo por ahora). |
| D-08 | GitHub = respaldo; push al cerrar cada tarea. |
| D-09 | Diseño de implementación: `docs/DESIGN.md` (MVP = PR-0…PR-6). |

Otras notas:

- Descartados: trycompai/crm, erxes, Chatwoot.
- Costo Meta: desde 1 oct 2026 mensajes de servicio Cloud API — presupuestar al escalar.
- TikTok: sin webhook estándar de mensajería → fase 7, no bloquea.

---

## 2. Plan de fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Investigación y decisión de arquitectura | ✅ Cerrada |
| 1 | Esqueleto del plugin (tablas, cap, `/crm`, plantilla, métricas, REST ping) | ✅ En GitHub |
| 1H | Hardening Fase 1 (login gate, upgrader, CSS fallback, zip) — **PR-0** | ⏳ Pendiente |
| 1S | Settings / secrets Meta — **PR-1** | ⏳ Pendiente |
| 2 | WhatsApp Cloud API (Coexistence): webhook, inbound/outbound, bandeja real | ⏳ Pendiente (App Meta) |
| 3 | Facebook Messenger + Instagram Direct | ⏳ Pendiente |
| 4 | Canal correo | ⏳ Pendiente |
| 5 | Pipeline de leads | ⏳ Pendiente |
| 6 | Pulido: roles, notificaciones, UX | ⏳ Pendiente |
| 7 | TikTok — investigación | ⏳ Pendiente |

**MVP shippable:** PR-0 → PR-6. Detalle técnico en [`docs/DESIGN.md`](./docs/DESIGN.md) (sección PR Plan).  
Cualquier cambio al plan se refleja **aquí primero**.

---

## 3. Estructura del plugin (en repo)

```
crmvitacare/                 ← repo = carpeta del plugin en WP
├── ESTADO_CRM.md            ← FUENTE DE INFORMACIÓN (este archivo)
├── README.md
├── docs/
│   ├── DESIGN.md            ← diseño técnico (detalle)
│   └── PROCESS.md
├── vitacare-crm.php
├── includes/
├── template-parts/
├── assets/
└── uninstall.php
```

---

## 4. Siguiente paso

1. **PR-0 / Fase 1H** — hardening del esqueleto (acceso `/crm`, migraciones, CSS mínimo), **sin** tocar raíz ni sistema instalado.
2. App Meta (Coexistence) cuando se aborde Fase 2.
3. Instalar plugin en WordPress de `vitacareec.org` solo como plugin; verificar **https://vitacareec.org/crm**.

---

## 5. Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Fase 1 esqueleto en `main` | `509fa94` |
| 2026-08-03 | Fix URL producción → vitacareec.org | `10d1f7c` |
| 2026-08-03 | Política GitHub, diseño, proceso | `8d1a2ce` |
| 2026-08-03 | **Fuente de verdad = ESTADO_CRM.md**; CRM solo en `/crm`; no tocar raíz ni sistema instalado (D-00, D-02, D-03 reforzados) | este update |
