# MonitorSQL — Documentación Base del Proyecto

## 1. Resumen ejecutivo

**MonitorSQL** será una plataforma web para entregar acceso **solo lectura** a bases de datos SQL, permitiendo que usuarios autorizados puedan explorar tablas, consultar datos con ayuda de IA, visualizar resultados y exportar información de forma segura.

El objetivo principal es reducir la fricción técnica al consultar bases de datos, sin comprometer la seguridad ni permitir modificaciones sobre los datos originales.

## 2. Visión del producto

MonitorSQL busca convertirse en una capa segura e inteligente entre usuarios de negocio, analistas, equipos técnicos y bases de datos relacionales.

La aplicación permitirá:

- Conectar una o más bases de datos SQL en modo lectura.
- Explorar esquemas, tablas, columnas, tipos de datos y relaciones.
- Consultar datos usando SQL manual o lenguaje natural asistido por IA.
- Visualizar resultados en tablas interactivas.
- Exportar resultados a CSV, Excel o JSON.
- Controlar accesos por usuario, rol, conexión, tabla o esquema.
- Registrar auditoría de consultas y exportaciones.

## 3. Nombre del proyecto

**MonitorSQL**

### Posibles descripciones cortas

- “Consulta tus datos SQL con IA, sin exponer permisos de escritura.”
- “Explorador SQL seguro, visual e inteligente.”
- “Acceso de solo lectura a bases de datos con consultas asistidas por IA.”

## 4. Stack tecnológico propuesto

### Backend

- **Laravel 13**
- **PHP 8.3+**
- **Laravel AI SDK**
- **Laravel Sanctum** o sistema de autenticación nativo según el tipo de despliegue
- **Laravel Policies / Gates** para autorización
- **Laravel Queues** para exportaciones grandes
- **Laravel Scheduler** para tareas de limpieza, auditoría o refresco de metadatos

### Frontend

- **Vue 3**
- **Inertia.js** o frontend desacoplado con API REST
- **shadcn-vue** para componentes UI
- **Tailwind CSS**
- **TanStack Table** o componente Data Table compatible con Vue
- **Chart.js**, **ECharts** o **Recharts-compatible alternative** para visualización de datos

### Base de datos interna de MonitorSQL

- PostgreSQL o MySQL para almacenar usuarios, roles, conexiones, permisos, auditoría y configuraciones.

### Bases de datos externas soportadas inicialmente

- MySQL / MariaDB
- PostgreSQL

Soporte futuro:

- SQL Server
- SQLite
- Oracle
- ClickHouse
- BigQuery

## 5. Principios clave

### 5.1 Solo lectura por diseño

MonitorSQL no debe permitir operaciones de escritura sobre las bases de datos conectadas.

Operaciones bloqueadas:

- INSERT
- UPDATE
- DELETE
- DROP
- ALTER
- TRUNCATE
- CREATE
- REPLACE
- GRANT
- REVOKE
- EXEC / CALL, salvo que se autoricen explícitamente procedimientos seguros

Estrategias recomendadas:

1. Usar credenciales SQL de solo lectura a nivel de base de datos.
2. Validar y bloquear sentencias peligrosas desde la aplicación.
3. Ejecutar consultas en transacciones read-only cuando el motor lo soporte.
4. Aplicar límites de filas y tiempo máximo de ejecución.
5. Auditar cada consulta ejecutada.

### 5.2 Seguridad antes que comodidad

La IA nunca debe ejecutar directamente una consulta sin validación previa.

Toda consulta generada por IA debe pasar por:

- Clasificación de intención.
- Validación sintáctica.
- Verificación de que sea solo lectura.
- Revisión de tablas permitidas para el usuario.
- Límite automático de resultados.
- Confirmación del usuario antes de ejecutar, si el riesgo es medio o alto.

### 5.3 Transparencia

El usuario debe ver siempre:

- La consulta SQL generada.
- Las tablas involucradas.
- Los filtros aplicados.
- El límite de resultados.
- Si la respuesta fue generada por IA.

## 6. Roles de usuario

### Administrador

Puede:

- Crear conexiones a bases de datos.
- Configurar credenciales.
- Asignar permisos por usuario o rol.
- Definir límites de consulta.
- Ver auditoría.
- Activar o desactivar funciones de IA.

### Analista

Puede:

