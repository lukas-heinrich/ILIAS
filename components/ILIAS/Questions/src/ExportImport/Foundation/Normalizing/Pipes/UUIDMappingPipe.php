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

namespace ILIAS\Questions\ExportImport\Foundation\Normalizing\Pipes;

use ILIAS\Data\UUID\Uuid;
use ILIAS\Data\UUID\Factory;
use ILIAS\Questions\ExportImport\Foundation\Contracts\Pipe;

/**
 * Pipe to map UUIDs to new UUIDs for the export and import process.
 *
 * @implements Pipe<Uuid>
 */
class UUIDMappingPipe implements Pipe
{
    private readonly Factory $uuid_factory;

    /**
     * @var array<string, Uuid>
     */
    private array $mappings = [];

    public function __construct()
    {
        $this->uuid_factory = new Factory();
    }

    public function handle(mixed $passable, \Closure $next): mixed
    {
        if (!$passable instanceof Uuid) {
            return $next($passable);
        }

        $uuid = $passable->toString();
        if (!array_key_exists($uuid, $this->mappings)) {
            $this->mappings[$uuid] = $this->uuid_factory->uuid4();
        }

        return $this->mappings[$uuid];
    }
}
