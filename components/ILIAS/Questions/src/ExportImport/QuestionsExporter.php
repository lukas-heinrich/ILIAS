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

namespace ILIAS\Questions\ExportImport;

use ILIAS\Questions\ExportImport\Foundation\Builder;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Serializer;

class QuestionsExporter
{
    public function __construct(
        private readonly Builder $builder,
    ) {
    }

    public function export(QuestionsDataCollector $collector, Serializer $serializer): string
    {
        $transformations = $this->builder->create();

        $serializer->group('questions', function () use ($transformations, $collector, $serializer): void {
            foreach ($collector->getQuestions() as $question) {
                $serializer->append(
                    'question',
                    $transformations->normalize($question),
                );
            }
        });

        return $serializer->write();

    }
}
