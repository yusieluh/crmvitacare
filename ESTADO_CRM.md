# ESTADO_CRM.md — Registro vivo del CRM VITACARE

> **Leer esto primero** en cualquier sesión nueva sobre este repo, antes de tocar código.  
> Se actualiza **al cerrar cada tarea/fase/PR** y se hace **commit + push a GitHub** como respaldo obligatorio.

**Repositorio:** https://github.com/yusieluh/crmvitacare  
**Sitio / WordPress:** https://vitacareec.org/  
**URL pública del CRM:** https://vitacareec.org/crm  
**Diseño completo:** [`docs/DESIGN.md`](./docs/DESIGN.md)  
**Proceso de documentación:** [`docs/PROCESS.md`](./docs/PROCESS.md)  
**Última actualización:** 2026-08-03

---

## 0. Qué es esto y cómo se relaciona con el sistema principal

- Repositorio independiente para el plugin **VITACARE CRM** (bandeja de conversaciones WhatsApp/Facebook/Instagram/correo + gestión de leads).
- Se instala en el **WordPress del dominio** (`vitacareec.org`) como **plugin nuevo** (`vitacare-crm`), **integrado** con el sistema ya creado (`vitacare-core`, `vitacare-theme`, WooCommerce).
- **Regla de no invasión:** no modifica, no parchea y no reescribe el sistema existente. Solo **obtiene información** (lectura) de usuarios WP, pedidos, roles, etc., para vincular conversaciones y leads.
- Los datos propios del CRM (conversaciones, mensajes, leads, settings del plugin) viven en tablas/options **propias** (`wp_vitacare_crm_*`).
- Al activarse, crea la página **`/crm`** → **https://vitacareec.org/crm** y reutiliza header/footer del tema activo (vía `template_include`) sin editar el tema.
- El código, el plan, el diseño y el historial de cambios **viven en este repo de GitHub** como respaldo de verdad.

---

## 0.1 Política de respaldo y documentación (obligatoria)

Al **completar cada tarea** (fase, PR, fix o decisión de producto):

1. Actualizar este archivo (`ESTADO_CRM.md`): estado de fases, siguiente paso, y entrada en el **Changelog**.
2. Si cambió arquitectura o alcance: actualizar `docs/DESIGN.md` (o añadir nota en Open Questions resueltas).
3. Si cambió uso para humanos: actualizar `README.md`.
4. **Commit** con mensaje claro en español o conventional commits.
5. **Push a `main` (o PR mergeado)** en https://github.com/yusieluh/crmvitacare — el remoto es el respaldo oficial.

Nada “queda solo en el chat” o en archivos temporales: si no está en GitHub, **no está documentado**.

Detalle operativo: [`docs/PROCESS.md`](./docs/PROCESS.md).

---

## 1. Decisiones ya tomadas (no reabrir sin razón nueva)

| ID | Decisión |
|---|---|
| D-01 | CRM propio como **plugin WordPress** en el mismo hosting (Hostinger shared). Sin VPS/Docker para Chatwoot/erxes. |
| D-02 | **No modificar** `vitacare-core` / `vitacare-theme` / sistema principal; solo **lectura** de datos del ecosistema. |
| D-03 | URL del CRM: **https://vitacareec.org/crm** (sitio: https://vitacareec.org/) |
| D-04 | WhatsApp: solo **Cloud API + Coexistence** (Meta). Prohibido Baileys / whatsapp-web.js. |
| D-05 | Canales: WhatsApp → FB/IG → correo; TikTok investigación aparte. |
| D-06 | Tablas propias: `wp_vitacare_crm_conversations`, `wp_vitacare_crm_messages` (+ leads en fase posterior). |
| D-07 | Capability: `vitacare_crm_access` (admin nativo por ahora). |
| D-08 | **GitHub = respaldo y fuente de documentación**; plan y cambios se documentan al cerrar cada tarea. |
| D-09 | Diseño de producto e implementación incremental: ver `docs/DESIGN.md` (MVP = PR-0…PR-6). |

Otras notas:

- Descartados: `trycompai/crm`, erxes, Chatwoot (infra y fuente de verdad duplicada).
- Costo Meta: desde 1 oct 2026 cobran mensajes de servicio vía Cloud API — presupuestar al escalar.
- TikTok: sin webhook de mensajería estándar → fase 7, no bloquea.

---

## 2. Plan de fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Investigación y decisión de arquitectura | ✅ Cerrada |
| 1 | Esqueleto del plugin (tablas, cap, `/crm`, plantilla, métricas, REST ping) | ✅ En GitHub (`main` @ Fase 1) |
| 1H | Hardening Fase 1 (login gate, upgrader, CSS fallback, docs, zip) — **PR-0** del diseño | ⏳ Pendiente |
| 1S | Settings / secrets Meta — **PR-1** | ⏳ Pendiente |
| 2 | WhatsApp Cloud API (Coexistence): webhook, inbound/outbound, bandeja real | ⏳ Pendiente (requiere App Meta) |
| 3 | Facebook Messenger + Instagram Direct | ⏳ Pendiente |
| 4 | Canal correo | ⏳ Pendiente |
| 5 | Pipeline de leads | ⏳ Pendiente |
| 6 | Pulido: roles, notificaciones, UX | ⏳ Pendiente |
| 7 | TikTok — investigación | ⏳ Pendiente |

**MVP shippable (diseño):** PR-0 → PR-6 (hardening + settings + WhatsApp + bandeja + media). Detalle en [`docs/DESIGN.md`](./docs/DESIGN.md) sección **PR Plan**.

---

## 3. Estructura del plugin (fase 1 actual en repo)

```
crmvitacare/   (repo GitHub = carpeta del plugin en WP)
├── vitacare-crm.php
├── ESTADO_CRM.md              # este archivo (estado vivo)
├── README.md
├── docs/
│   ├── DESIGN.md              # diseño completo + plan de PRs
│   └── PROCESS.md             # cómo documentar y respaldar cada tarea
├── includes/
│   ├── class-vitacare-crm-activator.php
│   ├── class-vitacare-crm-page.php
│   └── class-vitacare-crm-rest.php
├── template-parts/
│   ├── crm-page.php
│   └── crm-shell.php
├── assets/
│   ├── css/crm.css
│   └── js/crm.js
└── uninstall.php
```

---

## 4. Siguiente paso

1. **PR-0 / Fase 1H:** hardening del esqueleto (acceso a `/crm`, migraciones, CSS mínimo, `.gitignore`, empaquetado).
2. En paralelo: crear App en Meta for Developers (Coexistence) cuando se vaya a Fase 2.
3. Instalar el plugin en WordPress de `vitacareec.org` **sin tocar** core/tema; verificar https://vitacareec.org/crm tras activar.

---

## 5. Changelog (historial en repo)

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Fase 1 esqueleto en `main` | commit `509fa94` |
| 2026-08-03 | Fix URL producción → `vitacareec.org` | commit `10d1f7c` |
| 2026-08-03 | Política GitHub = respaldo; documentar cada tarea; URL `vitacareec.org/crm`; integración solo-lectura; `docs/DESIGN.md` + `docs/PROCESS.md` | este update |
