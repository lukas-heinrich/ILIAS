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
use ILIAS\Questions\ExportImport\Foundation\Contracts\Deserializer;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations;
use ILIAS\Questions\Persistence\Repository;

class QuestionsImporter
{
    public function __construct(
        private readonly Builder $builder,
        private readonly Repository $repository
    ) {
    }

    public function import(Deserializer $deserializer, int $parent_obj_id): void
    {
        // $transformations = $this->builder->withEnableMappings()->withLegacyNormalizers('11')->create();
        $transformations = $this->builder->withEnableMappings()->create();

        $deserializer->addHandler(
            'questions',
            fn($data) => $this->importQuestions($data, $parent_obj_id, $transformations)
        );

        $deserializer->process();
    }

    private function importQuestions(array $data, int $parent_obj_id, Transformations $tt): void
    {
        foreach ($data as $payload) {
            $this->importQuestion($payload, $parent_obj_id, $tt);
        }
    }

    private function importQuestion(array $payload, int $parent_obj_id, Transformations $tt)
    {
        //TODO: $tt->storeMapping($normalized['id'], $clone->id);
        $question = $tt->denormalize(
            $payload,
            $this->repository->getNew($parent_obj_id)
        );
        dd($payload, $question);
        //$this->repository->create($questions);
        // Siehe \ilPCAnswerFormGUI::insertCmd
        // Siehe \ilPCAnswerForm::create
        return $question;
    }
}
