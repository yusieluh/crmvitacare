# PROCESS.md — Documentación y respaldo en GitHub

**Repo:** https://github.com/yusieluh/crmvitacare  

## Reglas fijas

1. **`ESTADO_CRM.md` es la fuente de información** del proyecto (estado, plan, decisiones, changelog, siguiente paso). Se actualiza con **cada cambio** y **cada plan**.
2. El CRM corre en **https://vitacareec.org/crm** — **no se toca** la raíz https://vitacareec.org/ ni el sistema ya instalado.
3. Todo se documenta en el repo y se hace **push a GitHub** al completar cada tarea.

---

## 1. Principio

| Qué | Dónde vive |
|---|---|
| **Fuente de información (obligatoria)** | **`ESTADO_CRM.md`** |
| Diseño técnico / detalle PR plan | `docs/DESIGN.md` (complemento; no sustituye ESTADO) |
| Este proceso | `docs/PROCESS.md` |
| Uso rápido | `README.md` |
| Código del plugin | raíz del repo |
| Respaldo | commits + push en GitHub |

El chat, zips locales y notas sueltas **no** son la fuente de verdad. Si no está en `ESTADO_CRM.md` + GitHub, no cuenta.

---

## 2. Checklist al completar cada tarea

Ejecutar **en orden** al cerrar una tarea (feature, fix, fase o decisión):

1. **Código listo** (si aplica): funciona; **no toca** raíz del sitio, `vitacare-core`, tema ni sistema instalado.
2. **`ESTADO_CRM.md` (siempre — fuente de información)**
   - Actualizar estado de la fase/PR y el plan si cambió.
   - Actualizar “Siguiente paso”.
   - Añadir fila en **Changelog** (fecha, qué, ref de commit o PR).
   - Actualizar “Última actualización”.
3. **`docs/DESIGN.md`** — si cambió diseño, modelo de datos, API, seguridad o detalle de PRs (además de reflejarlo en ESTADO).
4. **`README.md`** — solo si cambió instalación, URL o uso para humanos.
5. **Commit** en el clone local:
   ```text
   docs: actualizar ESTADO tras <tarea>
   ```
   o, si hay código:
   ```text
   feat|fix|chore: <resumen>
   ```
6. **Push** a GitHub (`main` o rama de PR + merge).
7. Confirmar en https://github.com/yusieluh/crmvitacare que los archivos se ven actualizados.

---

## 3. Al iniciar una sesión de trabajo

1. `git pull` del repo.
2. Leer **`ESTADO_CRM.md`** (fuente de información).
3. Si hay trabajo de diseño: consultar `docs/DESIGN.md` como detalle.
4. No reabrir decisiones de `ESTADO_CRM.md` sin motivo nuevo **documentado en el mismo archivo**.
5. Recordar: solo **https://vitacareec.org/crm**; no tocar raíz ni sistema instalado.

---

## 4. Integración con el sistema VITACARE (recordatorio)

- Plugin instalado en WordPress de **vitacareec.org**.
- CRM en **https://vitacareec.org/crm**.
- **No modificar** el sistema existente.
- **Solo lectura** de información del sistema (usuarios, pedidos, etc.).
- Datos del CRM en tablas propias del plugin.

---

## 5. Convención de ramas (recomendada)

| Rama | Uso |
|---|---|
| `main` | Estable / instalable |
| `feat/...` o `fix/...` | Trabajo en curso → PR → merge a `main` |

Tras merge a `main`, el Changelog en `ESTADO_CRM.md` debe reflejar el cambio (puede ir en el mismo PR).

---

## 6. Automatización con el asistente (Grok / IA)

En este proyecto, el asistente debe:

1. Al **terminar una tarea**, actualizar **primero `ESTADO_CRM.md`** y el resto según checklist, **sin que el usuario lo pida**.
2. Preparar **commit** con mensaje claro.
3. **Push a GitHub** al completar (salvo fallo de red/auth o riesgo de secretos).
4. Nunca dejar el plan o el estado solo en el chat o en archivos temporales.
5. No proponer cambios a la raíz del sitio ni al código del sistema ya instalado.

---

## 7. Qué no versionar

- Secretos Meta (tokens, app secret) — van en `wp-config.php` o options cifradas en el servidor, **no** en Git.
- `node_modules`, zips generados locales si son grandes (añadir a `.gitignore` cuando existan).
- Datos reales de pacientes/conversaciones exportados.

---

*Creado: 2026-08-03. Actualizar este archivo si cambia el flujo de trabajo del equipo.*
