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
2. CRM **solo** en **https://vitacareec.org/crm** — no tocar la raíz
3. **No modificar** el sistema instalado (`vitacare-core`, tema, WooCommerce, etc.)

---

## Decisiones de producto confirmadas (Yusiel / VITACARE — 2026-08-03)

### 1. WhatsApp — “dispositivo vinculado” con QR + no bloquear número

**Deseo del negocio:**

- Poder **conectar como dispositivo vinculado** desde la app WhatsApp Business **escaneando un código QR**.
- Mantener el celular y el CRM funcionando, **con cuidado** para no bloquear la app ni el número.

**Realidad técnica y de política Meta (obligatoria en este repo):**

| Camino | ¿Cumple el deseo del QR? | ¿Oficial / seguro para el número? | En este proyecto |
|---|---|---|---|
| **A. Cloud API + Coexistence (Meta 2025+)** | No es un QR de “WhatsApp Web”, pero **sí** deja la app del teléfono operativa **y** el CRM (API) al mismo tiempo | **Sí** — soportado por Meta | **Implementación principal (ya en curso PR-1…5)** |
| **B. QR multi-dispositivo tipo WhatsApp Web** vía Baileys / whatsapp-web.js / forks “Evolution” no oficiales | Sí se parece al QR de vincular dispositivo | **No** — viola Términos de Servicio; Meta detecta y **bloquea números** con frecuencia; inaceptable para salud/pacientes | **No se implementa en el código de este CRM** |
| **C. Flujo QR embebido / partner oficial** si Meta lo documenta para ISV | Solo si Meta lo publica como oficial | Sí, si es documentación Meta vigente | Evaluar e implementar **solo** si aparece API oficial |

**Decisión de ingeniería (cerrada para el código del repo):**

| ID | Decisión |
|---|---|
| **D-04** | Canal WhatsApp de producción = **Cloud API + Coexistence**. |
| **D-04b** | **No se escribe código** de cliente no oficial (Baileys, whatsapp-web.js, etc.) en `crmvitacare`. |
| **D-04c** | UX de “vincular WhatsApp” en el CRM = **asistente Coexistence + checklist Meta** (Business Manager, número, webhooks). Si el usuario percibe “vincular el teléfono”, se explica en UI que el vínculo oficial es Coexistence, no un QR Web inventado. |
| **D-04d** | Mitigación de bloqueo: solo API oficial, rate limits, sin spam, ventana 24h respetada, tokens de system user, no multi-sesión no oficial. |

**Por qué no basta “hacerlo con cuidado” en el camino B:**  
El riesgo de ban no se elimina con “usar poco” o “solo lectura”. Meta trata esos clientes como automatización no autorizada. Para VITACARE (pacientes / reputación / un solo número de negocio) el coste de un ban es mayor que la comodidad del QR Web.

**Qué verá el staff en la UI (C-7):**

1. Botón **“Conectar WhatsApp (oficial)”**.  
2. Pasos guiados: App Meta → número → Coexistence → webhook → prueba de mensaje.  
3. Texto claro: *el celular sigue con WhatsApp Business; el CRM no se vincula como “WhatsApp Web” no oficial.*  
4. Estado: conectado / webhook OK / Coexistence.

---

### 2. Google — Gmail y lo necesario para el CRM

**Confirmado (OQ-G1):** prioridad **Gmail**, más lo necesario para operación CRM.

| Capacidad | API / enfoque | Prioridad |
|---|---|---|
| **Correo entrante** (bandeja unificada) | Gmail API + OAuth (Google Cloud project, scopes `gmail.readonly` / `gmail.modify` mínimos) | Alta |
| **Correo saliente** desde CRM | Gmail API `users.messages.send` o `wp_mail` con cuenta Google SMTP OAuth | Alta |
| **Hilos / conversación** | Mapear `threadId` Gmail → `conversations` canal `email` | Alta |
| **Adjuntos** | Descargar vía Gmail API; store como media CRM (PR-6 pattern) | Media |
| **Etiquetas / no leído** | Labels Gmail opcionales (CRM propio `status` manda en UI) | Media |
| **Contactos** | Google People API opcional para enriquecer nombre | Baja |
| **Calendar** (citas) | Google Calendar API — solo si producto lo pide después | Fuera de MVP mail |
| **Analytics / Ads** | No son bandeja CRM; no en C-5 v1 | Fuera |

**UX:** botón **“Conectar Gmail”** (OAuth Google) en Cuentas conectadas.  
**Seguridad:** OAuth tokens en options cifradas / no en Git; scopes mínimos; pantalla de consentimiento Google verificada si hay muchos usuarios.

**Plan conector:** **C-5** (tras C-1 base de cuentas).

---

### 3. TikTok — chats en bandeja + comentarios + métricas + “todo lo del CRM”

**Confirmado (OQ-T1):** se espera:

- **Chats / DMs** en la misma bandeja del CRM  
- **Comentarios** en videos  
- **Métricas** de contenido / cuenta  
- Resto útil para CRM (perfil, inbox unificado, asignación, leads)

**Estado real de APIs TikTok (actualizar al implementar):**

