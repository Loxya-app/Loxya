<?php
declare(strict_types=1);

namespace Robert2\API\Models;

use Robert2\API\Validation\Validator as V;

class Attribute extends BaseModel
{
    protected $orderField = 'id';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->validation = [
            'name' => V::notEmpty()->alpha(self::EXTRA_CHARS)->length(2, 64),
            'type' => V::notEmpty()->oneOf(
                v::equals('string'),
                v::equals('integer'),
                v::equals('float'),
                v::equals('boolean')
            ),
            'unit'       => V::optional(V::length(1, 8)),
            'max_length' => V::optional(V::numeric()),
        ];
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Relations
    // —
    // ——————————————————————————————————————————————————————

    public function Materials()
    {
        return $this->belongsToMany('Robert2\API\Models\Material', 'material_attributes')
            ->using('Robert2\API\Models\MaterialAttributesPivot')
            ->withPivot('value')
            ->select(['materials.id', 'name']);
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Mutators
    // —
    // ——————————————————————————————————————————————————————

    protected $casts = [
        'name'       => 'string',
        'type'       => 'string',
        'unit'       => 'string',
        'max_length' => 'integer',
    ];

    public function getMaterialsAttribute()
    {
        $materials = $this->Materials()->get();
        return $materials ? $materials->toArray() : null;
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Setters
    // —
    // ——————————————————————————————————————————————————————

    protected $fillable = [
        'name',
        'type',
        'unit',
        'max_length',
    ];
}
