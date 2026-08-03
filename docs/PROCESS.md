# PROCESS.md — Documentación y respaldo en GitHub

**Repo:** https://github.com/yusieluh/crmvitacare  
**Regla:** todo plan, decisión y cambio de código se documenta en este repositorio y se sube a GitHub al completar cada tarea.

---

## 1. Principio

| Qué | Dónde vive |
|---|---|
| Estado y fases | `ESTADO_CRM.md` |
| Diseño / arquitectura / PR plan | `docs/DESIGN.md` |
| Este proceso | `docs/PROCESS.md` |
| Uso rápido | `README.md` |
| Código del plugin | raíz del repo |
| Historial de verdad | commits + push en GitHub |

El chat con la IA, zips locales y notas sueltas **no** son el respaldo. Si no está en GitHub, no cuenta.

---

## 2. Checklist al completar cada tarea

Ejecutar **en orden** al cerrar una tarea (feature, fix, fase o decisión):

1. **Código listo** (si aplica): funciona, sin tocar `vitacare-core` / tema / sistema ajeno.
2. **`ESTADO_CRM.md`**
   - Actualizar estado de la fase/PR.
   - Actualizar “Siguiente paso”.
   - Añadir fila en **Changelog** (fecha, qué, ref de commit o PR).
   - Actualizar “Última actualización”.
3. **`docs/DESIGN.md`** — solo si cambió diseño, modelo de datos, API, seguridad o plan de PRs.
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
2. Leer `ESTADO_CRM.md`.
3. Si hay trabajo de diseño: consultar `docs/DESIGN.md`.
4. No reabrir decisiones de la sección 1 de `ESTADO_CRM.md` sin motivo nuevo documentado.

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

1. Al **terminar una tarea**, actualizar la documentación listada arriba **sin que el usuario lo pida otra vez**.
2. Preparar **commit** con mensaje claro.
3. **Pedir confirmación solo si el push falla o hay duda de secretos**; la política del proyecto es respaldar en GitHub al completar.
4. Nunca dejar el diseño o el plan solo en archivos temporales fuera del repo.

---

## 7. Qué no versionar

- Secretos Meta (tokens, app secret) — van en `wp-config.php` o options cifradas en el servidor, **no** en Git.
- `node_modules`, zips generados locales si son grandes (añadir a `.gitignore` cuando existan).
- Datos reales de pacientes/conversaciones exportados.

---

*Creado: 2026-08-03. Actualizar este archivo si cambia el flujo de trabajo del equipo.*
