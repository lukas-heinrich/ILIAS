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

use ILIAS\Questions\Question\Persistence\ManipulateQuery;
use ILIAS\Data\UUID\Uuid;

class AnswerOption
{
    public function __construct(
        private readonly ?Uuid $answer_option_id = null,
        private readonly ?int $position = null,
        private readonly string|float $value = '',
        private readonly ?float $available_points = null
    ) {
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function getValue(): string|float|null
    {
        return $this->value;
    }

    public function getAvailablePoints(): ?float
    {
        return $this->available_points;
    }

    public function toPersistence(ManipulateQuery $query): ManipulateQuery
    {

    }
}
