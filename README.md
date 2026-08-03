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

- **Fase 1** (esqueleto): en `main`.
- **Siguiente:** hardening (Fase 1H / PR-0) y, en paralelo, preparación de App Meta para WhatsApp.

Detalle de fases y changelog: [`ESTADO_CRM.md`](./ESTADO_CRM.md).

## Instalación (resumen)

1. Clonar o descargar este repo.
2. Copiar la carpeta del plugin a `wp-content/plugins/vitacare-crm/` (o instalar ZIP).
3. Activar **VITACARE CRM** en el admin de WordPress.
4. Abrir https://vitacareec.org/crm (requiere usuario con capability `vitacare_crm_access`; por defecto administradores).

No hace falta modificar otros plugins ni el tema.
