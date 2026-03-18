<?php

namespace ILIAS\Questions\ExportImport\Foundation\Normalizing\Normalizer;

use ILIAS\Questions\ExportImport\Foundation\Contracts\Normalizer;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Attributes\Normalizes;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\NormalizingException;
use ILIAS\Questions\ExportImport\Foundation\Objects\IdContainer;

/**
 * @implements Normalizer<IdContainer, array>
 */
#[Normalizes(IdContainer::class)]
class IdContainerNormalizer implements Normalizer
{
    public function __construct(
        private readonly Transformations $tt
    ) {
    }

    /**
     * @inheritDoc
     */
    public function normalize($value): array|float|bool|int|string|null
    {
        if ($value instanceof IdContainer) {
            if (is_object($value->getId())) {
                return [
                    'id' => $this->tt->normalize($value->getId()),
                    'type' => get_class($value->getId()),
                    'object' => $value->getObject(),
                ];
            } else {
                return [
                    'id' => (string) $value->getId(),
                    'type' => gettype($value->getId()),
                    'object' => $value->getObject(),
                ];
            }
        }

        throw new NormalizingException('Invalid value', $value);
    }

    /**
     * @inheritDoc
     */
    public function denormalize(array|float|bool|int|string|null $value, string $type): IdContainer
    {
        $raw_id = $value['id'];
        $type = $value['type'];

        if (class_exists($type)) {
            return new IdContainer($this->tt->denormalize($raw_id, $type), $value['object']);
        } else {
            $id = match($type) {
                'integer' => (int) $raw_id,
                'string' => (string) $raw_id,
                'float' => (float) $raw_id,
                'bool' => (bool) $raw_id,
                'null' => null,
                default => throw new NormalizingException("Invalid type for id: {$type}")
            };
            return new IdContainer($id, $value['object']);
        }
    }
}
