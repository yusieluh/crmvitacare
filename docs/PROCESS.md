# PROCESS.md — Documentación y respaldo en GitHub

**Repo:** https://github.com/yusieluh/crmvitacare  

## Reglas fijas

1. **`ESTADO_CRM.md` es la fuente de información** del proyecto. Se actualiza con **cada cambio** y **cada plan**.
2. El CRM corre en **https://vitacareec.org/crm** — **no se toca** la raíz ni el sistema instalado.
3. Todo se documenta en el repo y se hace **push a GitHub** al completar cada tarea.
4. Handoff entre sesiones/IAs: [`CONTINUAR.md`](./CONTINUAR.md).

---

## 1. Dónde vive cada cosa

| Qué | Dónde |
|---|---|
| **Fuente de información** | **`ESTADO_CRM.md`** |
| Continuar después / Claude Code | `docs/CONTINUAR.md` |
| Diseño técnico histórico | `docs/DESIGN.md` (secundario a ESTADO) |
| Este proceso | `docs/PROCESS.md` |
| Uso humano | `README.md` |
| Código | raíz del repo = carpeta del plugin |
| Respaldo | **GitHub `main`** |

El chat y archivos temporales **no** son el respaldo.

---

## 2. Checklist al completar cada tarea

1. Código listo; **sin** tocar core/tema/raíz del sitio.  
2. **`ESTADO_CRM.md`**: estado, plan, siguiente paso, changelog, fecha.  
3. `docs/CONTINUAR.md` si cambió “pendiente” o el prompt de handoff.  
4. `README.md` si cambió instalación/menús/versión visible.  
5. Commit con mensaje claro.  
6. **`git push origin main`**.  
7. Verificar en https://github.com/yusieluh/crmvitacare  

---

## 3. Al iniciar una sesión

1. `git pull origin main`  
2. Leer `ESTADO_CRM.md`  
3. Leer `docs/CONTINUAR.md`  
4. No reabrir decisiones cerradas sin documentar el cambio en ESTADO  

---

## 4. Integración (recordatorio)

- Plugin en WordPress de **vitacareec.org**  
- Solo **https://vitacareec.org/crm**  
- No modificar sistema existente  
- Solo lectura de datos del ecosistema WP cuando se vincule  
- Secrets nunca en el repo  

---

## 5. Automatización con asistentes (Grok / Claude / etc.)

El asistente debe:

1. Al terminar una tarea, actualizar **ESTADO_CRM** sin que el usuario lo pida.  
2. Commit + push a GitHub (salvo fallo de auth o secretos en el diff).  
3. No dejar el plan solo en el chat.  
4. No proponer QR WhatsApp no oficial ni scrapers.

---

*Actualizado 2026-08-03 — handoff multi-herramienta.*
