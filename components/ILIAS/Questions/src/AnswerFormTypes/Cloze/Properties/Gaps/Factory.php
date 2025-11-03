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

use ILIAS\Language\Language;
use ILIAS\Data\UUID\Factory as UuidFactory;

class Factory
{
    private array $available_gap_types;

    public function __construct(
        private readonly UuidFactory $uuid_factory,
        array $available_gap_types
    ) {
        foreach ($available_gap_types as $type) {
            $this->available_gap_types[$type->getIdentifier()] = $type;
        }
    }

    public function getAvailableGapTypesOptionsArray(
        Language $lng
    ): array {
        return array_map(
            fn(string $v) => $lng->txt("{$v}_gap"),
            array_keys($this->available_gap_type_classes)
        );
    }

    public function getNewGap(): ?Undefined
    {
        return new Gap(
            $this->uuid_factory->uuid4()
        );
    }

    public function getGapOfType(
        string $type_identifier,
        string $answer_input_id
    ): ?Gap {
        $class = $this->available_gap_type_classes[$type_identifier];
        if ($class === null) {
            return $class;
        }
        return (new $class())->withAnswerInputId(
            $this->uuid_factory->fromString($answer_input_id)
        );
    }

    /**
     * @return array<string, \ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gap>
     */
    public function fromDatabase(
        array $data
    ): array {

    }
}
