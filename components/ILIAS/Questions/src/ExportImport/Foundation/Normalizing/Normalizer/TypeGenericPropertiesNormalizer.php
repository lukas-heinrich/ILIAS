<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Questions\ExportImport\Foundation\Normalizing\Normalizer;

use ILIAS\Data\UUID\Uuid;
use ILIAS\Questions\AnswerForm\Definition;
use ILIAS\Questions\AnswerForm\Factory;
use ILIAS\Questions\AnswerForm\TypeGenericProperties;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Normalizer;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Attributes\Normalizes;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\NormalizingException;

/**
 * @implements Normalizer<TypeGenericProperties, array>
 */
#[Normalizes(TypeGenericProperties::class)]
class TypeGenericPropertiesNormalizer implements Normalizer
{
    public function __construct(
        private readonly Transformations $tt,
        private readonly Factory $factory
    ) {
    }
    /**
     * @inheritDoc
     */
    public function normalize($value): array|float|bool|int|string|null
    {
        if ($value instanceof TypeGenericProperties) {
            return [
                'answer_form_id' => $this->tt->normalize($value->getAnswerFormId()),
                'question_id' => $this->tt->normalize($value->getQuestionId()),
                'definition' => $this->normalizeDefinition($value->getDefinition()),
                'available_points' => $value->getAvailablePoints(),
                'image_size' => $value->getImageSize(),
                'shuffle_answer_options' => $value->getShuffleAnswerOptions(),
                'additional_text' => $value->getAdditionalText(),
                'additional_text_legacy' => $value->getAdditionalTextLegacy(),
            ];
        }

        throw new NormalizingException('Invalid definition value', $value);
    }

    /**
     * @inheritDoc
     */
    public function denormalize(array|float|bool|int|string|null $value, string $type): TypeGenericProperties
    {
        if (!is_array($value)) {
            throw new NormalizingException('Invalid type generic properties value', $value);
        }

        return new TypeGenericProperties(
            $this->tt->denormalize($value['answer_form_id'], Uuid::class),
            $this->tt->denormalize($value['question_id'], Uuid::class),
            $this->denormalizeDefinition($value['definition']),
            $this->tt->nullableFloat($value['available_points']),
            $this->tt->nullableInt($value['image_size']),
            $this->tt->nullableBool($value['shuffle_answer_options']),
            $this->tt->string($value['additional_text']),
            $this->tt->string($value['additional_text_legacy']),
        );
    }

    private function normalizeDefinition(Definition $definition): ?string
    {
        return $definition !== null ? $definition::class : null;
    }

    private function denormalizeDefinition(string $definition): ?Definition
    {
        return $definition !== null ? $this->factory->getDefinitionForClass($definition) : null;
    }
}
