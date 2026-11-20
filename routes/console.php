<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/* @chisel-notifications */
Schedule::command('notifications:prune')->daily()->withoutOverlapping()->onOneServer();
/* @end-chisel-notifications */

/* @chisel-audit-trails */
Schedule::command('audit:prune')->daily()->withoutOverlapping()->onOneServer();
/* @end-chisel-audit-trails */
