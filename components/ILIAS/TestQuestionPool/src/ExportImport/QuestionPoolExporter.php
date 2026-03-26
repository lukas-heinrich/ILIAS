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

namespace ILIAS\TestQuestionPool\ExportImport;

use ilObjQuestionPool;
use ILIAS\Data\UUID\Factory as UUIDFactory;
use ILIAS\Export\ExportHandler\I\Consumer\ExportWriter\HandlerInterface as ExportWriter;
use ILIAS\Export\ExportHandler\I\Consumer\ExportConfig\CollectionInterface as ExportConfig;
use ILIAS\Questions\ExportImport\Foundation\Builder;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Serializer;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Transformations;
use ILIAS\TestQuestionPool\ExportImport\Pipes\CollectQuestionImages;

/**
 * Orchestrates the export of a question pool. It uses the Builder to create a pipeline of transformations that are used
 * to normalize the data and then writes the normalized data to the serializer. It also copies the needed files to the
 * export directory.
 */
class QuestionPoolExporter
{
    public function __construct(
        private readonly Builder $builder,
        private readonly ExportWriter $writer,
        private readonly ExportConfig $config,
        private readonly string $export_dir
    ) {
    }

    /**
     * Starts the export process. It will return the serialized data as a string.
     */
    public function export(QuestionPoolCollector $collector, Serializer $serializer): string
    {
        $question_image_pipe = new CollectQuestionImages(
            new UUIDFactory(),
            $collector->getPoolId()
        );
        $transformations = $this->builder->withAdditionalPipes([$question_image_pipe])->create();

        // Export the pool object and its dependencies by writing them to the serializer
        $this->exportPool($collector, $serializer, $transformations);
        $serializer->group(
            'units',
            fn() => $this->exportUnits($collector, $serializer, $transformations)
        );
        $serializer->group(
            'questions',
            fn() => $this->exportQuestions($collector, $serializer, $transformations)
        );

        // Copy the question images to the export directory
        foreach ($question_image_pipe->getFiles() as $file) {
            $this->writer->writeFileByFilePath($file['from'], "{$this->export_dir}/" . $file['to']);
        }

        return $serializer->write();
    }

    protected function exportPool(QuestionPoolCollector $collector, Serializer $serializer, Transformations $tt): void
    {
        $pool_obj = new ilObjQuestionPool($collector->getPoolId()->toInt(), false);
        $pool_obj->loadFromDb();

        $serializer->append('object', $tt->normalize($pool_obj));
    }

    protected function exportUnits(QuestionPoolCollector $collector, Serializer $serializer, Transformations $tt): void
    {
        foreach ($collector->getUnitCategories() as $category) {
            $aggregated = [
                ... $tt->normalize($category),
                'units' => $tt->normalize($collector->getUnits($category->getId())),
            ];

            $serializer->append('category', $aggregated);
        }
    }

    protected function exportQuestions(QuestionPoolCollector $collector, Serializer $serializer, Transformations $tt): void
    {
        foreach ($collector->getQuestionObjects() as $question) {
            $serializer->append('question', $tt->normalize($question));
            $serializer->append('feedback', $tt->normalize($collector->getFeedback($question)));
        }
    }
}
