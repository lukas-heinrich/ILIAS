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

interface Type
{
    public function getIdentifier(): string;
    public function withData(Data $data): self;
    public function getEditInputs(
        Language $lng,
        FieldFactory $ff,
        Refinery $refinery
    ): array;

    public function getEditSectionConstraint(
        Refinery $refinery,
        Language $lng
    ): ?Constraint;

    public function getBuildGapTransformation(
        Refinery $refinery,
        Uuid $answer_input_id
    ): Transformation;

    public function addValuesToData(Data $data): Data;

    public function getAnswerInput(): \ilFormPropertyGUI;
}
