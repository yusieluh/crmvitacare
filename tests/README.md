# Tests (skeleton — Fase 1H / PR-0)

Aún no hay suite PHPUnit en CI. Este directorio marca el lugar para:

| Área | Cuándo |
|---|---|
| Gate de acceso / capability | PR-0+ |
| HMAC webhook Meta | PR-3 |
| REST conversaciones | PR-2+ |

Para ejecutar tests de integración hará falta un bootstrap WordPress (`WP_TESTS_DIR`) en un entorno de desarrollo — **no** en el sistema de producción ni modificando `vitacare-core`.

Smoke manual post-install:

1. Activar plugin.
2. Anónimo → `https://vitacareec.org/crm` redirige a login.
3. Admin → ve métricas.
4. Usuario sin `vitacare_crm_access` → mensaje de denegación sin error SQL.
5. Raíz `https://vitacareec.org/` intacta.