- Explorar tablas autorizadas.
- Ejecutar consultas SQL de solo lectura.
- Usar consultas asistidas por IA.
- Exportar resultados si tiene permiso.
- Crear consultas guardadas.

### Usuario de negocio

Puede:

- Hacer preguntas en lenguaje natural.
- Ver resultados filtrados.
- Exportar información si tiene permiso.
- Usar dashboards o consultas predefinidas.

### Auditor

Puede:

- Revisar historial de consultas.
- Revisar exportaciones.
- Ver accesos por usuario.
- No necesariamente puede consultar datos directamente.

## 7. Módulos principales

## 7.1 Autenticación y autorización

Funcionalidades:

- Login.
- Gestión de usuarios.
- Gestión de roles.
- Permisos por conexión.
- Permisos por base de datos, esquema, tabla o columna.
- Restricción de exportación por rol.

Permisos sugeridos:

- `connections.view`
- `connections.create`
- `connections.update`
- `connections.delete`
- `schemas.view`
- `tables.view`
- `queries.execute`
- `queries.ai_generate`
- `queries.export`
- `audit.view`

## 7.2 Gestión de conexiones SQL

Cada conexión debe guardar:

- Nombre visible.
- Motor SQL.
- Host.
- Puerto.
- Base de datos.
- Usuario.
- Contraseña cifrada.
- SSL habilitado o no.
- Estado de conexión.
- Fecha de última prueba.
- Configuración de límites.

Buenas prácticas:

- Cifrar credenciales con Laravel Encryption.
- No mostrar contraseñas luego de guardarlas.
- Probar conexión antes de activarla.
- Recomendar usuarios SQL de solo lectura.
- Permitir rotación de credenciales.

## 7.3 Explorador de esquemas y tablas

El usuario podrá navegar:

- Conexiones disponibles.
- Bases de datos o esquemas.
- Tablas.
- Columnas.
- Tipos de datos.
- Índices.
- Relaciones detectadas.
- Conteo aproximado de filas.
- Vista previa limitada de datos.

Pantallas sugeridas:

- Listado de conexiones.
- Árbol de esquemas/tablas.
- Detalle de tabla.
- Vista previa de datos.
- Buscador de columnas.

## 7.4 Editor SQL

Características:

- Editor con resaltado SQL.
- Autocompletado de tablas y columnas.
- Historial de consultas.
- Consultas favoritas.
- Límite automático.
- Validación de solo lectura.
- Resultados paginados.
- Tiempo de ejecución visible.

Reglas:

- Agregar `LIMIT` automático si el usuario no lo define.
- Bloquear múltiples sentencias si no son necesarias.
- Bloquear comentarios sospechosos o intentos de bypass.
- Mostrar errores de forma segura sin exponer credenciales ni detalles internos.

## 7.5 Consultas asistidas por IA

El usuario podrá escribir preguntas como:

- “Muéstrame los clientes registrados este mes.”
- “Agrupa las ventas por día de la última semana.”
- “Encuentra los productos con menor stock.”
- “Genera una consulta para comparar ingresos por categoría.”

Flujo propuesto:

1. Usuario selecciona conexión y, opcionalmente, tablas disponibles.
2. Usuario escribe pregunta en lenguaje natural.
3. El sistema obtiene metadatos permitidos para ese usuario.
4. La IA genera una consulta SQL.
5. El backend valida la consulta.
6. Se muestra la consulta al usuario.
7. El usuario ejecuta la consulta.
8. El sistema muestra resultados y explicación breve.

La IA debe recibir solo el esquema permitido, no datos completos salvo muestras anonimizadas o autorizadas.

## 7.6 Visualización de resultados

Vista de resultados:

- Tabla paginada.
- Ordenamiento.
- Filtros rápidos.
- Búsqueda sobre resultados cargados.
- Selector de columnas visibles.
- Copiar celda/fila.
- Conteo de resultados.
- Tiempo de ejecución.

Visualizaciones sugeridas:

- Tabla.
- Barras.
- Líneas.
- Pie / donut.
- Métricas tipo cards.
- Agrupaciones simples.

La IA puede sugerir visualizaciones según los tipos de columnas:

- Fechas + números: gráfico de línea.
- Categorías + números: gráfico de barras.
- Estado / categoría porcentual: donut.
- Un único valor agregado: KPI card.

## 7.7 Exportaciones

Formatos iniciales:

- CSV
- XLSX
- JSON

Reglas:

