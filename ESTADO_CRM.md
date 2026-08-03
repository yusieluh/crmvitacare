# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.8.0** (C-2 Facebook OAuth + Página) |
| **DB schema** | **v2** |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm**
3. **No modificar** sistema instalado
4. WhatsApp = Cloud API + Coexistence; **sin** QR no oficial
5. Canales sociales = **OAuth oficial** cuando aplique

---

## C-2 entregado (v0.8.0) — Facebook OAuth + selector de Página

### Flujo

1. Credenciales: **App ID** + **App Secret** Meta  
2. Admin → **CRM VITACARE → Facebook** → **Conectar con Facebook**  
3. Meta pide acceso a la cuenta  
4. CRM lista **Páginas** (`/me/accounts`)  
5. Usuario **elige la Página** que administra  
6. Se guarda Page ID, nombre, **page access token** (cifrado si hay `VITACARE_CRM_ENCRYPTION_KEY`)  
7. Flag `vitacare_crm_feature_facebook` = ON  
8. Desconectar / reconectar disponibles  

### Config en Meta App

- Producto **Facebook Login**  
- **Valid OAuth Redirect URI:**  
  `https://vitacareec.org/wp-admin/admin.php?page=vitacare-crm-facebook`  
  (o el dominio real del WP admin)

### Scopes solicitados

`pages_show_list`, `pages_messaging`, `pages_manage_metadata`, `pages_read_engagement`, `business_management`

### Archivo

- `includes/class-vitacare-crm-facebook-oauth.php`

### Aún no (C-4)

- Webhooks `object=page` → mensajes Messenger en la bandeja  
- Suscripción de la Página al app webhook  

---

## Plan conectores

| ID | Estado |
|---|---|
| C-1 Cuentas | ✅ |
| C-7 WA Coexistence UI | ✅ |
| **C-2 Facebook OAuth + Página** | ✅ **v0.8.0** |
| C-3 Instagram | ⏳ |
| C-4 Webhooks Messenger/IG | ⏳ |
| C-5 Gmail | ⏳ |
| C-6 TikTok | ⏳ |
| PR-6 Media WA | ⏳ |

---

## Siguiente paso

1. **C-4:** recibir mensajes Messenger de la Página conectada en la bandeja.  
2. O **C-5** Gmail OAuth.  
3. En Meta: registrar redirect URI y permisos; conectar Página en admin.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-5, C-1, C-7 | …`e4044e5` |
| 2026-08-03 | **C-2 Facebook OAuth + selector Página v0.8.0** | este update |
