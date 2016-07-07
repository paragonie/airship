<?php
use Airship\Engine\LedgerStorage\{
    DBStore,
    FileStore
};
use Airship\Engine\{
    Gears,
    State
};

/**
 * Configure the application event logger here
 */
$log_setup_closure = function() {
    
    $state = State::instance();
    $loggerClass = Gears::getName('Ledger');
    $args = [];
    
    /**
     * Here we build our logger storage class
     */
    switch ($state->universal['ledger']['driver']) {
        case 'file':
            $path = $state->universal['ledger']['path'];
            if (\strlen($path) >= 2) {
                if ($path[0] === '~' && $path[1] === '/') {
                    $path = ROOT.'/'.substr($path, 2);
                }
            }
            $storage = new FileStore(
                $path,
                $state->universal['ledger']['file-format'] ?? FileStore::FILE_FORMAT,
                $state->universal['ledger']['time-format'] ?? FileStore::TIME_FORMAT
            );
            break;
        case 'database':
            $path = $state->universal['ledger']['connection'];
            $storage = new DBStore(
                $path,
                $state->universal['ledger']['table'] ?? DBStore::DEFAULT_TABLE
            );
            break;
        default:
            throw new \Exception('Invalid logger storage mechansim');
    }
    
    /**
     * We inject any more dependencies here:
     */
    $logger = new $loggerClass($storage, ...$args);
    $state->logger = $logger;
};
$log_setup_closure();
unset($log_setup_closure);