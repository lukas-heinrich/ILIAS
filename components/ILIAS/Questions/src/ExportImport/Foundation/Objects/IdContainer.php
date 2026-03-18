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

namespace ILIAS\Questions\ExportImport\Foundation\Objects;

/**
 * Wrapper object for an id (int, string, uuid, ...) and an optional object type the id belongs to. The wrapper can be
 * used to detect ids within the normalization and denormalization pipes.
 *
 * @template T
 */
class IdContainer
{
    /**
     * @param T $id
     */
    public function __construct(
        private mixed $id = null,
        private string $object = ''
    ) {
    }

    /**
     * @return T
     */
    public function getId(): mixed
    {
        return $this->id;
    }

    public function getObject(): string
    {
        return $this->object;
    }
}
