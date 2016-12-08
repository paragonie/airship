<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use Airship\Alerts\{
    Continuum\CouldNotUpdate,
    InvalidType
};
use Airship\Engine\{
    Continuum\API,
    Database,
    Hail,
    State
};
use GuzzleHttp\{
    Client,
    Exception\TransferException
};
use ParagonIE\ConstantTime\{
    Base64UrlSafe,
    Binary
};
use ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Asymmetric\SignaturePublicKey,
    Asymmetric\SignatureSecretKey,
    Structure\MerkleTree,
    Structure\Node
};
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;

require_once __DIR__.'/init_gear.php';

/**
 * Class ChannelUpdates
 *
 * Manage key updates from a particular channel
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class ChannelUpdates extends BlueprintGear
{
    /**
     * @var string
     */
    private $channel;

    /**
     * @var SignaturePublicKey
     */
    private $channelPublicKey;

    /**
     * @var string[]
     */
    private $urls;

    /**
     * ChannelUpdates constructor.
     * 
     * @param Database $db
     * @param string $channel
     */
    public function __construct(Database $db, string $channel = '')
    {
        parent::__construct($db);
        $this->channel = $channel;
        $channelConfig = \Airship\loadJSON(ROOT . '/config/channels.json');
        $this->channelPublicKey = new SignaturePublicKey(
            \Sodium\hex2bin($channelConfig[$channel]['publickey'])
        );
        $this->urls = $channelConfig[$channel]['urls'];
    }

    /**
     * Return an encoded message containing the updates,
     * and an encoded Ed25519 signature of the message.
     *
     * @param SignatureSecretKey $sk
     * @param string $challenge
     * @return string[]
     */
    public function verifyUpdate(SignatureSecretKey $sk, string $challenge): array
    {
        $tree = $this->getUpdatedMerkleTree();

        $now = new \DateTime('now');
        $updates = [
            'challenge' => $challenge,
            'root' => $tree->getRoot(),
            'timestamp' => $now->format(\AIRSHIP_DATE_FORMAT)
        ];
        $response = \json_encode($updates);
        return [
            Base64UrlSafe::encode($response),
            Base64UrlSafe::encode(
                AsymmetricCrypto::sign($response, $sk, true)
            )
        ];
    }

    /**
     * Get all URLs
     *
     * @param bool $doNotShuffle
     * @return string[]
     */
    protected function getChannelURLs(bool $doNotShuffle = false): array
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            $after = [];
            foreach ($this->urls as $url) {
                if (\Airship\isOnionUrl($url)) {
                    $candidates[] = $url;
                } else {
                    $after[] = $url;
                }
            }

            // Shuffle each array separately, to maintain priority;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url;
            }
        } else {
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
        }
        return $candidates;
    }

    /**
     * Send the HTTP request, return the
     *
     * @param string $root
     * @return array
     */
    protected function getChannelUpdates(string $root): array
    {
        $state = State::instance();
        if (IDE_HACKS) {
            $state->hail = new Hail(new Client());
        }
        foreach ($this->getChannelURLs() as $url) {
            $initiated = new \DateTime('now');
            $response = $state->hail->postSignedJSON(
                $url . API::get('fetch_keys') . '/' . $root,
                $this->channelPublicKey
            );
            try {
                // We use a separate method for parsing this update:
                return $this->parseChannelUpdateResponse(
                    $response,
                    $initiated
                );
            } catch (CouldNotUpdate $ex) {
                // Log the error message:
                $this->log(
                    $ex->getMessage(),
                    LogLevel::ALERT,
                    \Airship\throwableToArray($ex)
                );
                // continue;
            }
        }
        // When all else fails, TransferException
        throw new TransferException(
            \__("All else has failed.")
        );
    }

    /**
     * Get key updates from the channel
     *
     * @param MerkleTree $tree
     * @return Node[]
     */
    protected function getKeyUpdates(MerkleTree $tree): array
    {
        $newNodes = [];

        foreach ($this->getChannelUpdates($tree->getRoot()) as $new) {
            $newNode = new Node($new['data']);
            $tree = $tree->getExpandedTree($newNode);

            // Verify that we've calculated the same Merkle root for each new leaf:
            if (\hash_equals($new['root'], $tree->getRoot())) {
                // Attempt to store the update (and create/revoke copies of the public keys):
                if ($this->storeUpdate($new)) {
                    $newNodes[] = $newNode;
                }
            }
        }
        if (\count($newNodes) > 0) {
            $this->notifyPeersOfNewUpdate();
        }

        return $newNodes;
    }

    /**
     * Get the current Merkle tree for our active channel.
     *
     * @return MerkleTree
     */
    protected function getMerkleTree(): MerkleTree
    {
        $nodeList = [];
        $queryString = 'SELECT data FROM airship_tree_updates WHERE channel = ? ORDER BY treeupdateid ASC';
        foreach ($this->db->run($queryString, $this->channel) as $node) {
            $nodeList []= new Node($node['data']);
        }
        return (new MerkleTree(...$nodeList))
            ->setHashSize(
                \Sodium\CRYPTO_GENERICHASH_BYTES_MAX
            )
            ->setPersonalizationString(
                \AIRSHIP_BLAKE2B_PERSONALIZATION
            );
    }

    /**
     * Get the Merkle tree with key updates factored in.
     *
     * @return MerkleTree
     */
    protected function getUpdatedMerkleTree(): MerkleTree
    {
        $originalTree = $this->getMerkleTree();
        $newNodes = $this->getKeyUpdates($originalTree);
        if (empty($newNodes)) {
            return $originalTree;
        }
        return $originalTree->getExpandedTree(...$newNodes);
    }

    /**
     * We are creating a new key
     *
     * @param array $keyData
     * @param array $nodeData
     * @return bool
     * @throws InvalidType
     */
    protected function insertKey(array $keyData, array $nodeData): bool
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_\-]/', '', $keyData['supplier']);
        if (empty($supplier)) {
            throw new InvalidType(
                \__('Expected non-empty string for supplier name.')
            );
        }
        $filePath = ROOT . '/config/supplier_keys/' . $supplier . '.json';

        if (\file_exists($filePath)) {
            $supplierData = \Airship\loadJSON($filePath);
            if (!$this->verifyMasterSignature($supplierData, $keyData, $nodeData)) {
                return false;
            }

            // Create new entry
            $supplierData['signing_keys'][] = [
                'type' => $keyData['type'] ?? 'signing',
                'public_key' => $keyData['public_key']
            ];
            return \file_put_contents(
                $filePath,
                \json_encode($supplierData, JSON_PRETTY_PRINT)
            ) !== false;
        } elseif ($keyData['type'] === 'master') {
            // The supplier's first key.
            $supplierData = [
                'channels' => [
                    $this->channel
                ],
                'signing_keys' => [
                    [
                        'type' => 'master',
                        'public_key' => $keyData['public_key']
                    ]
                ]
            ];
            return \file_put_contents(
                $filePath,
                \json_encode($supplierData, JSON_PRETTY_PRINT)
            ) !== false;
        }
        // Fail closed:
        return false;
    }

    /**
     * This propagates the new update through the network.
     */
    protected function notifyPeersOfNewUpdate()
    {
        $state = State::instance();
        if (IDE_HACKS) {
            $state->hail = new Hail(new Client());
        }
        $resp = [];
        $peers = \Airship\loadJSON(ROOT . '/config/channel_peers/' . $this->channel . '.json');
        foreach ($peers as $peer) {
            foreach ($peer['urls'] as $url) {
                $resp []= $state->hail->getAsync($url, [
                    'challenge' => Base64UrlSafe::encode(\random_bytes(21))
                ]);
            }
        }
        foreach ($resp as $r) {
            $r->then(function (ResponseInterface $response) {
                $body = (string) $response->getBody();
                $context = \json_decode(Binary::safeSubstr($body, 89));
                $this->log(
                    'Peer notified of channel update',
                    LogLevel::INFO,
                    $context
                );
            });
        }
    }

    /**
     * Parse the HTTP response and get the useful information out of it.
     *
     * @param array $data
     * @param \DateTime $originated
     * @return array
     * @throws CouldNotUpdate
     */
    protected function parseChannelUpdateResponse(
        array $data,
        \DateTime $originated
    ): array {
        if ($data['status'] !== 'success') {
            throw new CouldNotUpdate(
                $data['message'] ?? \__('An update error has occurred')
            );
        }
        $valid = [];
        if (!empty($data['no_updates'])) {
            // Verify signature of the "no updates" timestamp.
            if (!AsymmetricCrypto::verify($data['no_updates'], $this->channelPublicKey, $data['signature'])) {
                throw new CouldNotUpdate(
                    \__('Invalid signature from channel')
                );
            }
            $time = (new \DateTime($data['no_updates']))->add(new \DateInterval('P01D'));
            if ($time < $originated) {
                throw new CouldNotUpdate(
                    \__('Channel is reporting a stale "no update" status')
                );
            }
            // No updates.
            return [];
        }
        // Verify the signature of each update.
        foreach ($data['updates'] as $update) {
            $data = Base64UrlSafe::decode($update['data']);
            if (AsymmetricCrypto::verify($data, $this->channelPublicKey, $update['signature'])) {
                $dataInternal = \json_decode($data, true);
                $valid[] = [
                    'id' => (int) $update['id'],
                    'stored' => $dataInternal['stored'],
                    'master_signature' => $dataInternal['master_signature'],
                    'root' => $dataInternal['root'],
                    'data' => $dataInternal['data']
                ];
            }
        }
        // Sort by ID
        \uasort($valid, function (array $a, array $b): int {
            return (int) ($a['id'] <=> $b['id']);
        });
        return $valid;
    }

    /**
     * We are removing a key from our trust store.
     *
     * @param array $keyData
     * @param array $nodeData
     * @return bool
     * @throws InvalidType
     */
    protected function revokeKey(array $keyData, array $nodeData): bool
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_\-]/', '', $keyData['supplier']);
        if (empty($supplier)) {
            throw new InvalidType(
                \__('Expected non-empty string for supplier name')
            );
        }
        $filePath = ROOT . '/config/supplier_keys/' . $supplier . '.json';

        if (\file_exists($filePath)) {
            $supplierData = \Airship\loadJSON($filePath);
            if (!$this->verifyMasterSignature($supplierData, $keyData, $nodeData)) {
                return false;
            }

            // Remove the revoked key.
            foreach ($supplierData['signing_keys'] as $i => $key) {
                if (\hash_equals($keyData['public_key'], $key['public_key'])) {
                    unset($supplierData['signing_keys'][$i]);
                    break;
                }
            }
            return \file_put_contents(
                $filePath,
                \json_encode($supplierData, JSON_PRETTY_PRINT)
            ) !== false;
        }
        // Fail closed:
        return false;
    }

    /**
     * Store the new update in the database.
     *
     * @param array $nodeData
     * @return bool
     */
    protected function storeUpdate(array $nodeData): bool
    {
        $this->db->beginTransaction();
        $treeUpdateID = (int) $this->db->insertGet(
            'airship_tree_updates',
            [
                'channel' => $this->channel,
                'channelupdateid' => $nodeData['id'],
                'data' => $nodeData['data'],
                'merkleroot' => $nodeData['root']
            ],
            'treeupdateid'
        );
        $unpacked = \json_decode($nodeData['data'], true);
        switch ($nodeData['stored']['action']) {
            case 'CORE':
            case 'PACKAGE':
                if (!$this->updatePackageQueue($unpacked, $treeUpdateID)) {
                    $this->db->rollBack();
                    return false;
                }
                break;
            case 'CREATE':
                if (!$this->insertKey($unpacked, $nodeData)) {
                    $this->db->rollBack();
                    return false;
                }
                break;
            case 'INSERT':
                if (!$this->revokeKey($unpacked, $nodeData)) {
                    $this->db->rollBack();
                    return false;
                }
                break;
            default:
                // Unknown operation. Do nothing and abort.
                $this->db->rollBack();
                return false;
        }
        return $this->db->commit();
    }

    /**
     * We're storing metadata about a package in the database.
     *
     * @param array $pkgData
     * @param int $treeUpdateID
     * @return bool
     * @throws \Airship\Alerts\Database\DBException
     * @throws \TypeError
     */
    protected function updatePackageQueue(array $pkgData, int $treeUpdateID): bool
    {
        $this->db->beginTransaction();
        $packageId = $this->db->cell(
            "SELECT
                  packageid 
             FROM
                  airship_package_cache
             WHERE 
                 packagetype = ?
                 AND supplier = ?
                 AND name = ? 
            ",
            $pkgData['pkg_type'],
            $pkgData['supplier'],
            $pkgData['name']
        );
        if (empty($packageId)) {
            $packageId = $this->db->insertGet(
                'airship_package_cache',
                [
                    'packagetype' =>
                        $pkgData['pkg_type'],
                    'supplier' =>
                        $pkgData['supplier'],
                    'name' =>
                        $pkgData['name']
                ],
                'packageid'
            );
        }
        $this->db->insert(
            'airship_package_versions',
            [
                'package' =>
                    $packageId,
                'version' =>
                    $pkgData['version'],
                'checksum' =>
                    $pkgData['checksum'],
                'commithash' =>
                    $pkgData['commit'],
                'date_released' =>
                    $pkgData['date_released'],
                'treeupdateid' =>
                    $treeUpdateID

            ]
        );
        return $this->db->commit();
    }

    /**
     * Verify that this key update was signed by the master key for this supplier.
     *
     * @param array $supplierData
     * @param array $keyData
     * @param array $nodeData
     * @return bool
     */
    protected function verifyMasterSignature(
        array $supplierData,
        array $keyData,
        array $nodeData
    ): bool {
        $masterData = \json_decode($nodeData['master'], true);
        if ($masterData === false) {
            return false;
        }

        foreach ($supplierData['signing_keys'] as $key) {
            if ($key['type'] !== 'master') {
                continue;
            }
            if (\hash_equals($keyData['public_key'], $masterData['public_key'])) {
                $publicKey = new SignaturePublicKey(
                    \Sodium\hex2bin($masterData['public_key'])
                );
                $message = \json_encode($keyData);

                // If the signature is valid, we return true.
                return AsymmetricCrypto::verify(
                    $message,
                    $publicKey,
                    $masterData['signature']
                );
            }
        }
        // Fail closed.
        return false;
    }
}