| Función deseada | Disponibilidad típica | Enfoque en el CRM |
|---|---|---|
| OAuth “Conectar TikTok” | **Sí** — Login Kit / TikTok for Business | **C-6** |
| **DM / chats 1:1 en bandeja** | **Muy limitada o inexistente** en APIs públicas estables para terceros (no hay equivalente maduro a Cloud API WA / Messenger) | Fase **investigación + spike C-6a**; si no hay API de DM, UI muestra “Chats no disponibles vía API oficial” y no se finge con scraping |
| **Comentarios en videos** | Parcial — APIs de contenido/comentarios según producto (Research / Business / Display) y **aprobación de app** | **C-6b** si scopes aprobados |
| **Métricas** (views, likes, etc.) | Parcial — Business/Marketing APIs o export; requiere app en revisión | **C-6c** dashboard CRM o panel métricas |
| Scraping / bots de la app TikTok | No oficial, frágil, ban de cuenta | **Prohibido** (misma lógica que WA no oficial) |

**Decisión:**

| ID | Decisión |
|---|---|
| **D-14** | TikTok se conecta solo por **API oficial OAuth**. Sin scrapers. |
| **D-15** | Alcance TikTok se implementa **por capas**: (1) OAuth + perfil, (2) métricas/comentarios si la app es aprobada, (3) DMs **solo si** TikTok expone API de mensajería usable. |
| **D-16** | Si DMs no existen en API, el CRM **no inventa** chats; se documenta en UI y se prioriza comentarios + métricas + leads manuales desde TikTok. |

**Plan:** **C-6** (OAuth) → **C-6a** spike DM → **C-6b** comentarios → **C-6c** métricas.

---

## Matriz multi-canal (visión producto)

| Canal | Conexión UX | Mensajería en bandeja | Extra CRM |
|---|---|---|---|
| **WhatsApp** | Asistente Coexistence (oficial) | ✅ Cloud API (ya) | Media PR-6 |
| **Facebook** | OAuth + **selector de Página** | ✅ Messenger Graph | — |
| **Instagram** | OAuth / Page + IG pro | ✅ IG Messaging | — |
| **Gmail** | OAuth Google | ✅ hilos mail como canal `email` | Adjuntos, send |
| **TikTok** | OAuth | ⚠️ DMs solo si API | Comentarios, métricas |
| **QR WA Web no oficial** | — | ❌ | ❌ no en repo |

---

## Plan de implementación (conectores)

| ID | Contenido | Estado |
|---|---|---|
| **C-1** | UI “Cuentas conectadas” (estado por canal) | ⏳ |
| **C-2** | Facebook OAuth + **selector de Página** | ⏳ |
| **C-3** | Instagram (Page + IG) | ⏳ |
| **C-4** | Webhooks Meta page/IG → bandeja | ⏳ |
| **C-5** | **Gmail OAuth** + inbound/outbound + map a `conversations` email | ⏳ |
| **C-6** | TikTok OAuth | ⏳ |
| **C-6a** | Spike: ¿API de DM TikTok? informe en ESTADO | ⏳ |
| **C-6b** | Comentarios de videos (si API) | ⏳ |
| **C-6c** | Métricas TikTok (si API) | ⏳ |
| **C-7** | Asistente WhatsApp Coexistence (sin QR no oficial) | ⏳ |
| **C-8** | Unificar tokens OAuth vs settings manuales | ⏳ |
| **PR-6** | Media WhatsApp | ⏳ |

**Orden recomendado:** C-1 → C-7 (claridad WA) → C-2 → C-4 → C-3 → **C-5 Gmail** → PR-6 → C-6 / C-6a…

---

## MVP ya en código (`main`)

| PR | Estado |
|---|---|
| 0–5 Hardening, settings, REST, WA in/out, bandeja | ✅ v0.6.0 |

---

## Preguntas abiertas restantes

| # | Pregunta | Notas |
|---|---|---|
| OQ-M1 | ¿App Meta + Business Manager listos? | Necesario FB/IG/WA prod |
| OQ-G2 | ¿Una sola casilla Gmail de VITACARE o varias por sede/asesor? | Diseño C-5 |
| OQ-T2 | ¿Cuenta TikTok Business ya creada / país y tipo de app? | Revisión TikTok |

**Resueltas:** OQ-G1 = Gmail+CRM; OQ-T1 = chats+comentarios+métricas deseados; OQ-W1 = deseo QR vinculado, **implementación = Coexistence oficial (D-04…D-04d)**.

---

## Siguiente paso

1. Implementar **C-1** (pantalla Cuentas conectadas) + **C-7** (texto/flujo Coexistence WhatsApp).  
2. Luego **C-2** Facebook + selector de Página.  
3. Luego **C-5** Gmail OAuth.  
4. Spike **C-6a** TikTok DM en paralelo documental.

---

## Changelog

| Fecha | Qué | Ref |
|---|---|---|
| 2026-08-03 | PR-0…PR-5 | …`8481c64` |
| 2026-08-03 | Requisitos OAuth iniciales | `d758ba3` |
| 2026-08-03 | **Confirmaciones:** WA (deseo QR vs D-04 Coexistence only), **Gmail** para CRM, **TikTok** chats/comentarios/métricas con límites API; planes C-5/C-6 | este update |
