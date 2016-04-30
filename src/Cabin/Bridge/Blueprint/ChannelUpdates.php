<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Blueprint;

use \Airship\Alerts\Continuum\CouldNotUpdate;
use \Airship\Engine\{
    Continuum\API,
    Database,
    Hail,
    State
};
use \GuzzleHttp\{
    Client,
    Exception\TransferException,
    Psr7\Response
};
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Asymmetric\SignaturePublicKey,
    Asymmetric\SignatureSecretKey,
    Structure\MerkleTree,
    Structure\Node
};
use Psr\Http\Message\ResponseInterface;
use \Psr\Log\LogLevel;

require_once __DIR__.'/gear.php';

/**
 * Class ChannelUpdates
 *
 * Manage key updates from a particular channel
 *
 * @package Airship\Cabin\Bridge\Blueprint
 */
class ChannelUpdates extends BlueprintGear
{
    private $channel;
    private $channelPublicKey;
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
            'timestamp' => $now->format('Y-m-d\TH:i:s')
        ];
        $response = \json_encode($updates);
        return [
            Base64UrlSafe::encode($response),
            Base64UrlSafe::encode(AsymmetricCrypto::sign($response, $sk, true))
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
                if (\strpos($url, '.onion') !== false) {
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
            $response = $state->hail->post(
                $url . API::get('fetch_keys') . '/' . $root
            );
            if ($response instanceof Response) {
                $code = $response->getStatusCode();
                if ($code >= 200 && $code < 300) {
                    try {
                        return $this->parseChannelUpdateResponse(
                            (string) $response->getBody(),
                            $initiated
                        );
                    } catch (CouldNotUpdate $ex) {
                        $this->log(
                            $ex->getMessage(),
                            LogLevel::ALERT,
                            \Airship\throwableToArray($ex)
                        );
                        // continue;
                    }
                }
            }
        }
        // When all else fails, TransferException
        throw new TransferException();
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
        $queryString = 'SELECT data FROM airship_key_updates WHERE channel = ? ORDER BY keyupdateid ASC';
        foreach ($this->db->run($queryString, $this->channel) as $node) {
            $nodeList []= new Node($node['data']);
        }
        return new MerkleTree(...$nodeList);
    }

    /**
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
     */
    protected function insertKey(array $keyData, array $nodeData): bool
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_\-]/', '', $keyData['supplier']);
        if (\file_exists(ROOT . '/config/supplier_keys/' . $supplier . '.json')) {
            /**
             * @todo verify master signature before inserting.
             */
        } else {
            /**
             * @todo make sure this is the first, master key for the user
             */
        }
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
                $context = \json_decode((string) $response->getBody());
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
     * @param string $raw
     * @param \DateTime $originated
     * @return array
     * @throws CouldNotUpdate
     */
    protected function parseChannelUpdateResponse(string $raw, \DateTime $originated): array
    {
        $data = \json_decode($raw, true);
        if ($data['status'] !== 'success') {
            throw new CouldNotUpdate($data['message'] ?? 'An update has occurred');
        }
        $valid = [];
        if (!empty($data['no_updates'])) {
            // Verify signature of the "no updates" timestamp.
            $sig = Base64UrlSafe::decode($data['signature']);
            if (!AsymmetricCrypto::verify($data['no_updates'], $this->channelPublicKey, $sig, true)) {
                throw new CouldNotUpdate('Invalid signature from channel');
            }
            $time = (new \DateTime($data['no_updates']))->add(new \DateInterval('PT01D'));
            if ($time < $originated) {
                throw new CouldNotUpdate('Channel is reporting a stale "no update" status');
            }
            // No updates.
            return [];
        }
        // Verify the signature of each update.
        foreach ($data['updates'] as $update) {
            $data = Base64UrlSafe::decode($update['data']);
            $sig = Base64UrlSafe::decode($update['signature']);
            if (AsymmetricCrypto::verify($data, $this->channelPublicKey, $sig, true)) {
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
        \uasort($valid, function (array $a, array $b): array {
            return $a['id'] <=> $b['id'];
        });
        return $valid;
    }

    /**
     * We are marking a key as invalid, and never trusting it again.
     *
     * @param array $keyData
     * @param array $nodeData
     * @return bool
     */
    protected function revokeKey(array $keyData, array $nodeData): bool
    {

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
        $this->db->insert(
            'airship_key_updates',
            [
                'channel' => $this->channel,
                'channelupdateid' => $nodeData['id'],
                'data' => $nodeData['data'],
                'merkleroot' => $nodeData['root']
            ]
        );
        $unpacked = \json_decode($nodeData['data'], true);
        switch ($unpacked['action']) {
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
        }
        return $this->db->commit();
    }
}