- Exportar solo resultados de consultas validadas.
- Registrar auditoría de cada exportación.
- Permitir límites por rol.
- Ejecutar exportaciones grandes en cola.
- Expirar archivos exportados después de cierto tiempo.

Metadatos de exportación:

- Usuario.
- Conexión.
- Consulta ejecutada.
- Fecha.
- Cantidad de filas.
- Formato.
- IP.

## 7.8 Auditoría

Registrar:

- Inicio de sesión.
- Creación o edición de conexiones.
- Consulta ejecutada.
- Consulta generada por IA.
- Exportación realizada.
- Error de permisos.
- Intento de consulta bloqueada.

Campos sugeridos:

- `id`
- `user_id`
- `connection_id`
- `action`
- `sql_hash`
- `sql_preview`
- `status`
- `duration_ms`
- `rows_returned`
- `ip_address`
- `user_agent`
- `created_at`

## 8. Arquitectura propuesta

### Capas

1. **UI Vue + shadcn-vue**
2. **Laravel Controllers / Inertia Pages**
3. **Application Services**
4. **AI Services**
5. **SQL Connection Manager**
6. **Query Validator**
7. **Read-only Query Executor**
8. **Audit Logger**
9. **Export Service**

### Servicios principales

- `ConnectionService`
- `SchemaIntrospectionService`
- `QueryValidationService`
- `ReadOnlyQueryExecutor`
- `AiSqlAssistantService`
- `ResultVisualizationService`
- `ExportService`
- `AuditService`

## 9. Flujo de consulta con IA

```text
Usuario escribe pregunta
        ↓
Frontend envía pregunta + contexto seleccionado
        ↓
Backend obtiene esquema permitido
        ↓
AiSqlAssistantService genera SQL
        ↓
QueryValidationService valida solo lectura y permisos
        ↓
Frontend muestra SQL generado
        ↓
Usuario ejecuta
        ↓
ReadOnlyQueryExecutor ejecuta con límites
        ↓
AuditService registra evento
        ↓
Frontend muestra tabla, visualización y opciones de exportación
```

## 10. Modelo de datos interno sugerido

### `users`

Usuarios del sistema.

### `roles`

Roles funcionales.

### `permissions`

Permisos granulares.

### `database_connections`

Conexiones externas configuradas.

Campos sugeridos:

- `id`
- `name`
- `driver`
- `host`
- `port`
- `database`
- `username`
- `encrypted_password`
- `ssl_enabled`
- `options`
- `is_active`
- `created_by`
- `created_at`
- `updated_at`

### `connection_permissions`

Permisos por usuario o rol sobre conexiones, esquemas, tablas o columnas.

### `saved_queries`

Consultas guardadas por usuarios.

### `query_runs`

Historial de ejecuciones.

### `exports`

Historial y estado de exportaciones.

### `audit_logs`

Auditoría general.

## 11. Seguridad técnica

### Reglas obligatorias

- Las credenciales SQL externas deben ser de solo lectura.
- Toda consulta debe ser validada antes de ejecutarse.
- Nunca enviar credenciales al frontend.
- Nunca exponer errores crudos del motor SQL al usuario final.
- Usar rate limiting para IA y ejecución de consultas.
- Limitar tiempo máximo de consulta.
- Limitar cantidad máxima de filas.
- Auditar consultas bloqueadas.
- Cifrar secretos.

### Validación SQL

La validación debe considerar:

- Tipo de sentencia.
- Palabras prohibidas.
- Múltiples statements.
- Comentarios sospechosos.
- Acceso a tablas no permitidas.
- Uso de funciones potencialmente peligrosas.
- Límite obligatorio.

## 12. Prompt base para generación SQL

```text
Eres un asistente experto en SQL. Tu tarea es generar consultas SQL de solo lectura.

Reglas obligatorias:
- Solo puedes generar SELECT o WITH que termine en SELECT.
- No puedes generar INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, REPLACE, GRANT, REVOKE, EXEC ni CALL.
- Usa únicamente las tablas y columnas entregadas en el contexto.
- No inventes columnas ni relaciones.
- Agrega siempre un límite de resultados si la consulta no es agregada.
- Devuelve una respuesta estructurada con:
  - sql
  - explanation
  - tables_used
  - confidence
  - suggested_visualization

Contexto disponible:
{{schema_context}}

Pregunta del usuario:
{{user_question}}
```

