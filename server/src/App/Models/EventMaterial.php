<?php
declare(strict_types=1);

namespace Robert2\API\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EventMaterial extends Pivot
{
    public $incrementing = true;

    // ——————————————————————————————————————————————————————
    // —
    // —    Mutators
    // —
    // ——————————————————————————————————————————————————————

    protected $casts = [
        'event_id'    => 'integer',
        'material_id' => 'integer',
        'quantity'    => 'integer',
    ];
}
