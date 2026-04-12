<?php

declare(strict_types=1);

namespace Minoo\Entity;

use Waaseyaa\Entity\ConfigEntityBase;

final class DialectRegion extends ConfigEntityBase
{
    protected string $entityTypeId = 'dialect_region';

    protected array $entityKeys = ['id' => 'code', 'label' => 'name'];

    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
    ) {
        if (!array_key_exists('display_name', $values)) {
            $values['display_name'] = '';
        }
        if (!array_key_exists('language_family', $values)) {
            $values['language_family'] = '';
        }
        if (!array_key_exists('iso_639_3', $values)) {
            $values['iso_639_3'] = '';
        }
        if (!array_key_exists('regions', $values)) {
            $values['regions'] = [];
        }
        if (!array_key_exists('boundary_geojson', $values)) {
            $values['boundary_geojson'] = null;
        }

        parent::__construct(
            $values,
            $entityTypeId ?: $this->entityTypeId,
            $entityKeys ?: $this->entityKeys,
        );
    }
}