## 13. Respuesta estructurada esperada desde IA

```json
{
  "sql": "SELECT ... LIMIT 100",
  "explanation": "Esta consulta obtiene...",
  "tables_used": ["customers", "orders"],
  "confidence": "high",
  "suggested_visualization": {
    "type": "table",
    "x_axis": null,
    "y_axis": null,
    "reason": "El resultado contiene datos detallados por fila."
  }
}
```

## 14. Interfaz de usuario propuesta con shadcn-vue

### Layout principal

- Sidebar con conexiones y navegación.
- Header con buscador global, usuario y acciones rápidas.
- Área central para exploración, SQL, resultados y visualizaciones.

### Componentes sugeridos

- `Button`
- `Card`
- `Dialog`
- `Sheet`
- `Sidebar`
- `Table`
- `Data Table`
- `Tabs`
- `Input`
- `Textarea`
- `Select`
- `Badge`
- `Dropdown Menu`
- `Toast / Sonner`
- `Skeleton`
- `Alert Dialog`
- `Command`
- `Resizable`

### Pantallas MVP

1. Login.
2. Dashboard.
3. Conexiones.
4. Crear / editar conexión.
5. Explorador de tablas.
6. Editor SQL.
7. Asistente IA.
8. Resultados.
9. Exportaciones.
10. Auditoría.
11. Administración de usuarios y permisos.

## 15. MVP recomendado

### Fase 1 — Base segura

- Autenticación.
- CRUD de conexiones.
- Prueba de conexión.
- Exploración de tablas.
- Vista previa limitada.
- Editor SQL solo lectura.
- Validador básico SQL.
- Auditoría de consultas.

### Fase 2 — IA

- Integración Laravel AI SDK.
- Generación SQL desde lenguaje natural.
- Respuesta estructurada.
- Validación post-IA.
- Explicación de consulta.
- Sugerencia de visualización.

### Fase 3 — Visualización y exportación

- Tabla avanzada.
- Exportación CSV.
- Exportación XLSX.
- Exportación JSON.
- Visualizaciones básicas.
- Consultas guardadas.

### Fase 4 — Permisos avanzados

- Restricción por tabla.
- Restricción por columna.
- Roles personalizados.
- Límites por usuario.
- Auditoría avanzada.

## 16. Rutas sugeridas

```text
GET    /dashboard
GET    /connections
POST   /connections
GET    /connections/{connection}
PUT    /connections/{connection}
DELETE /connections/{connection}
POST   /connections/{connection}/test

GET    /connections/{connection}/schemas
GET    /connections/{connection}/tables
GET    /connections/{connection}/tables/{table}
GET    /connections/{connection}/tables/{table}/preview

POST   /queries/validate
POST   /queries/execute
POST   /queries/ai-generate
GET    /queries/history
POST   /queries/save

POST   /exports
GET    /exports
GET    /exports/{export}/download

GET    /audit
```

## 17. API interna sugerida

### Generar SQL con IA

`POST /queries/ai-generate`

Request:

```json
{
  "connection_id": 1,
  "question": "Muéstrame las ventas del último mes agrupadas por día",
  "selected_tables": ["orders", "order_items"]
}
```

Response:

```json
{
  "sql": "SELECT DATE(created_at) AS day, SUM(total) AS total_sales FROM orders WHERE created_at >= CURRENT_DATE - INTERVAL '30 days' GROUP BY DATE(created_at) ORDER BY day LIMIT 100",
  "explanation": "Agrupa las ventas de los últimos 30 días por fecha.",
  "tables_used": ["orders"],
  "confidence": "high",
  "suggested_visualization": {
    "type": "line",
    "x_axis": "day",
    "y_axis": "total_sales"
  }
}
```

### Ejecutar consulta

`POST /queries/execute`

Request:

```json
{
  "connection_id": 1,
  "sql": "SELECT * FROM customers LIMIT 100"
}
```

Response:

```json
{
  "columns": [
    { "name": "id", "type": "integer" },
    { "name": "name", "type": "string" }
  ],
  "rows": [{ "id": 1, "name": "Cliente Demo" }],
  "meta": {
    "duration_ms": 42,
    "row_count": 1,
    "limited": true
  }
}
```

## 18. Componentes técnicos clave

### `QueryValidationService`

Responsabilidades:

- Detectar statements no permitidos.
- Validar que sea SELECT.
- Normalizar SQL.
- Agregar límite si corresponde.
- Identificar tablas usadas.
- Verificar permisos.

