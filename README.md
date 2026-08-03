# VITACARE CRM

Plugin de WordPress independiente para VITACARE: bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads, en `/crm/`.

> **Leer primero: [`ESTADO_CRM.md`](./ESTADO_CRM.md)** — decisiones ya tomadas, plan de fases y estado actual.

## Relación con el sistema principal

Este repo es independiente de `vitacare-demo` (WordPress + WooCommerce en producción). El plugin `vitacare-crm` se instala **junto a** `vitacare-core` y `vitacare-theme`, sin modificarlos: crea su propia página (`/crm/`), sus propias tablas (`wp_vitacare_crm_*`) y reutiliza el tema activo solo para heredar el header/footer/estilos visuales.

## Estructura

```
vitacare-crm/
├── vitacare-crm.php
├── includes/
├── template-parts/
└── assets/
```

## Estado

Fase 1 (esqueleto del plugin) en curso — ver `ESTADO_CRM.md` para el detalle de fases.