<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitorsql:clean-expired-exports')->hourly();
Schedule::command('monitorsql:prune-ai-memory --days=30')->daily();