### `AiSqlAssistantService`

Responsabilidades:

- Construir contexto de esquema permitido.
- Enviar prompt al modelo IA.
- Solicitar respuesta estructurada.
- Validar formato de respuesta.
- Enviar SQL generado al validador.

### `ReadOnlyQueryExecutor`

Responsabilidades:

- Crear conexión dinámica.
- Ejecutar consulta con timeout.
- Aplicar límites.
- Normalizar resultados.
- Medir duración.
- Registrar auditoría.

### `ExportService`

Responsabilidades:

- Crear exportaciones.
- Procesar exportaciones grandes en background queue.
- Guardar archivos temporalmente.
- Controlar expiración.
- Registrar descargas.

## 19. Consideraciones para IA y Laravel AI SDK

MonitorSQL debe aprovechar **Laravel AI SDK** como la capa principal para construir la experiencia de IA del producto.

Laravel presenta el SDK como un paquete first-party para crear aplicaciones AI-native dentro del ecosistema Laravel, instalable con:

```bash
composer require laravel/ai
```

El SDK permite trabajar con agentes, herramientas, respuestas estructuradas, streaming, colas, embeddings, archivos, vector stores, testing y failover entre proveedores.

### Capacidades relevantes para MonitorSQL

#### Agentes especializados

Crear un agente dedicado, por ejemplo:

```bash
php artisan make:agent SqlQueryAssistant --structured
```

Este agente debe encargarse de transformar preguntas en lenguaje natural en SQL de solo lectura.

Responsabilidades del agente:

- Recibir la pregunta del usuario.
- Recibir solo el esquema permitido para ese usuario.
- Generar SQL seguro.
- Explicar la consulta.
- Declarar tablas usadas.
- Sugerir visualización.
- Devolver una salida estructurada.

#### Structured Output

La generación SQL debe usar salida estructurada, no texto libre.

Estructura sugerida:

```json
{
  "sql": "SELECT ... LIMIT 100",
  "explanation": "Descripción breve de lo que hace la consulta.",
  "tables_used": ["orders"],
  "confidence": "high",
  "suggested_visualization": {
    "type": "line",
    "x_axis": "created_at",
    "y_axis": "total"
  }
}
```

#### Tools del agente

MonitorSQL puede definir tools propias para que el agente consulte metadatos controlados, sin acceder directamente a la base de datos productiva.

Tools sugeridas:

- `GetAllowedSchemaTool`
- `FindTablesTool`
- `DescribeTableTool`
- `ValidateSqlTool`
- `SuggestVisualizationTool`

Regla importante: el agente no debe tener una tool que ejecute SQL directamente sobre la base externa. La ejecución debe quedar en `ReadOnlyQueryExecutor`, después de pasar por validación.

#### Streaming

El streaming puede usarse para mejorar la experiencia cuando la IA está explicando una consulta, generando pasos o analizando resultados.

Uso recomendado:

- Streaming para explicación y asistencia conversacional.
- No depender de streaming para ejecutar consultas SQL.

#### Queueing

Las tareas pesadas deben ir a cola:

- Generación de exportaciones grandes.
- Análisis de resultados grandes.
- Indexación de metadatos.
- Creación de embeddings sobre documentación interna del esquema.

#### Failover de proveedores

Configurar failover para mantener disponibilidad si un proveedor IA falla, tiene rate limits o latencia alta.

Ejemplo de estrategia:

- Proveedor primario: OpenAI.
- Proveedor secundario: Anthropic, Gemini, Groq u OpenRouter.
- Modelos más baratos para clasificación y validación.
- Modelos más potentes para generación SQL compleja.

#### Testing del flujo IA

Usar las herramientas de testing del SDK para simular agentes, outputs estructurados y herramientas.

Casos mínimos de prueba:

- Pregunta simple genera `SELECT` válido.
- Pregunta de escritura genera rechazo.
- Pregunta sobre tabla no permitida genera rechazo.
- Output inválido no se ejecuta.
- Consulta sin `LIMIT` recibe límite automático.
- IA no inventa columnas fuera del esquema.

### Diseño recomendado del agente SQL

Nombre sugerido:

```text
App/Ai/Agents/SqlQueryAssistant
```

Interfaces sugeridas:

