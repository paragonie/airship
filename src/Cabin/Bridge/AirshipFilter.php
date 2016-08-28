<?php

declare(strict_types=1);
namespace Airship\Cabin\Bridge;

use Airship\Engine\Security\Filter\{
    ArrayFilter,
    BoolFilter,
    FloatFilter,
    InputFilterContainer,
    IntArrayFilter,
    IntFilter,
    StringFilter
};

/**
 * Class AirshipFilter
 * @package Airship\Cabin\Bridge
 */
class AirshipFilter extends InputFilterContainer
{
    /**
     * AirshipFilter constructor.
     */
    public function __construct()
    {
        $this
            /* universal.json */
            ->addFilter('universal.airship.trusted-supplier', new StringFilter())

            ->addFilter('universal.auto-update.ignore-peer-verification', new BoolFilter())
            ->addFilter('universal.auto-update.check', new IntFilter())
            ->addFilter('universal.auto-update.major', new BoolFilter())
            ->addFilter('universal.auto-update.minor', new BoolFilter())
            ->addFilter('universal.auto-update.patch', new BoolFilter())
            ->addFilter('universal.auto-update.test', new BoolFilter())

            ->addFilter('universal.debug', new BoolFilter())

            ->addFilter('universal.email.from', new StringFilter())

            ->addFilter('universal.guest_groups', new IntArrayFilter())

            ->addFilter('universal.guzzle', new ArrayFilter())

            ->addFilter('universal.ledger.driver', new StringFilter())
            ->addFilter('universal.ledger.path', new StringFilter())
            ->addFilter('universal.ledger.file-format', new StringFilter())
            ->addFilter('universal.ledger.time-format', new StringFilter())
            ->addFilter('universal.ledger.connection', new StringFilter())
            ->addFilter('universal.ledger.table', new StringFilter())

            ->addFilter('universal.notary.channel', (new StringFilter())
                ->setDefault('paragonie')
            )
            ->addFilter('universal.notary.enabled', new BoolFilter())

            ->addFilter('universal.rate-limiting.expire', (new IntFilter())->setDefault(3600))
            ->addFilter('universal.rate-limiting.fast-exit', new BoolFilter())
            ->addFilter('universal.rate-limiting.first-delay', (new FloatFilter())->setDefault(0.25))
            ->addFilter('universal.rate-limiting.ipv4-subnet',
                (new IntFilter())
                    ->setDefault(32)
                    ->addCallback(
                        function ($var): int {
                            if ($var < 1) {
                                return 1;
                            } elseif ($var > 32) {
                                return 32;
                            }
                            return (int) $var;
                        }
                    )
            )
            ->addFilter('universal.rate-limiting.ipv6-subnet',
                (new IntFilter())
                    ->setDefault(64)
                    ->addCallback(
                        function ($var): int {
                            if ($var < 1) {
                                return 1;
                            } elseif ($var > 128) {
                                return 128;
                            }
                            return (int) $var;
                        }
                    )
            )
            ->addFilter('universal.rate-limiting.log-after', (new IntFilter())->setDefault(3))
            ->addFilter('universal.rate-limiting.log-public-key',
                (new StringFilter())
                    ->addCallback(function ($var): string {
                        // Hex-encoded public keys are 64-char hex strings.
                        if (\preg_match('/^[0-9A-Fa-f]{64}$/', $var)) {
                            return \strtolower($var);
                        }
                        return '';
                    })
            )
            ->addFilter('universal.rate-limiting.max-delay', (new IntFilter())->setDefault(30))

            ->addFilter('universal.session_config.cookie_domain', new StringFilter())
            ->addFilter('universal.tor-only', new BoolFilter())
            ->addFilter('universal.twig-cache', new BoolFilter())
        ;
    }
}