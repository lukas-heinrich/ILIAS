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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps;

use ILIAS\Questions\Question\Definitions\TextMatchingOptions;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;

class Text implements Type
{
    private ?int $max_chars = null;
    private TextMatchingOptions $text_matching_method = TextMatchingOptions::CaseInsensitive;

    public function getIdentifier(): string
    {
        return 'text';
    }

    public function withData(Data $data): self
    {
        $clone = clone $this;
        $clone->max_chars = $data->getMaxChars();
        if ($data->getTextMatchingMethod() !== null) {
            return $clone->text_matching_method = $data->getTextMatchingMethod();
        }
        return $clone;
    }

    public function getEditInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): array {
        return [
            'answer_options' => $ff->tag(
                $lng->txt('answer_options'),
                []
            )->withRequired(true)
            ->withValue($this->buildTagsArrayFromAnswerOptions($this->answer_options)),
            'matching_method' => $ff->select(
                $lng->txt('matching_method'),
                TextMatchingOptions::buildOptionsList($lng)
            )->withRequired(true)
            ->withValue($this->text_matching_method->value),
            'max_chars' => $ff->numeric(
                $lng->txt('max_chars'),
            )->withValue($this->max_chars)
        ];
    }

    public function getEditSectionConstraint(
        Refinery $refinery,
        Language $lng
    ): ?Constraint {
        return null;
    }

    public function getBuildGapTransformation(
        Refinery $refinery,
        Uuid $answer_input_id
    ): Transformation {
        return $refinery->custom()->transformation(
            fn(array $vs): self => new self(
                $answer_input_id,
                $vs['max_chars'],
                $vs['matching_method'],
                $vs['answer_options']
            )
        );
    }

    public function addValuesToData(Data $data): Data
    {
        return $data->withMaxChars($this->max_chars)
            ->withTextMatchingMethods($this->text_matching_method);
    }

    public function getAnswerInput(): \ilFormPropertyGUI
    {
        ;
    }
}
