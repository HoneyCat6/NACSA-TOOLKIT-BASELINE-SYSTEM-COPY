<?php

namespace App\DataObjects;

use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;

final readonly class ScoredElement
{
    /**
     * @param  list<?bool>  $responses
     */
    public function __construct(
        public int $ncsbElementId,
        public string $code,
        public Domain $domain,
        public int $yesCount,
        public ?MaturityLevel $level,
        public ElementStatus $status,
        public array $responses,
    ) {}
}
