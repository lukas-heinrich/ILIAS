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

namespace ILIAS\Questions\ExportImport\Foundation\Serializing;

use ILIAS\Questions\ExportImport\Foundation\Contracts\Serializer;

/**
 * Simple serializer that creates an XML string in memory. Due to its simplicity, it is not suitable for large datasets.
 */
class SimpleXMLSerializer implements Serializer
{
    private readonly \XMLWriter $writer;

    private string $current_group = '';

    public function __construct()
    {
        $this->writer = new \XMLWriter();
    }

    /**
     * @inheritDoc
     */
    public function open(string $path): static
    {
        $clone = clone $this;
        $clone->writer->openMemory();
        $clone->writer->setIndent(true);
        $clone->writer->startDocument('1.0', 'UTF-8');
        $clone->writer->writeComment("Example Export");
        $clone->writer->startElement('export');
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function startGroup(string $name): void
    {
        $this->current_group = $name;
        $this->writer->startElement($this->formatName($name));
    }

    /**
     * @inheritDoc
     */
    public function endGroup(string $name): void
    {
        if ($this->current_group !== $name) {
            throw new \LogicException(
                "Group name mismatch: expected end of '{$this->current_group}', got '{$name}'"
            );
        }

        $this->current_group = '';
        $this->writer->endElement();
    }

    /**
     * @inheritDoc
     */
    public function group(string $name, callable $callback): void
    {
        $this->startGroup($name);
        $callback();
        $this->endGroup($name);
    }

    /**
     * @inheritDoc
     */
    public function append(string $name, array $data): void
    {
        $this->writer->startElement($this->formatName($name));
        $this->appendRecursive($data);
        $this->writer->endElement();
    }

    /**
     * @inheritDoc
     */
    public function write(): string
    {
        $this->writer->endElement();
        $this->writer->endDocument();
        return $this->writer->outputMemory(true);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function appendRecursive(array $data): void
    {
        foreach ($data as $key => $value) {
            $type = gettype($value);
            $is_nested = is_array($value);

            if (is_numeric($key) || str_contains($key, '-') || $key === '') {
                $this->writer->startElement('item');
                $this->writer->writeAttribute('key', (string) $key);
            } else {
                $this->writer->startElement($this->formatName($key));
            }

            if (!$is_nested) {
                if ($type !== 'string') {
                    $this->writer->writeAttribute('type', $type);
                }

                $value = match ($type) {
                    'NULL' => 'NULL',
                    'boolean' => $value ? 'true' : 'false',
                    default => htmlspecialchars((string) $value),
                };

                $this->writer->writeRaw($value);
            } else {
                $this->appendRecursive($value);
            }

            $this->writer->endElement();
        }
    }

    private function formatName(int|string $name): string
    {
        // Transform key to kebab-case
        $output = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', str_replace(['_', ' '], '-', (string) $name)));
        return trim(preg_replace('/-+/', '-', $output), '-');
    }
}
