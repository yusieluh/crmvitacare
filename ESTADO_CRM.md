# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.7.0** (C-1 cuentas + C-7 Coexistence) |
| **DB schema** | **v2** |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm**
3. **No modificar** el sistema instalado
4. WhatsApp = **Cloud API + Coexistence** (D-04). **Sin** QR/dispositivo no oficial (D-04b)

---

## Entrega C-1 + C-7 (v0.7.0)

### C-1 — Cuentas conectadas

- Menú admin **CRM VITACARE** → hub de cuentas
- Tarjetas: WhatsApp, Facebook, Instagram, Gmail, TikTok
- Estado: listo / en progreso / pendiente / token inválido
- Enlaces a asistente WA y credenciales
- Aviso de política anti-QR no oficial

### C-7 — Asistente WhatsApp Coexistence

- Submenú **WhatsApp (oficial)**
- Checklist 11 pasos (guardado en `vitacare_crm_coex_checklist`)
- Estado live: webhook listo, envío Graph, salud token
- URL webhook copiable + fallback `rest_route`
- Enlaces Meta Developers / Business Suite
- **No** genera QR de dispositivo vinculado

### Archivos

- `includes/class-vitacare-crm-accounts.php` (nuevo)
- `includes/class-vitacare-crm-settings.php` (menú reordenado)
- `vitacare-crm.php` v0.7.0
- Enlaces desde bandeja `/crm`

---

## Plan conectores

| ID | Contenido | Estado |
|---|---|---|
| **C-1** | Cuentas conectadas | ✅ v0.7.0 |
| **C-7** | Asistente Coexistence WA | ✅ v0.7.0 |
| C-2 | Facebook OAuth + selector Página | ⏳ |
| C-3/C-4 | Instagram + webhooks page | ⏳ |
| C-5 | Gmail OAuth | ⏳ |
| C-6 | TikTok OAuth / spike DM | ⏳ |
| PR-6 | Media WA | ⏳ |

---

## MVP mensajería (previo)

PR-0…PR-5 ✅ (hardening, settings tokens, REST, WA in/out, bandeja)

---

## Siguiente paso

1. **C-2** Facebook Login + listar/elegir Página.  
2. Instalar **v0.7.0** y completar checklist Coexistence en admin.  
3. C-5 Gmail.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-5 | …`8481c64` |
| 2026-08-03 | Docs OAuth / Gmail / TikTok / política WA | `d758ba3` `4a85c17` |
| 2026-08-03 | **C-1 + C-7 v0.7.0** | este update |
