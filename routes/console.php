<?php

declare(strict_types=1);

/* @chisel-notifications */
use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:prune')->daily();
/* @end-chisel-notifications */

/* @chisel-audit-trails */
Schedule::command('audit:prune')->daily();
/* @end-chisel-audit-trails */
