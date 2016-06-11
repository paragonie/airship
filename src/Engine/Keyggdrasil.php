<?php
declare(strict_types=1);

namespace Airship\Engine;

use \Airship\Alerts\Continuum\{
    ChannelSignatureFailed,
    CouldNotUpdate,
    PeerSignatureFailed
};
use \Airship\Alerts\Hail\SignatureFailed;
use \Airship\Engine\{
    Bolt\Supplier as SupplierBolt,
    Bolt\Log,
    Contract\DBInterface
};
use \Airship\Engine\Continuum\{
    API,
    Channel,
    Supplier
};
use \Airship\Engine\Keyggdrasil\{
    Peer,
    TreeUpdate
};
use \GuzzleHttp\Exception\TransferException;
use \ParagonIE\ConstantTime\Base64UrlSafe;
use \ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Structure\MerkleTree,
    Structure\Node
};
use \Psr\Log\LogLevel;

/**
 * Class Keygdrassil
 *
 * (Yggdrasil = "world tree")
 *
 * This synchronizes our public keys for each channel with the rest of the network,
 * taking care to verify that a random subset of trusted peers sees the same keys.
 *
 * @package Airship\Engine\Continuum
 */
class Keyggdrasil
{
    use SupplierBolt;
    use Log;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var Hail
     */
    protected $hail;

    /**
     * @var Supplier[]
     */
    protected $supplierCache;

    /**
     * @var Channel[]
     */
    protected $channelCache;

    /**
     * Keyggdrasil constructor.
     *
     * @param Hail|null $hail
     * @param DBInterface|null $db
     * @param array $channels
     */
    public function __construct(Hail $hail = null, DBInterface $db = null, array $channels = [])
    {
        $config = State::instance();
        if (empty($hail)) {
            $this->hail = $config->hail;
        } else {
            $this->hail = $hail;
        }

        if (!$db) {
            $db = \Airship\get_database();
        }
        $this->db = $db;

        foreach ($channels as $ch => $config) {
            $this->channelCache[$ch] = new Channel($this, $ch, $config);
        }
    }

    /**
     * Does this peer notary see the same Merkle root?
     *
     * @param Peer $peer
     * @param string $expectedRoot
     * @return bool
     * @throws CouldNotUpdate
     * @throws PeerSignatureFailed
     */
    protected function checkWithPeer(Peer $peer, string $expectedRoot): bool
    {
        foreach ($peer->getAllURLs('/verify') as $url) {
            // Challenge nonce:
            $challenge = Base64UrlSafe::encode(\random_bytes(33));
            // Peer's response:
            $response = $this->hail->postJSON($url, ['challenge' => $challenge]);
            if ($response['status'] === 'OK') {
                // Decode then verify signature
                $message = Base64UrlSafe::decode($response['response']);
                $signature = Base64UrlSafe::decode($response['signature']);
                if (!AsymmetricCrypto::verify($message, $peer->getPublicKey(), $signature, true)) {
                    throw new PeerSignatureFailed(
                        'Invalid digital signature (i.e. it was signed with an incorrect key).'
                    );
                }
                // Make sure our challenge was signed.
                $decoded = \json_decode($message, true);
                if (!\hash_equals($challenge, $decoded['challenge'])) {
                    throw new CouldNotUpdate(
                        'Challenge-response authentication failed.'
                    );
                }
                // Make sure this was a recent signature (it *should* be):
                $min = (new \DateTime('now'))
                    ->sub(new \DateInterval('P01D'));
                $time = new \DateTime($decoded['timestamp']);
                if ($time < $min) {
                    throw new CouldNotUpdate(
                        'Timestamp ' . $decoded['timestamp'] . ' is far too old.'
                    );
                }

                // Return TRUE if it matches the expected root.
                // Return FALSE if it matches.
                return \hash_equals(
                    $expectedRoot,
                    $decoded['root']
                );
            } else {
                return false;
            }
            // If we're still here, Guzzle failed.
        }
        // When all else fails, throw a TransferException
        throw new TransferException();
    }

