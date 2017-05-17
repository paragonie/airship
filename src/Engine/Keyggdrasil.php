<?php
declare(strict_types=1);

namespace Airship\Engine;

use Airship\Alerts\Continuum\{
    ChannelSignatureFailed,
    CouldNotUpdate,
    PeerSignatureFailed
};
use Airship\Alerts\Hail\SignatureFailed;
use Airship\Engine\{
    Bolt\Supplier as SupplierBolt,
    Bolt\Log as LogBolt,
    Contract\DBInterface
};
use Airship\Engine\Continuum\{
    API,
    Channel,
    Log
};
use Airship\Engine\Keyggdrasil\{
    Peer,
    TreeUpdate
};
use GuzzleHttp\Exception\TransferException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Halite\{
    Asymmetric\Crypto as AsymmetricCrypto,
    Structure\MerkleTree,
    Structure\Node
};
use Psr\Log\LogLevel;

/**
 * Class Keygdrassil
 *
 * (Yggdrasil = "world tree")
 *
 * This synchronizes our public keys for each channel with the rest of the
 * network, taking care to verify that a random subset of trusted peers sees
 * the same keys. We also keep the checksums and version identifiers of all
 * extensions in the Merkle tree.
 *
 * @package Airship\Engine\Continuum
 */
class Keyggdrasil
{
    use SupplierBolt;
    use LogBolt;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var Log
     */
    protected static $continuumLogger;

    /**
     * @var Hail
     */
    protected $hail;

    /**
     * @var array<string, \Airship\Engine\Continuum\Supplier>
     */
    protected $supplierCache;

    /**
     * @var array<mixed, Channel>
     */
    protected $channelCache;

    /**
     * Keyggdrasil constructor.
     *
     * @param Hail|null $hail
     * @param DBInterface|null $db
     * @param array $channels
     */
    public function __construct(
        Hail $hail = null,
        DBInterface $db = null,
        array $channels = []
    ) {
        $config = State::instance();
        if (empty($hail)) {
            $this->hail = $config->hail;
        } else {
            $this->hail = $hail;
        }

        if (empty($db)) {
            $db = \Airship\get_database();
        }
        $this->db = $db;

        foreach ($channels as $ch => $config) {
            $this->channelCache[$ch] = new Channel($this, $ch, $config);
        }

        if (!self::$continuumLogger) {
            self::$continuumLogger = new Log($this->db, 'keyggdrasil');
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
            if ($response['status'] !== 'OK') {
                $this->log(
                    'Upstream error.',
                    LogLevel::EMERGENCY,
                    [
                        'response' => $response
                    ]
                );
                return false;
            }
            // Decode then verify signature
            $message = Base64UrlSafe::decode($response['response']);
            $signature = $response['signature'];
            $isValid = AsymmetricCrypto::verify(
                $message,
                $peer->getPublicKey(),
                $signature
            );
            if (!$isValid) {
                $this->log(
                    'Invalid digital signature (i.e. it was signed with an incorrect key).',
                    LogLevel::EMERGENCY
                );
                throw new PeerSignatureFailed(
                    'Invalid digital signature (i.e. it was signed with an incorrect key).'
                );
            }

            // Make sure our challenge was signed.
            $decoded = \json_decode($message, true);
            if (!\hash_equals($challenge, $decoded['challenge'])) {
                $this->log(
                    'Challenge-response authentication failed.',
                    LogLevel::EMERGENCY
                );
                throw new CouldNotUpdate(
                    \__('Challenge-response authentication failed.')
                );
            }

            // Make sure this was a recent signature (it *should* be).

            // The earliest timestamp we will accept from a peer:
            $min = (new \DateTime('now'))
                ->sub(new \DateInterval('P01D'));

            // The timestamp the server provided:
            $time = new \DateTime($decoded['timestamp']);

            if ($time < $min) {
                throw new CouldNotUpdate(
                    \__(
                        'Timestamp %s is far too old.', 'default',
                        $decoded['timestamp']
                    )
                );
            }

            // Return TRUE if it matches the expected root.
            // Return FALSE if it matches.
            return \hash_equals(
                $expectedRoot,
                $decoded['root']
            );
        }
        // When all else fails, throw a TransferException
        throw new TransferException();
    }

