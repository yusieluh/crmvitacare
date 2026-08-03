# CONTINUAR.md — Handoff para retomar el CRM VITACARE

Documento para **cualquier sesión futura**: Grok Build, **Claude Code**, Cursor, Codex o un desarrollador humano.

**Repo:** https://github.com/yusieluh/crmvitacare  
**Fuente de verdad:** [`../ESTADO_CRM.md`](../ESTADO_CRM.md) (siempre leer primero)

> **Una sola rama: `main`.** Grok y Claude Code trabajan alternándose sobre el mismo repo. Nunca crear una rama nueva para "tu" sesión — eso es lo que produce duplicados y conflictos. Siempre `git pull origin main` antes de tocar código y `git push` a `main` al terminar cada tarea, con `ESTADO_CRM.md` actualizado en el mismo commit (o uno inmediatamente después). Si ves una rama que no sea `main` en el remoto, no la uses: es un resto de una sesión anterior que ya quedó fusionado en `main`.

---

## 1. Arranque en 2 minutos

```bash
git clone https://github.com/yusieluh/crmvitacare.git
cd crmvitacare
git checkout main
git pull origin main
```

Windows (clone ya usado en este proyecto):

```powershell
cd C:\Users\User\Documents\crmvitacare
git pull origin main
```

Luego:

1. Abrir y leer **`ESTADO_CRM.md`** (completo).  
2. Leer este archivo.  
3. **No** rehacer PR-0…PR-6b ni C-1, C-2, C-3, C-4, C-5, C-7 (ya están en `main` v1.3.0).  
4. Elegir trabajo de la sección “Pendiente” de ESTADO.

---

## 2. Contexto de negocio y límites

| Hecho | Valor |
|---|---|
| Empresa | VITACARE Ecuador |
| CRM URL | https://vitacareec.org/crm |
| Sitio principal | https://vitacareec.org/ — **NO modificar** |
| Stack | Plugin PHP WordPress 6.4+, PHP 8.1+, Hostinger shared |
| Sistema existente | `vitacare-core` + tema + Woo — **no tocar código** |
| Datos CRM | Tablas `wp_vitacare_crm_conversations`, `wp_vitacare_crm_messages` |

---

## 3. Qué ya funciona (no reimplementar)

- Plugin activable, página `/crm`, login + capability `vitacare_crm_access`
- Bandeja UI (lista / hilo / compositor) con polling
- WhatsApp Cloud API: webhook HMAC, inbound, outbound, statuses
- Facebook: OAuth, selector de Página, Messenger in/out, subscribed_apps
- Instagram Direct: cuenta profesional vinculada a la Página (mismo token), webhook `object=instagram`, in/out Graph
- Gmail: OAuth, sync INBOX (cron 5 min), envío desde bandeja
- **Media entrante (PR-6):** descarga de imagen/audio/video/documento de WhatsApp/Messenger/Instagram a `wp-content/uploads/vitacare-crm-media/` (deny-direct vía `.htaccess`), servida solo por `GET /media/{message_id}` con cap `vitacare_crm_access`; render en la bandeja (`<img>`/`<audio>`/`<video>`/enlace de descarga)
- **Media saliente (PR-6b):** `POST /media/upload` (staff sube archivo, valida mime real + tope 25 MB) y `POST /conversations/{id}/messages` con `media_attachment_id` — WhatsApp y Messenger suben el binario directo a Graph (multipart, sin URL pública). Instagram sin soporte todavía (ver D-18 en ESTADO).
- Admin: Cuentas, WA Coexistence checklist, Facebook (+ estado Instagram), Gmail, Credenciales
- DB upgrader a v2, logger en uploads protegido

Versión plugin: **1.3.0** en `vitacare-crm.php`.

---

## 4. Pendiente prioritario

| ID | Descripción |
|---|---|
| **Ops — EN CURSO** | Desplegar en producción. `/crm` da 404 en `vitacareec.org` porque **el plugin nunca se instaló ahí**, solo vive en GitHub. El usuario pidió instalación directa y ofreció credenciales Hostinger (SSH/SFTP); se le pidieron en el chat y **todavía no las mandó**. Retomar pidiéndolas de nuevo si no llegaron — no asumir que ya se instaló. Detalle completo en `ESTADO_CRM.md` sección 5b. |
| **C-6** | TikTok OAuth; comentarios/métricas si API; DMs solo si API existe |
| **D-18** | Media saliente por Instagram: decidir si se expone media con URL firmada/expirable (Send API de IG la exige) |
| Leads | Pipeline DB v3 + UI (post-MVP en DESIGN) |

---

## 5. Decisiones que NO reabrir sin motivo fuerte

- Sin Chatwoot/erxes/VPS  
- Sin Baileys / QR WhatsApp Web no oficial  
- WhatsApp = Coexistence oficial  
- Facebook: usuario elige **una Página**  
- Gmail = canal `email` en misma bandeja  
- Documentar en ESTADO + push al cerrar cada tarea  

---

## 6. Checklist al terminar CUALQUIER tarea (obligatorio)

1. Código / cambio listo  
2. Actualizar **`ESTADO_CRM.md`** (estado, siguiente paso, changelog con hash si se conoce)  
3. Actualizar `README.md` si cambió instalación o menús  
4. `git add` + `git commit` con mensaje claro  
5. **`git push origin main`** (o PR mergeado)  
6. Confirmar en GitHub que se ve el commit  

Plantilla de commit:

```text
feat|fix|docs: <resumen corto>

- detalle
- ESTADO_CRM actualizado
```

Ver también [`PROCESS.md`](./PROCESS.md).

---

## 7. Prompt sugerido para Claude Code / otra IA

Copiar al iniciar:

```text
Trabaja en el plugin WordPress VITACARE CRM:
- Repo: https://github.com/yusieluh/crmvitacare (rama main)
- Lee primero ESTADO_CRM.md y docs/CONTINUAR.md
- NO modifiques vitacare-core, tema, ni la raíz del sitio vitacareec.org
- CRM solo en /crm
- WhatsApp solo Cloud API + Coexistence (prohibido Baileys/QR no oficial)
- Al terminar: actualiza ESTADO_CRM.md, commit y push a GitHub

Tarea concreta: <describir C-6 / D-18 / etc.>
```

---

## 8. Empaquetar para Hostinger

```powershell
cd C:\Users\User\Documents\crmvitacare
powershell -ExecutionPolicy Bypass -File .\bin\package-plugin.ps1
# → dist/vitacare-crm.zip
```

WP Admin → Plugins → Subir → Activar **VITACARE CRM**.

---

## 9. Redirect URIs a registrar (producción)

| Proveedor | URI |
|---|---|
| Meta Facebook Login | `https://vitacareec.org/wp-admin/admin.php?page=vitacare-crm-facebook` |
| Google OAuth | `https://vitacareec.org/wp-admin/admin.php?page=vitacare-crm-gmail` |
| Meta Webhook | `https://vitacareec.org/wp-json/vitacare-crm/v1/webhooks/meta` |

(Ajustar dominio si el admin WP usa otra URL.)

---

## 10. Diseño largo

[`DESIGN.md`](./DESIGN.md) tiene el diseño de producto/MVP y plan de PRs original.  
**Si hay conflicto con el código o con ESTADO_CRM.md, gana ESTADO_CRM.md + el código en `main`.**

---

*Generado para continuidad del proyecto VITACARE CRM. Actualizar cuando cambie el siguiente paso prioritario.*
