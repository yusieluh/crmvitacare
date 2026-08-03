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
| **Versión plugin** | **0.1.1** (Fase 1H / PR-0) |
| **Diseño de ingeniería (detalle)** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Proceso de trabajo** | [`docs/PROCESS.md`](./docs/PROCESS.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables (producto e instalación)

### 1. `ESTADO_CRM.md` = fuente de información

- Aquí vive el **estado**, el **plan de fases**, las **decisiones**, el **changelog** y el **siguiente paso**.
- Cada cambio de código, de alcance o de plan **actualiza este archivo** (y se sube a GitHub).
- `docs/DESIGN.md` es el detalle técnico de diseño; **no sustituye** a este archivo.
- El chat, notas locales o archivos temporales **no** son la fuente de verdad.

### 2. El CRM corre solo en `/crm`

- Superficie pública del CRM: **https://vitacareec.org/crm**
- El plugin **no redefine ni altera la raíz** del sitio (`https://vitacareec.org/`).
- La home, catálogo, checkout y front general **siguen intactos**.

### 3. El sistema ya instalado no se modifica

- **No se modifica:** `vitacare-core`, `vitacare-theme`, WooCommerce, otros plugins/plantillas de producción.
- Integración = **solo lectura** de datos del ecosistema.
- Datos del CRM → tablas/options **propias** (`wp_vitacare_crm_*`).
- Instalación = copiar/activar plugin en `wp-content/plugins/`.

```text
https://vitacareec.org/          ← raíz y sistema actual (NO TOCAR)
https://vitacareec.org/crm      ← solo aquí vive el CRM (plugin vitacare-crm)
```

---

## 0. Qué es el CRM

- Plugin WordPress **independiente** (`vitacare-crm`) para bandeja multi-canal y leads.
- Mismo WordPress de `vitacareec.org`, **junto a** el sistema ya creado, sin sustituirlo.
- Página slug `crm` → **https://vitacareec.org/crm**.
- Header/footer del tema vía `template_include` **sin editar el tema**.

---

## 0.1 Qué se actualiza al completar cada tarea

1. **Este archivo** (estado, plan, changelog, siguiente paso)  
2. **Commit + push** a GitHub  
3. `docs/DESIGN.md` / `README.md` solo si aplica  

Detalle: [`docs/PROCESS.md`](./docs/PROCESS.md).

---

## 1. Decisiones ya tomadas (no reabrir sin razón nueva)

| ID | Decisión |
|---|---|
| D-00 | **`ESTADO_CRM.md` es la fuente de información**; se actualiza con cada cambio y plan. |
| D-01 | CRM = plugin WordPress propio en hosting actual. Sin VPS/Docker Chatwoot/erxes. |
| D-02 | **No modificar** el sistema instalado. Solo **lectura** de datos. |
| D-03 | CRM **solo** en **https://vitacareec.org/crm**. **No tocar** la raíz. |
| D-04 | WhatsApp: solo **Cloud API + Coexistence**. Prohibido Baileys / whatsapp-web.js. |
| D-05 | Canales: WhatsApp → FB/IG → correo; TikTok investigación aparte. |
| D-06 | Tablas propias: conversations, messages (+ leads después). |
| D-07 | Capability: `vitacare_crm_access` (admin nativo por ahora). |
| D-08 | GitHub = respaldo; push al cerrar cada tarea. |
| D-09 | Diseño: `docs/DESIGN.md` (MVP = PR-0…PR-6). |
| D-10 | **Fase 1H:** login gate, noindex, upgrader, CSS fallback, zip tooling (v0.1.1). |

---

## 2. Plan de fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Investigación y arquitectura | ✅ Cerrada |
| 1 | Esqueleto del plugin | ✅ En GitHub |
| **1H** | **Hardening (PR-0):** login, caps en load, upgrader, noindex, CSS, `.gitignore`, zip, hard-delete docs | ✅ **Hecha (v0.1.1)** |
| 1S | Settings / secrets Meta — **PR-1** | ⏳ Pendiente |
| 2 | WhatsApp Cloud API (Coexistence) | ⏳ Pendiente (App Meta) |
| 3 | Facebook + Instagram | ⏳ Pendiente |
| 4 | Canal correo | ⏳ Pendiente |
| 5 | Pipeline de leads | ⏳ Pendiente |
| 6 | Pulido: roles, notificaciones, UX | ⏳ Pendiente |
| 7 | TikTok — investigación | ⏳ Pendiente |

**MVP shippable:** PR-0 ✅ → PR-1…PR-6. Detalle: [`docs/DESIGN.md`](./docs/DESIGN.md).

---

## 3. Estructura del plugin (tras 1H)

```
crmvitacare/
├── ESTADO_CRM.md                 ← FUENTE DE INFORMACIÓN
├── README.md
├── .gitignore
├── vitacare-crm.php              # v0.1.1, Requires WP 6.4+
├── uninstall.php
├── bin/package-plugin.ps1        # genera dist/vitacare-crm.zip
├── docs/
│   ├── DESIGN.md
│   ├── PROCESS.md
│   └── OPS-HARD-DELETE.md
├── includes/
│   ├── class-vitacare-crm-activator.php
│   ├── class-vitacare-crm-upgrader.php   # NEW: maybe_upgrade + ensure_caps
│   ├── class-vitacare-crm-page.php       # gate login, noindex, enqueue gated
│   └── class-vitacare-crm-rest.php
├── template-parts/
├── assets/
└── tests/README.md               # skeleton
```

### Entregables PR-0 / 1H

| Ítem | Detalle |
|---|---|
| Login gate | Anónimo en `/crm` → `auth_redirect()` |
| Caps | `ensure_caps()` en cada `plugins_loaded` (admin) |
| SQL | Sin queries CRM si no hay `vitacare_crm_access` |
| Upgrader | `Vitacare_Crm_Upgrader::maybe_upgrade()` (db v1 idempotente) |
| noindex | `X-Robots-Tag` + `wp_robots` + exclusión sitemap nativo |
| CSS | Fallbacks `.vcrm-*` si el tema no define métricas |
| Header WP | `Requires at least: 6.4` (antes 7.0.2 erróneo) |
| Tooling | `.gitignore`, `bin/package-plugin.ps1` |
| Ops | `docs/OPS-HARD-DELETE.md` |

**No incluido en PR-0 (va en PR-1):** pantalla settings Meta / secrets.

---

## 4. Siguiente paso

1. **PR-1 / Fase 1S:** settings admin del CRM (tokens Meta preferidos en `wp-config` / options), **sin** tocar raíz ni sistema instalado.
2. Preparar App Meta (Coexistence) para Fase 2.
3. Opcional: generar ZIP con `bin/package-plugin.ps1` e instalar en WordPress; verificar https://vitacareec.org/crm (login + métricas).

---

## 5. Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Fase 1 esqueleto | `509fa94` |
| 2026-08-03 | Fix URL producción | `10d1f7c` |
| 2026-08-03 | Docs diseño + proceso GitHub | `8d1a2ce` |
| 2026-08-03 | ESTADO como fuente de verdad; solo `/crm` | `5ba672e` |
| 2026-08-03 | **Fase 1H / PR-0 hardening v0.1.1** | este update |