    /**
     * Launch the update process.
     *
     * This updates our keys for each channel.
     */
    public function doUpdate()
    {
        if (empty($this->channelCache)) {
            return;
        }
        foreach ($this->channelCache as $chan) {
            $this->updateChannel($chan);
        }
    }

    /**
     * Fetch all of the updates from the remote server.
     *
     * @param Channel $chan
     * @param string $url
     * @param string $root Which Merkle root are we starting at?
     * @return TreeUpdate[]
     */
    protected function fetchTreeUpdates(Channel $chan, string $url, string $root): array
    {
        try {
            return $this->parseTreeUpdateResponse(
                $chan,
                $this->hail->getSignedJSON(
                    $url . API::get('fetch_keys') . '/' . $root,
                    $chan->getPublicKey()
                )
            );
        } catch (SignatureFailed $ex) {
            $state = State::instance();
            if (IDE_HACKS) {
                $state->logger = new Ledger();
            }
            $state->logger->alert('Signature failed!', \Airship\throwableToArray($ex));
            return [];
        }
    }

    /**
     * Get the tree of existing Merkle roots.
     *
     * @param Channel $chan
     * @return MerkleTree
     */
    protected function getMerkleTree(Channel $chan): MerkleTree
    {
        $nodeList = [];
        $queryString = 'SELECT data FROM airship_tree_updates WHERE channel = ? ORDER BY treeupdateid ASC';
        foreach ($this->db->run($queryString, $chan->getName()) as $node) {
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
     * We're storing a new public key for this supplier.
     *
     * @param Channel $chan
     * @param TreeUpdate $update
     */
    protected function insertKey(Channel $chan, TreeUpdate $update)
    {
        $supplier = $update->getSupplier();
        $name = $supplier->getName();
        $file = ROOT . '/config/supplier_keys/' . $name . '.json';
        $supplierData = \Airship\loadJSON($file);

        $supplierData['signing_keys'][] = [
            'type' => $update->getKeyType(),
            'public_key' => $update->getPublicKeyString()
        ];
        \Airship\saveJSON($file, $supplierData);
        \clearstatcache();

        // Flush the channel's supplier cache
        $chan->getSupplier($name, true);
    }

    /**
     * Interpret the TreeUpdate objects from the API response. OR verify the signature
     * of the "no updates" message to prevent a DoS.
     *
     * @param Channel $chan
     * @param array $response
     * @return TreeUpdate[]
     * @throws ChannelSignatureFailed
     * @throws CouldNotUpdate
     */
    protected function parseTreeUpdateResponse(Channel $chan, array $response): array
    {
        if (!empty($response['no_updates'])) {
            // The "no updates" message should be authenticated.
            $signatureVerified = AsymmetricCrypto::verify(
                $response['no_updates'],
                $chan->getPublicKey(),
                Base64UrlSafe::decode($response['signature']),
                true
            );
            if (!$signatureVerified) {
                throw new ChannelSignatureFailed();
            }
            $datetime = new \DateTime($response['no_updates']);

            // One hour ago:
            $stale = (new \DateTime('now'))
                ->sub(new \DateInterval('PT01H'));

            if ($datetime < $stale) {
                throw new CouldNotUpdate('Stale response.');
            }

            // We got nothing to do:
            return [];
        }

        $TreeUpdateArray = [];
        foreach ($response['updates'] as $update) {
            $data = Base64UrlSafe::decode($update['data']);
            $sig = Base64UrlSafe::decode($update['signature']);
            $signatureVerified = AsymmetricCrypto::verify(
                $data,
                $chan->getPublicKey(),
                $sig,
                true
            );
            if (!$signatureVerified) {
                // Invalid signature
                throw new ChannelSignatureFailed();
            }
            // Now that we know it was signed by the channel, time to update
            $TreeUpdateArray[] = new TreeUpdate(
                $chan,
                \json_decode($data, true)
            );
        }
        // Sort by ID
        \uasort(
            $TreeUpdateArray,
            function (TreeUpdate $a, TreeUpdate $b): int
            {
                return $a->getChannelId() <=> $b->getChannelId();
            }
        );
        return $TreeUpdateArray;
    }

    /**
     * Insert/delete entries in supplier_keys, while updating the database.
     *
     * @param Channel $chan)
     * @param TreeUpdate[] $updates
     * @return bool
     */
    protected function processTreeUpdates(Channel $chan, TreeUpdate ...$updates): bool
    {
        $this->db->beginTransaction();
        foreach ($updates as $update) {
            // Insert the new node in the database:
            $treeUpdateID = (int) $this->db->insert(
                'airship_tree_updates',
                [
                    'channel' => $chan->getName(),
                    'channelupdateid' => $update->getChannelId(),
                    'data' => $update->getNodeJSON(),
                    'merkleroot' => $update->getRoot()
                ],
                'treeupdateid'
            );

            // Update the JSON files separately:
            if ($update->isCreateKey()) {
                $this->insertKey($chan, $update);
            } elseif ($update->isRevokeKey()) {
                $this->revokeKey($chan, $update);
            } else {
                $this->updatePackageQueue($update, $treeUpdateID);
            }
        }
        return $this->db->commit();
    }

    /**
     * We're storing a new public key for this supplier.
     *
     * @param Channel $chan
     * @param TreeUpdate $update
     */
    protected function revokeKey(Channel $chan, TreeUpdate $update)
    {
        $supplier = $update->getSupplier();
        $name = $supplier->getName();
        $file = ROOT . '/config/supplier_keys/' . $name . '.json';
        $supplierData = \Airship\loadJSON($file);

        foreach ($supplierData['signing_keys'] as $id => $skey) {
            if (\hash_equals($skey['public_key'], $update->getPublicKeyString())) {
                // Remove this key
                unset($supplierData['signing_keys'][$id]);
                break;
            }
        }
        \Airship\saveJSON($file, $supplierData);
        \clearstatcache();

        // Flush the channel's supplier cache
        $chan->getSupplier($name, true);
    }

    /**
     * Update a particular channel.
     *
     * 1. Identify a working URL for the channel.
     * 2. Query server for updates.
     * 3. For each update:
     *    1. Verify that our trusted notaries see the same update.
     *       (Ed25519 signature of challenge nonce || Merkle root)
     *    2. Add/remove the supplier's key.
     *
     * @param Channel $chan
     */
    protected function updateChannel(Channel $chan)
    {
        $originalTree = $this->getMerkleTree($chan);
        $originalRoot = $originalTree->getRoot();
        foreach ($chan->getAllURLs() as $url) {
            try {
                $updates = $this->fetchTreeUpdates(
                    $chan,
                    $url,
                    $originalRoot
                ); // TreeUpdate[]
                if (empty($updates)) {
                    return;
                }
                while (!empty($updates)) {
                    $merkleTree = $originalTree->getExpandedTree();
                    // Verify these updates with our Peers.
                    try {
                        if ($this->verifyResponseWithPeers($chan, $merkleTree, ...$updates)) {
                            // Apply these updates:
                            $this->processTreeUpdates($chan, ...$updates);
                            return;
                        }
                        // If we're here, verification failed
                    } catch (CouldNotUpdate $ex) {
                        $this->log(
                            $ex->getMessage(),
                            LogLevel::ALERT,
                            \Airship\throwableToArray($ex)
                        );
                    }
                    // If verification fails, pop off the last update and try again
                    \array_pop($updates);
                }
                // Received a successful API response.
                return;
            } catch (ChannelSignatureFailed $ex) {
                $this->log(
                    'Invalid Channel Signature for ' . $chan->getName(),
                    LogLevel::ALERT,
                    \Airship\throwableToArray($ex)
                );
            } catch (TransferException $ex) {
                $this->log(
                    'Channel update error',
                    LogLevel::NOTICE,
                    \Airship\throwableToArray($ex)
                );
            }
        }
        // IF we get HERE, we've run out of updates to try.

        $this->log('Channel update concluded with no changes', LogLevel::ALERT);
    }

    /**
     * We're storing metadata about a package in the database.
     *
     * @param TreeUpdate $update
     * @param int $treeUpdateID
     */
    protected function updatePackageQueue(TreeUpdate $update, int $treeUpdateID)
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
            $update->getPackageType(),
            $update->getSupplierName(),
            $update->getPackageName()
        );
        if (empty($packageId)) {
            $packageId = $this->db->insertGet(
                'airship_package_cache',
                [
                    'packagetype' =>
                        $update->getPackageType(),
                    'supplier' =>
                        $update->getSupplierName(),
                    'name' =>
                        $update->getPackageName()
                ],
                'packageid'
            );
        }
        $data = $update->getNodeData();
        $this->db->insert(
            'airship_package_versions',
            [
                'package' =>
                    $packageId,
                'version' =>
                    $data['version'],
                'checksum' =>
                    $data['checksum'],
                'commithash' =>
                    $data['commit'],
                'date_released' =>
                    $data['date_released']
                    
            ]
        );
        $this->db->commit();
    }