- Agent
- HasStructuredOutput
- HasTools
- Opcionalmente Conversational, si se desea mantener contexto por sesión

Instrucciones base:

```text
Eres un asistente experto en SQL de solo lectura para MonitorSQL.

Tu objetivo es ayudar al usuario a consultar datos de forma segura.

Reglas obligatorias:
- Solo puedes generar SELECT o WITH que termine en SELECT.
- Nunca generes INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, REPLACE, GRANT, REVOKE, EXEC ni CALL.
- Usa únicamente tablas y columnas del contexto autorizado.
- No inventes columnas, tablas ni relaciones.
- Si falta información, pide aclaración o responde que no puedes generar una consulta segura.
- Agrega LIMIT cuando la consulta devuelva filas detalladas.
- Devuelve siempre salida estructurada.
```

### Arquitectura IA actualizada

```text
Usuario pregunta
        ↓
Controller / Action
        ↓
SchemaPermissionService obtiene esquema autorizado
        ↓
SqlQueryAssistant genera respuesta estructurada
        ↓
QueryValidationService valida SQL
        ↓
Frontend muestra SQL + explicación
        ↓
Usuario confirma ejecución
        ↓
ReadOnlyQueryExecutor ejecuta consulta
        ↓
AuditService registra evento
        ↓
ResultVisualizationService sugiere tabla/gráfico/exportación
```

### Variables de entorno IA sugeridas

```env
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
OPENROUTER_API_KEY=
AI_DEFAULT_PROVIDER=openai
AI_DEFAULT_MODEL=
AI_SQL_TIMEOUT=60
AI_SQL_MAX_SCHEMA_TOKENS=12000
```

### Reglas de seguridad específicas para IA

La IA debe usarse como asistente, no como autoridad final.

Buenas prácticas:

- Pasar solo metadatos autorizados.
- Evitar enviar datos sensibles como muestra.
- Pedir salida estructurada.
- Validar todo SQL generado.
- Registrar prompts y respuestas de forma segura, evitando guardar datos sensibles innecesarios.
- Mostrar confianza de la respuesta.
- Permitir al usuario editar el SQL antes de ejecutarlo.
- Separar generación SQL de ejecución SQL.
- No entregar credenciales ni conexión directa al agente.

## 20. Riesgos principales

| Riesgo                                          | Mitigación                                  |
| ----------------------------------------------- | ------------------------------------------- |
| La IA genera SQL peligroso                      | Validador estricto + credenciales read-only |
| Usuario accede a tablas no autorizadas          | Permisos por conexión/tabla/columna         |
| Consultas muy pesadas                           | Timeout, LIMIT, paginación, colas           |
| Exposición de credenciales                      | Cifrado, nunca enviar al frontend           |
| Exportación masiva no controlada                | Límites por rol y auditoría                 |
| Error del motor SQL expone información sensible | Sanitizar errores                           |

## 21. Roadmap sugerido

### Versión 0.1

- Autenticación.
- Crear conexión MySQL/PostgreSQL.
- Explorar tablas.
- Ejecutar SELECT manual.
- Mostrar resultados en tabla.

### Versión 0.2

- Validación SQL robusta.
- Auditoría básica.
- Exportación CSV.
- Historial de consultas.

### Versión 0.3

- Integración IA.
- Generación SQL.
- Explicación de consultas.
- Consultas guardadas.

### Versión 0.4

- Visualizaciones.
- Exportación XLSX/JSON.
- Permisos por tabla.

### Versión 1.0

- Permisos avanzados por columna.
- Auditoría completa.
- Dashboards.
- Soporte multi-conexión estable.
- Hardening de seguridad.

## 22. Definición de éxito del MVP

El MVP será exitoso si permite que un usuario autorizado pueda:

1. Iniciar sesión.
2. Seleccionar una conexión SQL.
3. Explorar tablas permitidas.
4. Hacer una consulta SELECT segura.
5. Preguntar algo en lenguaje natural y recibir SQL válido.
6. Ver resultados en tabla.
7. Exportar los resultados.
8. Tener la acción registrada en auditoría.

## 23. Próximos entregables recomendados

1. Documento técnico de arquitectura.
2. Diagrama entidad-relación.
3. Backlog de historias de usuario.
4. Diseño de pantallas principales.
5. Especificación del validador SQL.
6. Prompt engineering inicial para IA.
7. Plan de seguridad y auditoría.
8. Plan de desarrollo por sprint.
