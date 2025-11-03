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

use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Refinery\Constraint;
use ILIAS\Refinery\Transformation;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;

class LongMenu implements Type
{
    private int $min_autocomplete = 3;

    public function getIdentifier(): string
    {
        return 'long_menu';
    }

    public function withData(Data $data): self
    {
        if ($data->getMinAutocomplete() === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->min_autocomplete = $data->getMinAutocomplete();
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
            )->withValue($this->buildTagsArrayFromAnswerOptions($this->answer_options)),
            'min_autocomplete' => $ff->numeric(
                $lng->txt('min_autocomplete')
            )->withRequired(true)
            ->withValue($this->min_autocomplete),
            'options_awarding_points' => $ff->tag(
                $lng->txt('answer_options'),
                []
            )->withValue(
                array_reduce(
                    $this->answer_options,
                    static function (array $c, AnswerOption $v): array {
                        if ($v->getAvailablePoints() > 0.0) {
                            $c[] = $v->getValue();
                        }
                        return $c;
                    },
                    []
                )
            )
        ];
    }

    public function getEditSectionConstraint(
        Refinery $refinery,
        Language $lng
    ): ?Constraint {
        return $refinery->custom()->constraint(
            fn(array $v): bool => $vs['answer_options'] !== [],
            $lng->txt('error')
        );
    }

    public function getBuildGapTransformation(
        Refinery $refinery,
        Uuid $answer_input_id
    ): Transformation {
        return $refinery->custom()->transformation(
            fn(array $vs): self => new self(
                $answer_input_id,
                $vs['min_autocomplete'],
                $vs['answer_options']
            )
        );
    }

    public function addValuesToData(Data $data): Data
    {
        return $data->withMinAutocomplete($this->min_autocomplete);
    }

    public function getAnswerInput(): \ilFormPropertyGUI
    {
        ;
    }
}
