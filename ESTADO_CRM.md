# ESTADO_CRM.md — Fuente de verdad del CRM VITACARE

> **ESTE ARCHIVO ES LA FUENTE DE INFORMACIÓN DEL PROYECTO.**  
> Cada cambio/plan → actualizar aquí → commit + push a GitHub.  
> Repo: https://github.com/yusieluh/crmvitacare

| Campo | Valor |
|---|---|
| **Sitio (raíz — NO tocar)** | https://vitacareec.org/ |
| **URL del CRM** | **https://vitacareec.org/crm** |
| **Versión plugin** | **0.6.0** (PR-5 bandeja UI) |
| **DB schema** | **v2** |
| **Diseño** | [`docs/DESIGN.md`](./docs/DESIGN.md) |
| **Última actualización** | 2026-08-03 |

---

## Reglas inviolables

1. **`ESTADO_CRM.md` = fuente de información**
2. CRM **solo** en **https://vitacareec.org/crm**
3. **No modificar** sistema instalado

---

## Plan de fases (MVP)

| PR | Contenido | Estado |
|---|---|---|
| 0 | Hardening | ✅ |
| 1 | Settings Meta | ✅ |
| 2 | REST + DB v2 | ✅ |
| 3 | WhatsApp inbound | ✅ |
| 4 | WhatsApp outbound | ✅ |
| **5** | **Inbox UI** | ✅ **v0.6.0** |
| 6 | Media | ⏳ |

---

## PR-5 entregado (v0.6.0)

### Bandeja en https://vitacareec.org/crm

Layout 3 columnas (responsive):

1. **Lista** — filtros estado/canal/búsqueda; preview; badge no leídos; chip canal  
2. **Hilo** — burbujas in/out; meta hora/CRM|App/delivery; cerrar/reabrir  
3. **Contexto** — nombre, teléfono, canal, estado, asignado, id externo  

### Comportamiento JS (`assets/js/crm.js`)

- `GET /conversations`, `GET …/messages`, `POST …/messages`, `PATCH` status  
- Polling **20 s** (pausa si pestaña oculta)  
- Enter envía; Shift+Enter nueva línea  
- Cuerpos con `textContent` (sin XSS por innerHTML)  
- Error ventana 24h mostrado en compositor  
- Envío solo si canal = whatsapp y hilo no cerrado  
- Al abrir hilo: `mark_read` (unread_count = 0)

### Archivos tocados

- `template-parts/crm-shell.php`  
- `assets/css/crm.css`, `assets/js/crm.js`  
- `includes/class-vitacare-crm-page.php` (i18n + poll)  
- `mark_read` en conversations repo + REST messages  

---

## Siguiente paso

1. **PR-6:** descarga/servir media WhatsApp.  
2. Instalar v0.6.0 en WordPress y probar bandeja con un hilo real.  
3. Post-MVP: FB/IG, email, leads UI.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-4 | …`b07f9f2` |
| 2026-08-03 | **PR-5 bandeja UI v0.6.0** | este update |
