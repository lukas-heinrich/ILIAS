<?php

namespace ILIAS\Questions\ExportImport\Foundation\Normalizing;

use ILIAS\Questions\ExportImport\Foundation\Contracts\Pipe;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Pipeline;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Pipes\DenormalizeCarry;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Pipes\NormalizeCarry;
use ILIAS\Refinery\Custom\Group;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations as TransformationsContract;
use InvalidArgumentException;

/**
 * Provides a set of transformations for normalizing and denormalizing values. It uses the Refinery library to perform
 * the transformations. It also provides a registry of normalizers, which are used to handle the normalization and
 * denormalization of complex objects.
 */
class Transformations implements TransformationsContract
{
    public function __construct(
        protected readonly Refinery $refinery,
        protected readonly Pipeline $pipeline
    ) {
    }

    /*
        Normalization/Denormalization
    */

    /**
     * @inheritDoc
     */
    public function normalize(mixed $value): array|float|bool|int|string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        return $this->pipeline->send(new NormalizeCarry($this, $value))
            ->then(fn(NormalizeCarry $carry) => $carry->result());
    }

    /**
     * @inheritDoc
     */
    public function denormalize(array|float|bool|int|string|null $normalized, string|object $expected): mixed
    {
        if ($normalized === null) {
            return null;
        }

        return $this->pipeline->send(new DenormalizeCarry($this, $normalized, $expected))
            ->then(fn(DenormalizeCarry $carry) => $carry->result());
    }

    /**
     * Get a pipe from the pipeline.
     *
     * @template T of Pipe
     * @param class-string<T> $pipe
     * @return T
     *
     * @throws NormalizingException if the pipe is not found
     */
    public function context(string $pipe): Pipe
    {
        foreach ($this->pipeline->pipes() as $pipe) {
            if ($pipe instanceof $pipe) {
                return $pipe;
            }
        }
        throw new NormalizingException("Pipe {$pipe} not found");
    }

    /*
        Transformations
    */

    public function custom(): Group
    {
        return $this->refinery->custom();
    }

    /**
     * @throws InvalidArgumentException if the value cannot be transformed into an integer
     */
    public function int(mixed $value): int
    {
        return $this->refinery->kindlyTo()->int()->transform($value);
    }

    /**
     * @throws InvalidArgumentException if the value cannot be transformed into a float
     */
    public function float(mixed $value): float
    {
        return $this->refinery->kindlyTo()->float()->transform($value);
    }

    /**
     * @throws InvalidArgumentException if the value cannot be transformed into a string
     */
    public function string(mixed $value): string
    {
        return $this->refinery->kindlyTo()->string()->transform($value);
    }

    /**
     * @throws InvalidArgumentException if the value cannot be transformed into a boolean
     */
    public function bool(mixed $value): bool
    {
        return $this->refinery->kindlyTo()->bool()->transform($value);
    }

    public function nullableInt(mixed $value): ?int
    {
        return $this->refinery->byTrying([
            $this->refinery->kindlyTo()->int(),
            $this->refinery->always(null)
        ])->transform($value);
    }

    public function nullableFloat(mixed $value): ?float
    {
        return $this->refinery->byTrying([
            $this->refinery->kindlyTo()->float(),
            $this->refinery->always(null)
        ])->transform($value);
    }

    public function nullableString(mixed $value): ?string
    {
        return $this->refinery->byTrying([
            $this->refinery->kindlyTo()->string(),
            $this->refinery->always(null)
        ])->transform($value);
    }

    public function nullableBool(mixed $value): ?bool
    {
        return $this->refinery->byTrying([
            $this->refinery->kindlyTo()->bool(),
            $this->refinery->always(null)
        ])->transform($value);
    }
}
