<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Alerts\Continuum\{
    CouldNotUpdate,
    NoSupplier
};
use \ParagonIE\Halite\Asymmetric\{
    Crypto as Asymmetric,
    SignaturePublicKey
};

/**
 * Class KeyUpdate
 *
 * This represents a key update
 *
 * @package Airship\Engine\Continuum
 */
class KeyUpdate
{
    const ACTION_INSERT_KEY = 'insert';
    const ACTION_REVOKE_KEY = 'revoke';
    const KEY_TYPE_MASTER = 'master';
    const KEY_TYPE_SIGNING = 'sub';

    protected $channelId;
    protected $channelName;
    protected $isNewSupplier = false;
    protected $merkleRoot = '';
    protected $updateMessage = [];
    protected $supplier;
    protected $supplierMasterKeyUsed;
    protected $verified = false;

    /**
     * KeyUpdate constructor.
     * @param Channel $chan
     * @param array $updateData
     */
    public function __construct(Channel $chan, array $updateData)
    {
        $this->channelId = (int) $updateData['id'];
        $this->channelName = $chan->getName();
        $this->merkleRoot = $updateData['root'];
        $this->unpackMessageUpdate($chan, $updateData['data']);
    }

    /**
     * The upstream primary key for this update
     *
     * @return int
     */
    public function getChannelId(): int
    {
        return $this->channelId;
    }

    /**
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }

    /**
     * @return string
     */
    public function getNodeJSON(): string
    {
        return \json_encode($this->updateMessage);
    }

    /**
     * @return Supplier
     */
    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    /**
     * Return the appropriate supplier (or create it if it doesn't already exist)
     *
     * @param Channel $chan
     * @param array $updateData
     * @return Supplier
     * @throws CouldNotUpdate
     */
    protected function loadSupplier(Channel $chan, array $updateData): Supplier
    {
        try {
            return $chan->getSupplier($updateData['supplier']);
        } catch (NoSupplier $ex) {
            if ($updateData['action'] !== self::ACTION_INSERT_KEY) {
                throw new CouldNotUpdate(
                    'For new suppliers, we can only insert their first master key',
                    0,
                    $ex
                );
            }
            if ($updateData['type'] !== self::KEY_TYPE_MASTER) {
                throw new CouldNotUpdate(
                    'Non-master key provided. It is possible that the channel is borked.',
                    0,
                    $ex
                );
            }

            // If we reach here, it's a new supplier.
            $this->isNewSupplier = true;
            return $chan->createSupplier($updateData);
        }
    }

    /**
     * This method stores the necessary bits of data in this object.
     *
     * @param Channel $chan
     * @param array $updateData
     * @return void
     * @throws CouldNotUpdate
     */
    protected function unpackMessageUpdate(Channel $chan, array $updateData)
    {
        $messageToSign = [
            'action' =>
                $updateData['action'],
            'date_generated' =>
                $updateData['date_generated'],
            'public_key' =>
                $updateData['public_key'],
            'supplier' =>
                $updateData['supplier'],
            'type' =>
                $updateData['type']
        ];
        $this->supplier = $this->loadSupplier($chan, $updateData);
        $signature = $updateData['signature'];

        // If this isn't a new supplier, we need to verify the key
        if (!$this->isNewSupplier) {
            foreach ($this->supplier->getSigningKeys() as $supKey) {
                if (IDE_HACKS) {
                    // Hi PHPStorm, this is a SignaturePublicKey I promise!
                    $supKey['key'] = new SignaturePublicKey();
                }
                if ($supKey['type'] !== 'master') {
                    continue;
                }
                $pub = \Sodium\bin2hex($supKey['key']->getRawKeyMaterial());
                if (\hash_equals($pub, $messageToSign['public_key'])) {
                    // Store the public key
                    $this->supplierMasterKeyUsed = $supKey;
                    break;
                }
            }
            if (empty($this->supplierMasterKeyUsed)) {
                throw new CouldNotUpdate('The provided public key does not match any known master key');
            }
            $encoded = \json_encode($messageToSign);
            if (!Asymmetric::verify($encoded, $this->supplierMasterKeyUsed, $signature)) {
                throw new CouldNotUpdate('Invalid signature for this master key');
            }
        }

        $this->updateMessage = $updateData;
    }
}
