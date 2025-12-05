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

namespace ILIAS\Questions\AnswerForm;

use ILIAS\Data\UUID\Uuid;

class Form
{
    public function __construct(
        private readonly Definition $type_definition,
        private readonly Uuid $question_id,
        private ?Uuid $id = null,
        private ?TypeSpecificData $type_specific_data = null,
        private ?Skills $skills = null
    ) {
    }

    public function getTypeDefinition(): Definition
    {
        return $this->type_definition;
    }

    public function getQuestionId(): Uuid
    {
        return $this->question_id;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function withId(Uuid $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    public function getSkills(): Skills
    {
        return $this->skills;
    }

    public function withSkills(Skills $skills): self
    {
        $clone = clone $this;
        $clone->skills = $skills;
        return $clone;
    }
}
