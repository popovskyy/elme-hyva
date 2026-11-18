<?php

declare(strict_types=1);

namespace Elme\Theme\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The tones of the glass panel that carries text over a photo (web/tailwind/elme/foundation/glass.css).
 */
class PanelStyle implements OptionSourceInterface
{
    public const INK = 'ink';

    public const LIGHT = 'light';

    public const OLIVE = 'olive';

    public const DEFAULT_STYLE = self::INK;

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::INK, 'label' => __('Ink glass (dark, white text)')],
            ['value' => self::LIGHT, 'label' => __('Light glass (frosted, dark text)')],
            ['value' => self::OLIVE, 'label' => __('Olive glass (green, white text)')],
        ];
    }
}
