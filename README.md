# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads.

| | |
|---|---|
| **Repo (respaldo oficial)** | https://github.com/yusieluh/crmvitacare |
| **URL en producción** | https://vitacareec.org/crm |
| **Estado y plan** | [`ESTADO_CRM.md`](./ESTADO_CRM.md) |
| **Diseño completo** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Proceso de documentación** | [`docs/PROCESS.md`](./docs/PROCESS.md) |

> **Fuente de información del proyecto:** [`ESTADO_CRM.md`](./ESTADO_CRM.md) — se actualiza con **cada cambio** y **cada plan**.

## Integración (límites fijos)

- CRM **solo** en **https://vitacareec.org/crm**
- **No se toca** la raíz del sitio https://vitacareec.org/
- **No se modifica** el sistema ya instalado (`vitacare-core`, tema, WooCommerce, etc.)
- Solo **lectura** de datos del ecosistema; tablas propias del plugin
- Plugin instalado **junto a** lo existente, sin parchearlo

## Documentación y respaldo

- Fuente de información: **`ESTADO_CRM.md`**
- Cada tarea: actualizar ESTADO → commit → push a GitHub  
- Detalle: [`docs/PROCESS.md`](./docs/PROCESS.md)

## Estructura

```
vitacare-crm/
├── vitacare-crm.php
├── ESTADO_CRM.md
├── README.md
├── docs/
│   ├── DESIGN.md
│   └── PROCESS.md
├── includes/
├── template-parts/
├── assets/
└── uninstall.php
```

## Estado

- **Fase 1** esqueleto + **Fase 1H (PR-0) hardening** — plugin **v0.1.1** en `main`.
- **Siguiente:** PR-1 settings Meta; App Meta para WhatsApp.

Detalle y changelog: [`ESTADO_CRM.md`](./ESTADO_CRM.md).

## Instalación (resumen)

1. Clonar este repo **o** generar ZIP:
   ```powershell
   powershell -ExecutionPolicy Bypass -File .\bin\package-plugin.ps1
   ```
   → `dist/vitacare-crm.zip`
2. En WordPress: Plugins → Añadir → Subir plugin (o copiar a `wp-content/plugins/vitacare-crm/`).
3. Activar **VITACARE CRM**.
4. Abrir **https://vitacareec.org/crm** (login obligatorio; capability `vitacare_crm_access` para administradores).

**No** modifica la raíz del sitio ni otros plugins/temas.
