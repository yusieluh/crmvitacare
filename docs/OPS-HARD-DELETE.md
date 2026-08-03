# Hard-delete de datos del CRM (operaciones)

Por diseño, **desactivar o desinstalar el plugin no borra** tablas ni la página `/crm`: son datos de negocio (conversaciones / mensajes).

## Borrado voluntario (solo si se pide explícitamente)

Ejecutar solo con backup y autorización. Ejemplo SQL (prefijo `wp_` puede variar):

```sql
DROP TABLE IF EXISTS wp_vitacare_crm_messages;
DROP TABLE IF EXISTS wp_vitacare_crm_conversations;
DELETE FROM wp_options WHERE option_name LIKE 'vitacare_crm_%';
-- Página /crm: borrar desde WP Admin → Páginas, o:
-- DELETE FROM wp_posts WHERE post_name = 'crm' AND post_type = 'page';
```

No automatizar esto en `uninstall.php` sin decisión de producto documentada en `ESTADO_CRM.md`.
