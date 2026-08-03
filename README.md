# VITACARE CRM

Plugin de WordPress para **VITACARE Ecuador**: bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads.

| | |
|---|---|
| **Repo (respaldo oficial)** | https://github.com/yusieluh/crmvitacare |
| **URL en producción** | https://vitacareec.org/crm |
| **Estado y plan** | [`ESTADO_CRM.md`](./ESTADO_CRM.md) |
| **Diseño completo** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Proceso de documentación** | [`docs/PROCESS.md`](./docs/PROCESS.md) |

> **Leer primero:** [`ESTADO_CRM.md`](./ESTADO_CRM.md).

## Integración con el sistema existente

- Se instala en el **WordPress del dominio** como plugin **junto a** `vitacare-core` y `vitacare-theme`.
- **No modifica** el sistema ya creado: solo **obtiene información** (lectura) de usuarios, pedidos WooCommerce, etc.
- Crea su página (`/crm`), tablas propias (`wp_vitacare_crm_*`) y reutiliza el tema solo para header/footer/estilos.

## Documentación y respaldo

Todo el plan y cada cambio se documentan en este repositorio y se suben a **GitHub al completar cada tarea**. Ver [`docs/PROCESS.md`](./docs/PROCESS.md).

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