    /**
     * Return true if the Merkle roots match.
     *
     * This employs challenge-response authentication:
     * @ref https://github.com/paragonie/airship/issues/13
     *
     * @param Channel $channel
     * @param MerkleTree $originalTree
     * @param TreeUpdate[] ...$updates
     * @return bool
     * @throws CouldNotUpdate
     */
    protected function verifyResponseWithPeers(
        Channel $channel,
        MerkleTree $originalTree,
        TreeUpdate ...$updates
    ): bool {
        $state = State::instance();
        $nodes = $this->updatesToNodes($updates);
        $tree = $originalTree->getExpandedTree(...$nodes);

        $maxUpdateIndex = \count($updates) - 1;
        $expectedRoot = $updates[$maxUpdateIndex]->getRoot();
        if (!\hash_equals($tree->getRoot(), $expectedRoot)) {
            // Calculated root did not match.
            throw new CouldNotUpdate(
                'Calculated Merkle root did not match the update.'
            );
        }

        if ($state->universal['auto-update']['ignore-peer-verification']) {
            // The user has expressed no interest in verification
            return true;
        }

        $peers = $channel->getPeerList();
        $numPeers = \count($peers);

        $minSuccess = $channel->getAppropriatePeerSize();
        $maxFailure = (int) \min(
            \floor($minSuccess * M_E),
            $numPeers - 1
        );
        if ($maxFailure < 1) {
            $maxFailure = 1;
        }
        \Airship\secure_shuffle($peers);

        $success = $networkError = 0;

        for ($i = 0; $i < $numPeers; ++$i) {
            try {
                if (!$this->checkWithPeer($peers[$i], $tree->getRoot())) {
                    // Merkle root mismatch? Abort.
                    return false;
                }
                ++$success;
            } catch (TransferException $ex) {
                ++$networkError;
            }

            if ($success >= $minSuccess) {
                // We have enough good responses.
                return true;
            } elseif ($networkError >= $maxFailure) {
                // We can't give a confident response here.
                return false;
            }
        }
        // Fail closed:
        return false;
    }

    /**
     * Get a bunch of nodes for inclusion in the Merkle tree.
     *
     * @param TreeUpdate[] $updates
     * @return Node[]
     */
    protected function updatesToNodes(array $updates): array
    {
        $return = [];
        foreach ($updates as $up) {
            $return []= new Node($up->getNodeJSON());
        }
        return $return;
    }
}
