# MonitorSQL

**Explorador SQL inteligente con IA — acceso de solo lectura, seguro y visual a tus bases de datos.**

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)](https://laravel.com)
[![Vue](https://img.shields.io/badge/Vue-3.x-4FC08D?logo=vue.js)](https://vuejs.org)
[![Tailwind](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/license-CC%20BY–NC--SA%204.0-lightgrey)](LICENSE.md)

---

## Acerca de MonitorSQL

MonitorSQL es una plataforma web que permite a usuarios autorizados explorar bases de datos SQL de forma **solo lectura**, consultar información usando lenguaje natural asistido por **inteligencia artificial**, visualizar resultados con gráficos y exportar datos — todo sin exponer permisos de escritura.

<p align="center">
  <img width="100%"  alt="image" src="https://github.com/user-attachments/assets/e4faa364-d571-431c-bb24-9cc6bdc82ea5" />
</p>

### ¿Por qué MonitorSQL?

- Equipos de negocio y analistas necesitan consultar datos sin depender de ingenieros.
- Compartir credenciales de base de datos es inseguro.
- Las consultas directas pueden romper la base de datos accidentalmente.
- No existe una capa de auditoría sobre quién consulta qué.

MonitorSQL resuelve esto con un gateway de solo lectura, control de acceso granular y un asistente de IA que traduce preguntas en español a SQL seguro.

---

## Características principales

### Asistente de IA para SQL
- Traduce preguntas en lenguaje natural a consultas SQL seguras.
- **Arquitectura Dual-Pass**: primero selecciona tablas, luego genera SQL — elimina alucinaciones de tablas inexistentes.
- **Memoria de largo plazo**: aprende términos de negocio, tablas preferidas y patrones exitosos.
- **Memoria de corto plazo**: conversaciones multi-turno con contexto.
- **Validación automática**: solo SELECT, LIMIT obligatorio, verificación de dialecto (MySQL/PostgreSQL).
- Soporte para **7+ proveedores** de IA: OpenAI, Anthropic, DeepSeek, Groq, Mistral, Gemini, Cerebras y más.
- **Control de temperatura 0.1** para reducir alucinaciones.
- **Resumen automático** de resultados con IA.

### Explorador de bases de datos
- Conexión a **MySQL/MariaDB y PostgreSQL** en modo solo lectura.
- Exploración de esquemas, tablas, columnas, tipos de datos y llaves foráneas.
- Vista previa de datos con paginación.
- Editor SQL con autocompletado básico y validación en tiempo real.

### Control de acceso granular (`app/AccessControlController.php`)
- Roles y permisos personalizables por conexión, esquema y tabla.
- Límites por usuario: máximo de filas, consultas por hora, exportaciones por día.
- Permisos individuales: `queries.execute`, `queries.ai_generate`, `connections.view`, `audit.view`, etc.

### Visualización de datos
- Tablas interactivas (TanStack Table).
- Gráficos: barras, líneas, donut, KPIs (Chart.js).
- Sugerencias automáticas de tipo de gráfico desde la IA.

### Exportación de datos
- Formatos: **CSV, Excel, JSON**.
- Exportaciones en background vía Laravel Queues para grandes volúmenes.
- Límites configurables por usuario y caducidad automática de archivos.

### Auditoría y seguridad
- Registro de cada consulta ejecutada: SQL, duración, filas retornadas, IP.
- Bloqueo automático de consultas no válidas.
- Autenticación con **Laravel Fortify** (2FA, verificación de email, recuperación de contraseña).
- API keys de proveedores IA encriptadas en base de datos.

### Historial y favoritos
- Historial completo de consultas ejecutadas con tags y notas.
- Consultas guardadas como favoritos organizadas por categorías.
- Búsqueda y filtrado del historial.

### Monitoreo de conexiones
- Test de conexión en vivo.
- Métricas de estado: última prueba, tiempo de respuesta.
- Backups y snapshots de esquema.

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| **Backend** | PHP 8.3+, Laravel 13, Laravel AI SDK, Laravel Fortify |
| **Frontend** | Vue 3, Inertia.js v3, TypeScript |
| **UI** | Tailwind CSS v4, shadcn-vue (reka-ui), Lucide icons |
| **Visualización** | Chart.js, vue-chartjs |
| **Base de datos interna** | SQLite (dev), PostgreSQL o MySQL (producción) |
| **Bases de datos externas** | MySQL, MariaDB, PostgreSQL |
| **IA** | OpenAI, Anthropic (Claude), DeepSeek, Groq, Mistral, Gemini, Cerebras, xAI, OpenRouter |
| **Colas** | Laravel Queues (database/redis) |
| **Testing** | PHPUnit 12, 73 tests |

---

## Requisitos

- **PHP 8.3** o superior
- **Composer** 2.x
- **Node.js** 20+ y **pnpm** (o npm)
- Base de datos **SQLite**, **MySQL 8** o **PostgreSQL 15+** para los datos internos de MonitorSQL
- Extensiones PHP: `pdo_mysql`, `pdo_pgsql`, `mbstring`, `json`, `xml`

---

## Instalación rápida

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/monitorsql.git
cd monitorsql

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias frontend
pnpm install  # o npm install

# 4. Configurar entorno
cp .env.example .env
php artisan key:generate

# 5. Configurar base de datos interna en .env
# Por defecto usa SQLite (database/database.sqlite)
touch database/database.sqlite

# 6. Ejecutar migraciones y seeders
php artisan migrate --seed

# 7. Iniciar el servidor de desarrollo
composer run dev
```

El comando `composer run dev` inicia simultáneamente:
- Servidor PHP Artisan (`php artisan serve`)
- Servidor Vite para assets frontend
- Worker de colas para exportaciones

Abre `http://localhost:8000` en tu navegador.

### Usuario administrador por defecto

| Campo | Valor |
|-------|-------|
| Email | `admin@monitorsql.com` |
| Password | La que definas en `DatabaseSeeder` |

---

## Configuración de proveedores IA

MonitorSQL soporta múltiples proveedores de IA. Configura al menos uno en tu `.env`:

```env
# Proveedor principal
AI_DEFAULT_PROVIDER=openai
AI_DEFAULT_MODEL=gpt-4.1-mini

# Fallback (opcional)
AI_FALLBACK_PROVIDER=anthropic
AI_FALLBACK_MODEL=

# Temperatura (0.0-1.0) — menor = menos alucinaciones
AI_TEMPERATURE=0.1

# Claves API
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

También puedes configurar proveedores desde la UI en `/settings/ai-providers`.

---

## Conexión a bases de datos externas

1. Ve a **Conexiones** en el panel lateral.
2. Haz clic en **Nueva conexión**.
3. Completa los datos de tu base de datos MySQL o PostgreSQL.
4. Usa credenciales con permisos de **solo lectura** (recomendado: `SELECT` únicamente).
5. Haz clic en **Probar conexión**.
6. Una vez verificada, la conexión estará disponible para todos los usuarios autorizados.

---

## Arquitectura de seguridad

```
Usuario → Autenticación (Fortify/2FA)
       → Autorización (Roles/Permisos)
       → Gateway solo lectura
       → Validación SQL (QueryValidationService)
       → Base de datos externa (SELECT only)
       → Auditoría (AuditLog)
```

Cada consulta pasa por un pipeline de validación que:
1. Normaliza y limpia comentarios SQL.
2. Rechaza sentencias que no sean SELECT o WITH.
3. Bloquea tokens peligrosos (INSERT, UPDATE, DELETE, DROP, etc.).
4. Verifica que todas las tablas referenciadas estén en la allowlist.
5. Valida compatibilidad de dialecto (no ILIKE en MySQL, etc.).
6. Añade LIMIT automático si no está presente.
7. Genera hash SHA-256 para auditoría.

---

## Funcionalidades de la IA

### Dual-Pass Anti-Halucinaciones

El sistema usa dos agentes de IA en secuencia:

1. **TableSelectorAgent** — Dada la pregunta y las tablas permitidas, selecciona las tablas correctas. Si no hay match, responde con sugerencias en vez de inventar.
2. **SqlQueryAssistant** — Recibe solo las tablas confirmadas + su schema real, y genera SQL seguro. No puede alucinar tablas porque solo conoce las verificadas.

### Memoria adaptativa

- **Aliases de términos**: "contactos" → `contacts`, "ventas" → `orders`
- **Tablas preferidas**: aprende qué tablas usas más
- **Patrones analíticos**: CTEs, window functions, agregaciones
- **Tracking de alucinaciones**: registra tablas inventadas para advertir en futuros prompts

### Sistema de prompts editable

El system prompt de la IA es editable desde la UI (`/settings/system-prompt`) y soporta:
- Reglas de prioridad P0/P1/P2
- Guardrails anti-alucinaciones
- Ejemplos few-shot
- Adaptación automática al dialecto

---

## Permisos del sistema

| Permiso | Descripción |
|---------|------------|
| `connections.view` | Ver lista de conexiones |
| `connections.create` | Crear/editar conexiones (admin) |
| `schemas.view` | Explorar esquemas de base de datos |
| `tables.view` | Ver tablas y columnas |
| `queries.execute` | Ejecutar consultas SQL |
| `queries.ai_generate` | Usar el asistente de IA |
| `queries.export` | Exportar datos |
| `audit.view` | Ver registros de auditoría |

---

## Testing

```bash
# Todos los tests
php artisan test --compact

# Tests específicos
php artisan test --compact --filter=testName

# Solo un archivo
php artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php
```

El proyecto incluye **73 tests** con **329 assertions** cubriendo autenticación, permisos, validación SQL y más.

---

## Roadmap

- [x] Conexiones MySQL/PostgreSQL
- [x] Asistente IA con múltiples proveedores
- [x] Control de acceso RBAC
- [x] Auditoría completa
- [x] Exportación CSV/Excel/JSON
- [x] Historial y favoritos
- [x] Memoria IA de largo plazo
- [x] Arquitectura Dual-Pass anti-alucinaciones
- [ ] Dashboards personalizables
- [ ] Alertas y notificaciones
- [ ] API REST pública
- [ ] Soporte para SQL Server y Oracle
- [ ] Editor SQL avanzado con Monaco Editor

---

## Licencia

Este proyecto es **open source para uso no comercial** bajo la licencia [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International](https://creativecommons.org/licenses/by-nc-sa/4.0/) (CC BY-NC-SA 4.0).

**No está permitido el uso comercial sin autorización expresa.** Si deseas usar MonitorSQL en un entorno comercial, por favor contacta al autor para obtener una licencia comercial.

---

## Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz fork del repositorio.
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`).
3. Escribe tests para tus cambios.
4. Asegúrate de que todos los tests pasen (`php artisan test --compact`).
5. Ejecuta el formateador (`vendor/bin/pint --format agent`).
6. Envía un Pull Request.

---

## Autor

Creado por [Tu Nombre](https://github.com/tu-usuario). MonitorSQL nació de la necesidad de dar acceso seguro a datos sin exponer la base de datos a modificaciones accidentales.

---

<p align="center">
  <sub>Hecho con Laravel, Vue y mucho cuidado por la seguridad de tus datos.</sub>
</p>
