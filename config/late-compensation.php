<?php

return [
    'enabled' => env('LATE_COMPENSATION_ENABLED', false),
    'preview' => env('LATE_COMPENSATION_PREVIEW', false),

    // Map production sources to excluded DueType values, or use '*' for every type.
    'excluded_due_types' => [],
];