    /**
     * Launch the update process.
     *
     * This updates our keys for each channel.
     *
     * @return void
     */
    public function doUpdate(): void
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
     * @throws \TypeError
     */
    protected function fetchTreeUpdates(
        Channel $chan,
        string $url,
        string $root
    ): array {
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
            if (!($state->logger instanceof Ledger)) {
                throw new \TypeError(
                    \trk('errors.type.wrong_class', Ledger::class)
                );
            }
            $state->logger->alert(
                'Signature failed!',
                \Airship\throwableToArray($ex)
            );
        }
        return [];
    }

    /**
     * Given a TreeUpdate, return an array formatted for logging.
     *
     * @param TreeUpdate $up
     * @return array
     */
    protected function getLogData(TreeUpdate $up): array
    {
        return [
            'is' => [
                'core' =>
                    $up->isAirshipUpdate(),
                'package' =>
                    $up->isPackageUpdate(),
                'newKey' =>
                    $up->isCreateKey(),
                'revoke' =>
                    $up->isRevokeKey()
            ],
            'merkleRoot' =>
                $up->getRoot(),
            'type' =>
                $up->isPackageUpdate() || $up->isAirshipUpdate()
                    ? $up->getPackageType()
                    : $up->getKeyType(),
            'supplier' =>
                $up->getSupplierName(),
            'name' =>
                $up->getPackageName(),
            'data' =>
                $up->getNodeData()
        ];
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
        $nodes = $this->db->run(
            'SELECT
                 data
             FROM
                 airship_tree_updates
             WHERE
                 channel = ?
             ORDER BY
                 treeupdateid ASC
            ',
            (string) $chan->getName()
        );
        foreach ($nodes as $node) {
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
     * @return void
     */
    protected function insertKey(Channel $chan, TreeUpdate $update): void
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
     * Dear future security auditors: This is important.
     *
     * @param Channel $chan
     * @param array $response
     * @return TreeUpdate[]
     * @throws ChannelSignatureFailed
     * @throws CouldNotUpdate
     */
    protected function parseTreeUpdateResponse(
        Channel $chan,
        array $response
    ): array {
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

            // One day ago:
            $stale = (new \DateTime('now'))
                ->sub(new \DateInterval('P01D'));

            if ($datetime < $stale) {
                throw new CouldNotUpdate(
                    \__('Stale response.')
                );
            }

            // We got nothing to do:
            return [];
        }

        // We were given updates. Let's validate them!
        $TreeUpdateArray = [];
        foreach ($response['updates'] as $update) {
            $data = Base64UrlSafe::decode($update['data']);
            $sig = $update['signature'];
            $signatureVerified = AsymmetricCrypto::verify(
                $data,
                $chan->getPublicKey(),
                $sig
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
                return (int) ($a->getChannelId() <=> $b->getChannelId());
            }
        );
        return $TreeUpdateArray;
    }

    /**
     * Insert/delete entries in supplier_keys, while updating the database.
     *
     * Dear future security auditors: This is important.
     *
     * @param Channel $chan
     * @param array<int, TreeUpdate> $updates
     * @return bool
     */
    protected function processTreeUpdates(
        Channel $chan,
        TreeUpdate ...$updates
    ): bool {
        $this->db->beginTransaction();
        foreach ($updates as $update) {
            // Insert the new node in the database:
            $treeUpdateID = (int) $this->db->insertGet(
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
                self::$continuumLogger->store(
                    LogLevel::INFO,
                    'New public key',
                    [
                        'action' => 'KEYGGDRASIL',
                        'supplier' => $update->getSupplierName(),
                        'publicKey' => $update->getPublicKeyString(),
                        'merkleRoot' => $update->getRoot(),
                        'data' => $this->getLogData($update)
                    ]
                );
            } elseif ($update->isRevokeKey()) {
                $this->revokeKey($chan, $update);
                self::$continuumLogger->store(
                    LogLevel::INFO,
                    'Public key revoked',
                    [
                        'action' => 'KEYGGDRASIL',
                        'supplier' => $update->getSupplierName(),
                        'publicKey' => $update->getPublicKeyString(),
                        'merkleRoot' => $update->getRoot(),
                        'data' => $this->getLogData($update)
                    ]
                );
            } else {
                $this->updatePackageQueue($update, $treeUpdateID);
                self::$continuumLogger->store(
                    LogLevel::INFO,
                    'New package metadata',
                    [
                        'action' => 'KEYGGDRASIL',
                        'supplier' => $update->getSupplierName(),
                        'merkleRoot' => $update->getRoot(),
                        'data' => $this->getLogData($update)
                    ]
                );
            }
        }
        return $this->db->commit();
    }

    /**
     * We're storing a new public key for this supplier.
     *
     * @param Channel $chan
     * @param TreeUpdate $update
     * @return void
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
     * @return void
     */
    protected function updateChannel(Channel $chan): void
    {
        // The original Merkle Tree (and root) from the last-known-good state.
        $originalTree = $this->getMerkleTree($chan);
        $originalRoot = $originalTree->getRoot();
        foreach ($chan->getAllURLs() as $url) {
            try {
                /**
                 * Fetch all the alleged updates from the server, which are to
                 * be treated with severe skepticism. The updates we see here
                 * are, at least, authenticated by the server API's signing key
                 * which means we're commmunicating with the correct channel.
                 *
                 * @var TreeUpdate[]
                 */
                $updates = $this->fetchTreeUpdates(
                    $chan,
                    $url,
                    $originalRoot
                );
                if (empty($updates)) {
                    // Nothing to do.
                    return;
                }

                /**
                 * We'll keep retrying until we're in a good state or we have
                 * no more candidate updates left to add to our tree.
                 */
                while (!empty($updates)) {
                    $merkleTree = $originalTree->getExpandedTree();
                    // Verify these updates with our Peers.
                    try {
                        if ($this->verifyResponseWithPeers(
                            $chan,
                            $merkleTree,
                            ...$updates
                        )) {
                            // Apply these updates:
                            $this->processTreeUpdates($chan, ...$updates);
                            return;
                        }
                        // If we're here, verification failed
                    } catch (CouldNotUpdate $ex) {
                        /**
                         * Something bad happened. Possibilities:
                         *
                         * 1. One of our peers disagreed with the Merkle root
                         *    that we saw, so we aborted for safety.
                         * 2. Too many network failures occurred, so we aborted
                         *    to prevent a DoS to create a false consensus.
                         *
                         * Log the details, then the loop will continue.
                         */
                        $this->log(
                            $ex->getMessage(),
                            LogLevel::ALERT,
                            \Airship\throwableToArray($ex)
                        );
                        $subsequent = [];
                        foreach ($updates as $up) {
                            if ($up instanceof TreeUpdate) {
                                $subsequent[] = $this->getLogData($up);
                            }
                        }
                        self::$continuumLogger->store(
                            LogLevel::ALERT,
                            $ex->getMessage(),
                            [
                                'action' => 'KEYGGDRASIL',
                                'baseRoot' => $originalRoot,
                                'subsequent' => $subsequent
                            ]
                        );
                    }
                    // If verification fails, pop off the last update and try again
                    \array_pop($updates);
                }
                // Received a successful API response.
                return;
            } catch (ChannelSignatureFailed $ex) {
                /**
                 * We can't even trust the channel's API response. An error
                 * occurred. We aborte entirely at this step.
                 *
                 * This may mean a MITM attacker with a valid CA certificate.
                 * This may mean a server-side error.
                 *
                 * Log and abort; don't try to automate any decisions based
                 * on a strange network state.
                 */
                $this->log(
                    'Invalid Channel Signature for ' . $chan->getName(),
                    LogLevel::ALERT,
                    \Airship\throwableToArray($ex)
                );
                self::$continuumLogger->store(
                    LogLevel::ALERT,
                    'Invalid Channel Signature for ' . $chan->getName(),
                    [
                        'action' => 'KEYGGDRASIL'
                    ]
                );
            } catch (TransferException $ex) {
                /**
                 * Typical network error. Maybe an HTTP 5xx response code?
                 *
                 * Either way: log and abort.
                 */
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
     * @return void
     */
    protected function updatePackageQueue(TreeUpdate $update, int $treeUpdateID): void
    {
        $packageId = $this->db->cell(
            'SELECT
                  packageid 
             FROM
                  airship_package_cache
             WHERE 
                 packagetype = ?
                 AND supplier = ?
                 AND name = ? 
            ',
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
                    $data['commit'] ?? null,
                'date_released' =>
                    $data['date_released'],
                'treeupdateid' =>
                    $treeUpdateID
            ]
        );
    }

    /**
     * Return true if the Merkle roots match.
     *
     * Dear future security auditors: This is important.
     *
     * This employs challenge-response authentication:
     * @ref https://github.com/paragonie/airship/issues/13
     *
     * @param Channel $channel
     * @param MerkleTree $originalTree
     * @param array<int, TreeUpdate> $updates
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
            self::$continuumLogger->store(
                LogLevel::EMERGENCY,
                'Calculated Merkle root did not match the update.',
                [
                    $tree->getRoot(),
                    $expectedRoot
                ]
            );
            throw new CouldNotUpdate(
                \__('Calculated Merkle root did not match the update.')
            );
        }

        if ($state->universal['auto-update']['ignore-peer-verification']) {
            // The user has expressed no interest in verification
            return true;
        }

        $peers = $channel->getPeerList();
        $numPeers = \count($peers);

        /**
         * These numbers are negotiable in future versions.
         *
         * If P is the set of trusted peer notaries (where ||P|| is the number
         * of trusted peer notaries):
         *
         * 1. At least 1 must return 'success'.
         * 2. At least ln(||P||) must return 'success'.
         * 3. At most e * ln(||P||) can timeout.
         * 4. If any peer disagrees with what we see, our
         *    result is discarded as invalid.
         *
         * The most harm a malicious peer can do is DoS if they
         * are selected.
         */
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

        /**
         * If any peers give a different answer, we're under attack.
         * If too many peers don't respond, assume they're being DDoS'd.
         * If enough peers respond in absolute agreement, we're good.
         */
        for ($i = 0; $i < $numPeers; ++$i) {
            try {
                if (!$this->checkWithPeer($peers[$i], $tree->getRoot())) {
                    // Merkle root mismatch? Abort.
                    return false;
                }
                ++$success;
            } catch (TransferException $ex) {
                self::$continuumLogger->store(
                    LogLevel::EMERGENCY,
                    'A transfer exception occurred',
                    \Airship\throwableToArray($ex)
                );
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
        self::$continuumLogger->store(
            LogLevel::EMERGENCY,
            'We ran out of peers.',
            [
                $numPeers,
                $minSuccess,
                $maxFailure
            ]
        );
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
