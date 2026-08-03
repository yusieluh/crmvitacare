# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **1.0.0** (C-5 Gmail) |
| **DB** | v2 |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. Este archivo = fuente de información  
2. Solo `/crm` — no tocar raíz del sitio  
3. No modificar sistema instalado  
4. Integraciones **oficiales** (Meta / Google OAuth; WA Coexistence; sin QR no oficial)

---

## C-5 entregado (v1.0.0) — Gmail

### Funciones

| Función | Detalle |
|---|---|
| OAuth Google | Conectar cuenta Gmail (offline + refresh token) |
| Credenciales | Client ID/Secret en admin o `VITACARE_CRM_GOOGLE_CLIENT_*` en wp-config |
| Sync entrante | Cron ~5 min + botón «Sincronizar ahora»; INBOX últimos 14 días |
| Canal bandeja | `email` — hilos por dirección del contacto |
| Envío | Compositor `/crm` → Gmail API `messages.send` |
| IDs | `external_message_id` = `gmail:{id}` |

### Ops Google Cloud

1. Activar **Gmail API**  
2. OAuth Client tipo **Web**  
3. Redirect URI:  
   `https://vitacareec.org/wp-admin/admin.php?page=vitacare-crm-gmail`  
4. Consent screen + scopes Gmail  
5. CRM → **Gmail** → guardar Client ID/Secret → **Conectar con Google**

### Archivo

- `includes/class-vitacare-crm-gmail.php`

### Límites v1

- Texto plano (HTML se convierte a texto al importar)  
- Sin adjuntos pesados todavía  
- Depende de WP-Cron (recomendable cron real Hostinger cada 5 min a `wp-cron.php`)

---

## Canales listos en bandeja

| Canal | In | Out | Conexión |
|---|---|---|---|
| WhatsApp | ✅ | ✅ | Coexistence + tokens |
| Facebook Messenger | ✅ | ✅ | OAuth + Página |
| **Gmail / email** | ✅ | ✅ | **OAuth Google** |
| Instagram | ⏳ | ⏳ | — |
| TikTok | ⏳ | ⏳ | — |

---

## Plan restante

| ID | Estado |
|---|---|
| C-1…C-2, C-4, C-7 | ✅ |
| **C-5 Gmail** | ✅ **1.0.0** |
| C-3 Instagram | ⏳ |
| C-6 TikTok | ⏳ |
| PR-6 Media WA | ⏳ |

---

## Siguiente paso

1. Configurar proyecto Google y conectar Gmail en admin.  
2. C-3 Instagram o PR-6 media.  
3. Cron real en Hostinger para sync Gmail fiable.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | Messenger C-4 | `053eca2` |
| 2026-08-03 | **Gmail C-5 v1.0.0** | este update |
