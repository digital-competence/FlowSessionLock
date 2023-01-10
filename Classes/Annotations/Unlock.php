<?php

declare(strict_types=1);

namespace DigiComp\FlowSessionLock\Annotations;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Unlock
{
}
