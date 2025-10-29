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

namespace ILIAS\Questions\AnswerFormTypes;

use ILIAS\Questions\AnswerForm\Type;

class Factory
{
    /**
     * @var array<string, \ILIAS\Questions\AnswerForm\Type> $available_answer_form_types
     */
    private readonly array $available_answer_form_types;

    /**
     * @param array<\ILIAS\Questions\AnswerForm\Type> $available_answer_form_types
     */
    public function __construct(
        array $available_answer_form_types
    ) {
        $this->available_answer_form_types = array_reduce(
            $available_answer_form_types,
            function (array $c, Type $v) {
                $c[$this->getHashedClass($v::class)] = $v;
                return $c;
            },
            []
        );
    }

    /**
     * @return array<string, \ILIAS\Questions\AnswerForm\Type>
     */
    public function getAvailableAnswerFormTypes(): array
    {
        return $this->available_answer_form_types;
    }

    public function getHashedClass(string $class): string
    {
        return md5($class);
    }

    public function getAnswerFormTypeByClassHash(string $class_hash): ?Type
    {
        return $this->available_answer_form_types[$class_hash] ?? null;
    }
}
