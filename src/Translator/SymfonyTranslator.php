<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2021 ZfcDatagrid
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category ZfcDatagrid
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Translator;

use Symfony\Contracts\Translation\TranslatorInterface as SymfonyTranslatorInterface;
use ZfcDatagrid\Translator\TranslatorInterface;

;

/**
 * Adapter for Laminas Router
 */
class SymfonyTranslator implements TranslatorInterface
{
    /**
     * @var SymfonyTranslatorInterface
     */
    protected $translator;

    public function __construct(SymfonyTranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function translate(string $message)
    {
        return $this->translator->trans($message);
    }
}
