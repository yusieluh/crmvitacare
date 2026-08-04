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
3. **No** rehacer PR-0…PR-6b, C-1, C-2, C-3, C-4, C-5, C-6, C-7, D-19 (puente VITACARE), D-20 (despliegue automático) ni D-22 (Zoho Mail) — ya están en `main` v1.6.0. **C-6 TikTok en particular: no reabrir para "agregar mensajería"** — el spike ya confirmó que TikTok no tiene API pública de DMs (ver D-21 en ESTADO_CRM.md), solo el conector de verificación de cuenta. **El despliegue en producción ya está resuelto** (plugin activo, `/crm` responde) — no volver a pedirle SSH/SFTP al usuario.  
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
- **Puente solo-lectura a VITACARE (D-19):** `GET /conversations/{id}/vitacare-contact` (clase `Vitacare_Crm_Vitacare_Bridge`, consulta SQL directa a `wp_users`/`wp_vitacare_*`, sin dependencia de código de `vitacare-core`), match por correo o por últimos 9 dígitos del teléfono; panel "Contacto en VITACARE" en la bandeja (nombre, correo, rol, membresía, citas recientes, pendiente de pago).
- **Despliegue automático (D-20):** `.github/workflows/deploy-hostinger.yml` — push a `main` sincroniza (rsync SSH) solo `wp-content/plugins/vitacare-crm/`; requiere los 4 secrets de Hostinger cargados en Settings → Secrets **de este repo** (no se heredan de `vitacare-demo`, son por-repo en GitHub).
- **TikTok Login Kit (C-6):** `Vitacare_Crm_Tiktok_Oauth`, OAuth v2 oficial (`vitacare-crm-tiktok` en el admin) que conecta y verifica una cuenta de TikTok (nombre, avatar, ID). **No es un canal de mensajería** — TikTok no publica ninguna API pública de DMs/comentarios para apps de terceros (confirmado, ver D-21), así que no hay webhook ni entrada en la bandeja para TikTok.
- **Zoho Mail (D-22):** `Vitacare_Crm_Zoho`, OAuth v2 oficial (`vitacare-crm-zoho` en el admin) — segundo proveedor de correo bajo el **mismo canal `email`** que Gmail (no uno nuevo), con sync entrante cron y envío desde la bandeja igual que Gmail. Cada conversación recuerda en `meta.mail_provider` qué buzón la maneja para responder por el correcto.
- Admin: Cuentas, WA Coexistence checklist, Facebook (+ estado Instagram), TikTok, Gmail, Credenciales
- DB upgrader a v2, logger en uploads protegido

Versión plugin: **1.6.0** en `vitacare-crm.php`.

---

## 4. Pendiente prioritario

| ID | Descripción |
|---|---|
| **Ops** | Conectar Facebook (Página + Instagram vinculado) y Gmail/Zoho Mail desde el admin, ya en el sitio real (plugin activo, deploy resuelto). |
| **Ops** | Opcional: crear apps de desarrollador en TikTok for Developers y/o Zoho API Console si se quieren usar esos conectores (ver ESTADO_CRM.md sección 5). |
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

**Desde D-20, el despliegue a producción es automático**: cada push a `main` dispara `.github/workflows/deploy-hostinger.yml`, que sincroniza el repo (menos `.git`/`.github`/`dist`/`tests`/`bin`/`.idea`/`.vscode`/`.gitignore`) a `wp-content/plugins/vitacare-crm/` vía SSH+rsync — siempre que los 4 secrets de Hostinger ya estén cargados en Settings → Secrets de este repo. El empaquetado manual de abajo sigue siendo válido como respaldo (o si los secrets aún no están configurados).

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
