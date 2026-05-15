<?php

return [
    'max_rows' => (int) env('MONITOR_SQL_MAX_ROWS', 1000),
    'query_timeout_seconds' => (int) env('MONITOR_SQL_QUERY_TIMEOUT_SECONDS', 30),
    'export_max_rows' => (int) env('MONITOR_SQL_EXPORT_MAX_ROWS', 10000),
    'export_expiration_minutes' => (int) env('MONITOR_SQL_EXPORT_EXPIRATION_MINUTES', 60),

    'ai' => [
        'provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
        'model' => env('AI_DEFAULT_MODEL', 'gpt-4o'),
        'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'anthropic'),
        'fallback_model' => env('AI_FALLBACK_MODEL'),
        'sql_timeout' => (int) env('AI_SQL_TIMEOUT', 60),
        'max_schema_tokens' => (int) env('AI_SQL_MAX_SCHEMA_TOKENS', 12000),
        'temperature' => (float) env('AI_TEMPERATURE', 0.1),
    ],

    'rate_limits' => [
        'query_execute_per_minute' => (int) env('MONITOR_SQL_RATE_QUERY_EXECUTE', 20),
        'query_ai_generate_per_minute' => (int) env('MONITOR_SQL_RATE_QUERY_AI_GENERATE', 15),
        'query_validate_per_minute' => (int) env('MONITOR_SQL_RATE_QUERY_VALIDATE', 30),
    ],
];
