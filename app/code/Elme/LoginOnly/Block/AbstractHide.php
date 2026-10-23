<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Block;

use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\View\Element\Template;

/**
 * Layout wrapper that renders its template, or only the child named in "child_name_to_output", for one login
 * state. Uses the HTTP context, so it is right on cached pages too.
 */
abstract class AbstractHide extends Template
{
    public function __construct(
        Template\Context $context,
        protected readonly LoginState $loginState,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->shouldShowBlock()) {
            return '';
        }

        $childName = (string) $this->getData('child_name_to_output');
        if ($childName !== '') {
            return $this->getChildHtml($childName);
        }

        return $this->getTemplate() ? parent::_toHtml() : $this->getChildHtml();
    }

    abstract protected function shouldShowBlock(): bool;
}
