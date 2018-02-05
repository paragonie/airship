<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge;

use ParagonIE\Ionizer\Filter\{
    BoolFilter,
    IntFilter,
    StringFilter,
    WhiteList
};
use ParagonIE\Ionizer\InputFilterContainer;

/**
 * Class ConfigFilter
 * @package Airship\Cabin\Bridge
 */
class ConfigFilter extends InputFilterContainer
{
    /**
     * ConfigFilter constructor.
     *
     * Specifies the filter rules for the cabin configuration POST rules.
     */
    public function __construct()
    {
        $this

        /* config_extra */
            ->addFilter('config_extra.board.enabled', new BoolFilter())
            ->addFilter(
                'config_extra.editor.default-format',
                (new WhiteList(
                        'HTML',
                        'Markdown',
                        'Rich Text',
                        'RST'
                ))->setDefault('Rich Text')
            )
            ->addFilter('config_extra.recaptcha.secret-key', new StringFilter())
            ->addFilter('config_extra.recaptcha.site-key', new StringFilter())
            ->addFilter('config_extra.password-reset.enabled', new BoolFilter())
            ->addFilter('config_extra.password-reset.logout', new BoolFilter())
            ->addFilter('config_extra.password-reset.ttl', new IntFilter())
            ->addFilter('config_extra.file.cache', new IntFilter())
            ->addFilter('config_extra.two-factor.label', new StringFilter())
            ->addFilter('config_extra.two-factor.issuer', new StringFilter())
            ->addFilter(
                'config_extra.two-factor.length',
                (new IntFilter())->addCallback(
                    function (int $var): int {
                        if ($var < 6) {
                            return 6;
                        } elseif ($var > 8) {
                            return 8;
                        }
                        return (int) $var;
                    }
                )
            )
            ->addFilter(
                'config_extra.two-factor.period',
                (new IntFilter())
                    ->setDefault(30)
            )
            ->addFilter(
                'config_extra.user-directory.per-page',
                (new IntFilter())
                    ->setDefault(20)
            )

        /* twig_vars */
            ->addFilter('twig_vars.active-motif', new StringFilter())
            ->addFilter('twig_vars.title', new StringFilter())
        ;
    }
}
