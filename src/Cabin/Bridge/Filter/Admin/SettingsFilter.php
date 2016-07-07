<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Filter\Admin;

use Airship\Engine\Security\Filter\{
    ArrayFilter,
    BoolFilter,
    FloatFilter,
    InputFilterContainer,
    IntFilter,
    StringFilter
};

/**
 * Class SettingsFilter
 * @package Airship\Cabin\Bridge\Filter\Account
 */
class SettingsFilter extends InputFilterContainer
{
    /**
     * SettingsFilter constructor.
     */
    public function __construct()
    {
        $this
            // content_security_policy.json
            ->addFilter('content_security_policy.connect-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.connect-src.data', new BoolFilter())
            ->addFilter('content_security_policy.connect-src.self', new BoolFilter())
            ->addFilter('content_security_policy.child-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.child-src.data', new BoolFilter())
            ->addFilter('content_security_policy.child-src.self', new BoolFilter())
            ->addFilter('content_security_policy.form-action.allow', new ArrayFilter())
            ->addFilter('content_security_policy.form-action.self', new BoolFilter())
            ->addFilter('content_security_policy.font-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.font-src.data', new BoolFilter())
            ->addFilter('content_security_policy.font-src.self', new BoolFilter())
            ->addFilter('content_security_policy.frame-ancestors.allow', new ArrayFilter())
            ->addFilter('content_security_policy.frame-ancestors.self', new BoolFilter())
            ->addFilter('content_security_policy.img-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.img-src.data', new BoolFilter())
            ->addFilter('content_security_policy.img-src.self', new BoolFilter())
            ->addFilter('content_security_policy.media-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.media-src.self', new BoolFilter())
            ->addFilter('content_security_policy.object-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.object-src.data', new BoolFilter())
            ->addFilter('content_security_policy.object-src.self', new BoolFilter())
            ->addFilter('content_security_policy.script-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.script-src.data', new BoolFilter())
            ->addFilter('content_security_policy.script-src.self', new BoolFilter())
            ->addFilter('content_security_policy.script-src.unsafe-eval', new BoolFilter())
            ->addFilter('content_security_policy.script-src.unsafe-inline', new BoolFilter())
            ->addFilter('content_security_policy.style-src.allow', new ArrayFilter())
            ->addFilter('content_security_policy.style-src.data', new BoolFilter())
            ->addFilter('content_security_policy.style-src.self', new BoolFilter())
            ->addFilter('content_security_policy.style-src.unsafe-inline', new BoolFilter())
            ->addFilter('content_security_policy.upgrade-insecure-requests', new BoolFilter())
            // universal.json
            ->addFilter(
                'universal.auto-update.check',
                (new IntFilter())
                    ->setDefault(3600)
            )
            ->addFilter('universal.auto-update.enabled', new BoolFilter())
            ->addFilter('universal.debug', new BoolFilter())
            ->addFilter('universal.email.from', new StringFilter())
            ->addFilter(
                'universal.ledger.driver',
                (new StringFilter())->addCallback(function ($driver): string {
                    if ($driver === 'file') {
                        return 'file';
                    } elseif ($driver === 'database') {
                        return 'database';
                    }
                    throw new \TypeError(
                        'Invalid Ledger driver'
                    );
                })
            )
            ->addFilter('universal.guest_groups', new ArrayFilter())
            ->addFilter('universal.ledger.path', new StringFilter())
            ->addFilter(
                'universal.notary.channel',
                (new StringFilter())
                    ->addCallback(function () {
                        // In the future, this will be changeable.
                        return 'paragonie';
                    })
            )
            ->addFilter('universal.notary.enabled', new BoolFilter())
            ->addFilter('universal.rate-limiting.expire', new IntFilter())
            ->addFilter('universal.rate-limiting.fast-exit', new BoolFilter())
            ->addFilter('universal.rate-limiting.first-delay', new FloatFilter())
            ->addFilter(
                'universal.rate-limiting.ipv4-subnet',
                (new IntFilter())->setDefault(32)
            )
            ->addFilter(
                'universal.rate-limiting.ipv6-subnet',
                (new IntFilter())->setDefault(64)
            )
            ->addFilter('universal.rate-limiting.log-after', new IntFilter())
            ->addFilter(
                'universal.rate-limiting.max-delay',
                (new IntFilter())->setDefault(30)
            )
            ->addFilter(
                'universal.rate-limiting.log-public-key',
                (new StringFilter())->addCallback(function ($str): string {
                    if (empty($str)) {
                        return '';
                    }
                    if (\preg_match('#^[0-9A-Fa-f]{64}$#', $str)) {
                        return $str;
                    }
                    return '';
                })
            )
            ->addFilter('universal.session_config.cookie_domain', new StringFilter())
            ->addFilter('universal.tor-only', new BoolFilter())
            ->addFilter('universal.twig-cache', new BoolFilter())
            ->addFilter('universal.trusted-supplier', new StringFilter());
    }
}
