# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.9.0** (C-4 Messenger en bandeja) |
| **DB schema** | **v2** |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. Fuente de información = este archivo  
2. Solo **https://vitacareec.org/crm**  
3. No modificar sistema instalado  
4. WhatsApp = Cloud API + Coexistence (sin QR no oficial)  
5. Facebook/Messenger = OAuth + Página + webhooks oficiales  

---

## C-4 entregado (v0.9.0) — Messenger en la bandeja

### Inbound

- Webhook `POST /wp-json/vitacare-crm/v1/webhooks/meta` con `object=page`
- Eventos `entry[].messaging[]` → conversaciones canal **`facebook`**
- PSID = `external_contact_id`; mid = `external_message_id` (dedupe)
- Echo de la Página → outbound; usuario → inbound
- Deliveries → `delivery_status=delivered`
- Filtro: solo eventos de la **Page ID** conectada en C-2

### Outbound

- Compositor en `/crm` para hilos `facebook`
- `POST /me/messages` con page access token
- Ventana 24h Messenger → error `vitacare_crm_outside_window`

### Suscripción de Página

- Al elegir Página (C-2): `POST /{page-id}/subscribed_apps` (messages, postbacks, deliveries, reads)
- Botón **Re-suscribir Página a webhooks** en admin Facebook

### Verify GET

- Acepta challenge si está activo flag WhatsApp **o** Facebook **o** Instagram  
- Mismo Verify Token / App Secret que WhatsApp

### Archivos

- `includes/class-vitacare-crm-channel-messenger.php`
- Webhook + REST send multi-canal + UI compositor FB

### Ops Meta (checklist)

1. App con Messenger / webhooks  
2. Callback URL = webhook CRM + verify token  
3. Suscribir campos de **Page** (messages)  
4. Conectar Página en CRM → Facebook  
5. Mensaje de prueba a la Página → ver en `/crm`  

---

## Plan conectores

| ID | Estado |
|---|---|
| C-1 Cuentas | ✅ |
| C-7 WA Coexistence UI | ✅ |
| C-2 Facebook OAuth + Página | ✅ |
| **C-4 Messenger bandeja** | ✅ **v0.9.0** |
| C-3 Instagram | ⏳ |
| C-5 Gmail | ⏳ |
| C-6 TikTok | ⏳ |
| PR-6 Media WA | ⏳ |

---

## Siguiente paso

1. Probar Messenger real (Page + webhook).  
2. **C-5 Gmail** o **C-3 Instagram**.  
3. PR-6 media WhatsApp.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | C-1, C-7, C-2 | `e4044e5` `4a6f13f` |
| 2026-08-03 | **C-4 Messenger v0.9.0** | este update |
