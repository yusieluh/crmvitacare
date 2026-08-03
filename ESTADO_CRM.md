# ESTADO_CRM.md — Registro vivo del CRM VITACARE

> Mismo propósito que `ESTADO_PROYECTO.md` del repo principal `vitacare-demo`: leer esto primero en cualquier sesión nueva sobre este repo, antes de tocar código. Se actualiza al cerrar cada fase.

## 0. Qué es esto y cómo se relaciona con el sistema principal

- Repositorio independiente para el plugin **VITACARE CRM** (bandeja de conversaciones WhatsApp/Facebook/Instagram/correo + gestión de leads).
- Se instala como **plugin nuevo** (`vitacare-crm`) junto a `vitacare-core` y `vitacare-theme`, sin modificar ninguno de los dos.
- Al activarse, crea automáticamente la página **`/crm/`** y reutiliza el `header.php`/`footer.php` del tema activo (vía `template_include`), heredando la identidad visual VITACARE sin duplicar CSS del tema.
- Producción real del sistema principal: `https://web.vitacareec.org/` (no tocar desde este repo). Este plugin se instalará ahí una vez probado.

## 1. Decisiones ya tomadas (no reabrir sin razón nueva)

- Se descartó `trycompai/crm` (no tiene integraciones sociales, stack pensado para Vercel, muy inmaduro).
- Se descartó montar un CRM externo tipo **erxes**/**Chatwoot**: exigirían VPS/Docker aparte y duplicarían la fuente de verdad (usuarios, pedidos, citas ya viven en WordPress/WooCommerce).
- CRM propio, como plugin de WordPress, corre en el mismo hosting compartido de Hostinger que ya usa el sitio — sin costo de infraestructura adicional.
- Ruta pública: `vitacareec.org/crm/`.
- WhatsApp: integración oficial vía **Cloud API + Coexistence** (Meta, lanzada 2025) — mantiene la app de WhatsApp Business del celular funcionando igual, sincronizada en tiempo real con el CRM. Se descarta la vía no oficial "vincular como dispositivo" (librerías tipo Baileys/whatsapp-web.js): riesgo real de bloqueo del número, contra Términos de Servicio de WhatsApp, inaceptable para un sistema de salud con pacientes reales.
- Nota de costo: desde el 1 de octubre de 2026 Meta cobra por mensajes de servicio enviados vía Cloud API — presupuestar antes de escalar volumen.
- TikTok: no tiene webhook de mensajería estándar equivalente a Meta — queda pendiente de investigación aparte (fase 7), no bloquea el resto.

## 2. Plan de fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Investigación y decisión de arquitectura (ver sección 1) | ✅ Cerrada |
| 1 | Esqueleto del plugin: tablas `wp_vitacare_crm_conversations`/`wp_vitacare_crm_messages`, capability `vitacare_crm_access`, página `/crm/` autocreada, plantilla que reutiliza el tema, panel base con métricas (0 conversaciones) | ✅ Construida localmente, **pendiente de aprobación para commit/push** |
| 2 | WhatsApp Cloud API (Coexistence): webhook de verificación + recepción, envío saliente vía Graph API, bandeja real de conversaciones/hilo de mensajes (reemplaza el placeholder de métricas) | ⏳ Pendiente — requiere que el usuario cree la app en Meta for Developers y active Coexistence |
| 3 | Facebook Messenger + Instagram Direct (Graph API), mismo patrón de webhook que fase 2, nuevo `channel` en la misma tabla | ⏳ Pendiente |
| 4 | Canal correo (entrante/saliente) — definir proveedor según lo que el usuario ya tenga (SMTP existente + inbound parse de algún proveedor, o cuenta IMAP dedicada) | ⏳ Pendiente, definir en su momento |
| 5 | Pipeline de leads: estados (nuevo/contactado/seguimiento/convertido/perdido), asignación a staff, notas, vínculo con paciente real (`wp_user_id`) o pedido WooCommerce existente | ⏳ Pendiente |
| 6 | Pulido: extender acceso a `vitacare_supervisor` si se decide, notificaciones de mensaje entrante, mobile-first para uso interno (recordando que paneles internos priorizan densidad de escritorio, no target táctil) | ⏳ Pendiente |
| 7 | TikTok — investigación de integración (sin webhook estándar de mensajería) | ⏳ Pendiente, sin bloquear el resto |

## 3. Estructura del plugin (fase 1)

```
vitacare-crm/
├── vitacare-crm.php                     # bootstrap, constantes, hooks de activación
├── includes/
│   ├── class-vitacare-crm-activator.php # tablas, capability, página /crm/
│   ├── class-vitacare-crm-page.php      # template_include + enqueue de assets
│   └── class-vitacare-crm-rest.php      # namespace REST vitacare-crm/v1 (stub)
├── template-parts/
│   ├── crm-page.php                     # get_header() + shell + get_footer()
│   └── crm-shell.php                    # contenido real de /crm/
└── assets/
    ├── css/crm.css
    └── js/crm.js
```

## 4. Siguiente paso

Aprobar el commit/push de la Fase 1 al repo, luego instalar el plugin (zip o vía SSH) en `web.vitacareec.org` como plugin nuevo, activar, y verificar que `/crm/` carga con el tema aplicado antes de empezar la Fase 2.
