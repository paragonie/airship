<?php
declare(strict_types=1);
namespace Airship\Engine\Keyggdrasil;

use \Airship\Alerts\Continuum\{
    CouldNotUpdate,
    NoSupplier
};
use \Airship\Engine\Continuum\{
    Channel,
    Supplier
};
use \Airship\Engine\State;
use \ParagonIE\Halite\Asymmetric\{
    Crypto as Asymmetric,
    SignaturePublicKey
};

/**
 * Class TreeUpdate
 *
 * This represents an update to Keyggdrasil
 *
 * @package Airship\Engine\Keyggdrasil
 */
class TreeUpdate
{
    const ACTION_INSERT_KEY = 'CREATE';
    const ACTION_REVOKE_KEY = 'REVOKE';
    const ACTION_CORE_UPDATE = 'CORE';
    const ACTION_PACKAGE_UPDATE = 'PACKAGE';

    const KEY_TYPE_MASTER = 'master';
    const KEY_TYPE_SIGNING = 'sub';

    /**
     * @var int
     */
    protected $channelId;
    /**
     * @var string
     */
    protected $channelName;
    /**
     * @var string
     */
    protected $checksum;
    /**
     * @var bool
     */
    protected $isNewSupplier = false;
    /**
     * @var string
     */
    protected $keyType;
    /**
     * @var string|null
     */
    protected $masterSig = null;
    /**
     * @var string
     */
    protected $merkleRoot = '';
    /**
     * @var string
     */
    protected $newPublicKey = null;
    protected $updateMessage = [];
    /**
     * @var array
     */
    protected $stored;
    /**
     * @var Supplier
     */
    protected $supplier;
    /**
     * @var SignaturePublicKey
     */
    protected $supplierMasterKeyUsed;
    /**
     * @var string
     */
    protected $supplierName;
    /**
     * @var bool
     */
    protected $verified = false;

