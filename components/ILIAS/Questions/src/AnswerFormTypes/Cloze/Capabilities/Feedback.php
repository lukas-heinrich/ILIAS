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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Capabilities;

use ILIAS\Questions\AnswerForm\Capabilities\Feedback as FeedbackInterface;
use ILIAS\Questions\Question\Response;
use ILIAS\Questions\AnswerForm;

class Feedback implements FeedbackInterface
{
    private ?AnswerForm $answer_form = null;

    public function isConfigured(): bool
    {
        return false;
    }

    public function withAnswerForm(AnswerForm $answer_form): self
    {
        $clone = clone $this;
        $clone->answer_form = $answer_form;
        return $clone;
    }

    public function getGeneralFeedback(Response $response): array
    {

    }

    public function getSpecificFeedback(Response $response, string $answer_id): array
    {

    }
}
