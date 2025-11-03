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

class Numeric implements Type
{
    private float $step_size = 0.0001;
    private ?float $lower_limit = null;
    private ?float $upper_limit = null;

    public function getIdentifier(): string
    {
        return 'numeric';
    }

    public function withData(Data $data): Type
    {
        $clone = clone $this;
        $clone->lower_limit = $data->getLowerLimit();
        $clone->upper_limit = $data->getUpperLimit();
        if ($data->getStepSize() !== null) {
            return $this->step_size = $data->getStepSize();
        }
        return $clone;
    }

    public function getEditInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): array {
        return [
            'lower_limit' => $ff->numeric('lower_limit')
                ->withStepSize($this->step_size)
                ->withRequired(true)
                ->withValue($this->lower_limit),
            'upper_limit' => $ff->numeric('upper_limit')
                ->withStepSize(0.00000001)
                ->withValue($this->upper_limit),
            'step_size' => $ff->numeric('step_size')
                ->withStepSize($this->step_size)
                ->withRequired(true)
                ->withValue($this->step_size)
        ];
    }

    public function getEditSectionConstraint(
        Refinery $refinery,
        Language $lng
    ): ?Constraint {
        return $refinery->custom()->constraint(
            fn(array $v): bool => $vs['upper_limit'] === null
                || $vs['upper_limit'] >= $vs['lower_limit']
                    && $vs['upper_limit'] >= $vs['step_size'],
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
                $vs['lower_limit'],
                $vs['upper_limit'],
                $vs['step_size']
            )
        );
    }

    public function addValuesToData(Data $data): Data
    {
        return $data->withLowerLimit($this->lower_limit)
            ->withUpperLimit($this->upper_limit)
            ->withStepSize($this->step_size);
    }

    public function getAnswerInput(): \ilFormPropertyGUI
    {
        ;
    }
}