    /**
     * TreeUpdate constructor.
     *
     * @param Channel $chan
     * @param array $updateData
     */
    public function __construct(Channel $chan, array $updateData)
    {
        /**
         * This represents data from the base64urlsafe encoded JSON blob that is signed by the channel.
         */
        $this->channelId = (int) $updateData['id'];
        $this->channelName = $chan->getName();
        $this->merkleRoot = $updateData['root'];
        $this->stored = $updateData['stored'];
        $this->action = $this->stored['action'];
        $packageRelatedActions = [
            self::ACTION_CORE_UPDATE,
            self::ACTION_PACKAGE_UPDATE
        ];

        if (\in_array($this->action, $packageRelatedActions)) {
            // This is a package-related update:
            $this->checksum = $this->stored['checksum'];

            // This is the JSON message from the tree node, stored as an array:
            $data = \json_decode($updateData['data'], true);
            $this->updateMessage = $data;

            if ($this->action === self::ACTION_CORE_UPDATE) {
                $state = State::instance();
                $trustedSupplier = $state->universal['airship']['trusted-supplier'] ?? 'paragonie';
                $this->supplier = $chan->getSupplier($trustedSupplier);
            } else {
                $this->supplier = $chan->getSupplier($data['supplier']);
            }
        } else {
            // This is a key-related update:
            if (!empty($updateData['master_signature'])) {
                $this->masterSig = $updateData['master_signature'];
            }
            $data = \json_decode($updateData['data'], true);
            try {
                $this->unpackMessageUpdate($chan, $data);
            } catch (NoSupplier $ex) {
                $this->isNewSupplier = true;
                $chan->createSupplier($data);
                $this->supplier = $chan->getSupplier($data['supplier']);
            }
            $this->keyType = $data['type'];
            $this->newPublicKey = $data['public_key'];
        }
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
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * @return string
     */
    public function getNodeJSON(): string
    {
        return \json_encode($this->updateMessage);
    }

    /**
     * Get the new public key as a SignaturePublicKey object
     *
     * @return SignaturePublicKey
     */
    public function getPublicKeyObject(): SignaturePublicKey
    {
        return new SignaturePublicKey(
            \Sodium\hex2bin($this->newPublicKey)
        );
    }

    /**
     * Get the new public key as a hex-coded string
     *
     * @return string
     */
    public function getPublicKeyString(): string
    {
        return $this->newPublicKey;
    }

    /**
     * Get the Merkle root for this update
     *
     * @return string
     */
    public function getRoot(): string
    {
        return $this->merkleRoot;
    }

    /**
     * @return Supplier
     */
    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    /**
     * @return string
     */
    public function getSupplierName(): string
    {
        return $this->supplierName;
    }

    /**
     * Is this an update to the Airship core?
     *
     * @return bool
     */
    public function isAirshipUpdate(): bool
    {
        return $this->action === self::ACTION_CORE_UPDATE;
    }

    /**
     * Is this a "create key" update?
     *
     * @return bool
     */
    public function isCreateKey(): bool
    {
        return $this->action === self::ACTION_INSERT_KEY;
    }

    /**
     * Is this a "package release" update?
     *
     * @return bool
     */
    public function isPackageUpdate(): bool
    {
        return $this->action === self::ACTION_PACKAGE_UPDATE;
    }

    /**
     * Is this a "revoke key" update?
     *
     * @return bool
     */
    public function isRevokeKey(): bool
    {
        return $this->action === self::ACTION_REVOKE_KEY;
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
        $this->supplierName = \preg_replace('/[^A-Za-z0-9\-_]/', '', $updateData['supplier']);
        try {
            if (!\file_exists(ROOT . '/config/supplier_keys/' . $this->supplierName . '.json')) {
                throw new NoSupplier($this->supplierName);
            }
            return $chan->getSupplier($this->supplierName);
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
     * @throws NoSupplier
     */
    protected function unpackMessageUpdate(Channel $chan, array $updateData)
    {
        // This is the JSON message from the tree node, stored as an array:
        $this->updateMessage = $updateData;
        if ($this->isPackageUpdate() || $this->isAirshipUpdate()) {
            // These aren't signed for updating the tree.
            return;
        }
        // We need a precise format:
        $dateGen = (new \DateTime($this->stored['date_generated']))
            ->format('Y-m-d\TH:i:s');
        $messageToSign = [
            'action' =>
                $this->action,
            'date_generated' =>
                $dateGen,
            'public_key' =>
                $updateData['public_key'],
            'supplier' =>
                $updateData['supplier'],
            'type' =>
                $updateData['type']
        ];

        try {
            $this->supplier = $this->loadSupplier($chan, $updateData);
        } catch (NoSupplier $ex) {
            if (!$this->isNewSupplier) {
                throw $ex;
            }
        }
        // If this isn't a new supplier, we need to verify the key
        if ($this->isNewSupplier) {
            return;
        }
        $master = \json_decode($updateData['master'], true);
        foreach ($this->supplier->getSigningKeys() as $supKey) {
            // Yes, this is (in fact) a SignaturePublicKey:
            if (IDE_HACKS) {
                $supKey['key'] = new SignaturePublicKey();
            }
            if ($supKey['type'] !== 'master') {
                continue;
            }
            $pub = \Sodium\bin2hex(
                $supKey['key']->getRawKeyMaterial()
            );

            // Is this the key we're looking for?
            if (\hash_equals($pub, $master['public_key'])) {
                // Store the public key
                $this->supplierMasterKeyUsed = $supKey['key'];
                break;
            }
        }
        if (empty($this->supplierMasterKeyUsed)) {
            throw new CouldNotUpdate(
                'The provided public key does not match any known master key '
            );
        }
        $encoded = \json_encode($messageToSign);
        if (!Asymmetric::verify($encoded, $this->supplierMasterKeyUsed, $master['signature'])) {
            throw new CouldNotUpdate(
                'Invalid signature for this master key'
            );
        }
    }
}
