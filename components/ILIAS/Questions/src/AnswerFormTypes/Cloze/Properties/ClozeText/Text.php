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

namespace ILIAS\Questions\AnswerFormTypes\Cloze\Properties\ClozeText;

use ILIAS\Questions\AnswerFormTypes\Cloze\Properties\Gaps\Gaps;
use ILIAS\Data\Text\Markdown;
use ILIAS\Data\Text\Factory as TextFactory;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Language\Language;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Field\Markdown as MarkdownInput;
use ILIAS\UI\Component\Input\Field\Hidden as HiddenInput;
use Mustache\Engine;

class Text
{
    public function __construct(
        private readonly Refinery $refinery,
        private readonly Engine $mustache_engine,
        private readonly TextFactory $text_factory,
        private Markdown $cloze_text
    ) {
    }

    public function getInput(
        Language $lng,
        FieldFactory $ff,
        Factory $cloze_text_factory
    ): MarkdownInput {
        return $ff->markdown(
            new \ilUIMarkdownPreviewGUI(),
            $lng->txt('cloze_text')
        )->withMustacheVAriables([
            'GAP' => $lng->txt('gap')
        ])->withAdditionalTransformation(
            $this->refinery->custom()->transformation(
                fn(string $v): self => $cloze_text_factory->buildFromTextString($v)
            )
        )->withAdditionalTransformation(
            $this->refinery->custom()->constraint(
                fn(self $v): bool => $v->hasAtLeastOneGap(),
                $lng->txt('no_gaps')
            )
        )->withRequired(true)
        ->withValue($this->cloze_text->getRawRepresentation());
    }

    public function getHiddenInput(FieldFactory $ff): HiddenInput
    {
        return $ff->hidden()->withValue($this->getTextForOutputInHiddenInput());
    }

    public function getRenderedMarkdown(): string
    {
        return $this->mustache_engine->render(
            $this->cloze_text->getRawRepresentation(),
            array_reduce(
                $this->getAllGaps(),
                function (array $c, Uuid $v): array {
                    $c[$this->buildGapPlaceholderNameWithId($v)] = $this->buildShortenedGapRepresentation($v);
                    return $c;
                },
                []
            )
        );
    }

    public function hasAtLeastOneGap(): bool
    {
        return $this->new_gaps + $this->preexisting_gaps !== [];
    }

    public function extractGapsFromMarkdown(
        Gaps $existing_gaps
    ): array {
        if ($this->cloze_text->getRawRepresentation() === '') {
            return $existing_gaps->withResetGaps();
        }

        $position = 0;
        return array_reduce(
            $this->mustache_engine->getTokenizer()->scan($this->cloze_text->getRawRepresentation()),
            function (Gaps $c, array $v) use (&$position): Gaps {
                if ($v['type'] !== '_v'
                    || !str_starts_with($v['name'], 'GAP')) {
                    return $c;
                }

                if ($v['name'] === 'GAP') {
                    return $c->withNewGap();
                }

                $gap = $c->getGapByTagName($v['name'])?->withPosition($position);

                if ($gap === null) {
                    return $c;
                }

                return $c->withUpdatedGap($gap);
            },
            $existing_gaps
        );
    }

    public function addIdsOfNewGapsToClozeText(Gaps $gaps): Markdown
    {
        $new_gaps = $gaps->getUndefinedGaps();
        return $this->text_factory->markdown(
            mb_ereg_replace_callback(
                $this->buildGapPlaceholder(),
                function (array $matches) use (&$new_gaps): string {
                    return array_shift($new_gaps)->getGapPlaceholder();
                },
                $this->cloze_text->getRawRepresentation()
            )
        );
    }

    private function getTextForOutputInHiddenInput(): string
    {
        return str_replace(['{{', '}}'], ['&#123;&#123;', '&#125;&#125;'], $this->cloze_text->getRawRepresentation());
    }
}
