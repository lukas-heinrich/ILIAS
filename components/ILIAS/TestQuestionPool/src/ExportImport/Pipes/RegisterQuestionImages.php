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

namespace ILIAS\TestQuestionPool\ExportImport\Pipes;

use ILIAS\Data\UUID\Factory;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Pipe;
use ILIAS\Questions\ExportImport\Foundation\Normalizing\Pipes\NormalizeCarry;
use ILIAS\TestQuestionPool\ExportImport\Envelopes\QuestionImage;
use ILIAS\TestQuestionPool\Questions\Files\QuestionFiles;

/**
 * Pipe that enriches QuestionImage envelopes with UUID-based IDs and records source-to-target file mappings during
 * normalization.
 */
class RegisterQuestionImages implements Pipe
{
    /**
     * @var list<array{from: string, to: string}> $files
     */
    private array $files = [];

    public function __construct(
        private readonly QuestionFiles $question_files,
        private readonly Factory $uuid_factory,
        private readonly int $pool_obj_id,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(mixed $passable, \Closure $next): mixed
    {
        if ($passable instanceof NormalizeCarry) {
            return $this->handleNormalization($passable, $next);
        }

        return $next($passable);
    }

    private function handleNormalization(mixed $passable, \Closure $next): mixed
    {
        if (!$passable->value instanceof QuestionImage) {
            return $next($passable);
        }

        // Build the absolute source path
        $base_dir = $this->question_files->buildImagePath(
            $passable->value->getQuestionId(),
            $this->pool_obj_id
        );

        if ($passable->value->getType() === QuestionImage::TYPE_SOLUTION) {
            $base_dir = str_replace('images/', 'solution/', $base_dir);
        }

        $source_path = $base_dir . $passable->value->getFilename();

        // Generate a unique ID for the image and set it on the envelope and the relative target path
        $id = $this->uuid_factory->uuid4();
        $passable->value->setId($id->toString());

        $extension = pathinfo($passable->value->getFilename(), PATHINFO_EXTENSION);
        $target_path = $id->toString() . '.' . $extension;

        $this->files[] = ['from' => $source_path, 'to' => $target_path];

        return $next($passable);
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
