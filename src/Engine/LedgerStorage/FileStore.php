<?php
declare(strict_types=1);
namespace Airship\Engine\LedgerStorage;

use \Airship\Engine\Contract\{
    DBInterface,
    LedgerStorageInterface
};

class FileStore implements LedgerStorageInterface
{
    const FILE_FORMAT = 'Y-m-d.\l\o\g';
    const TIME_FORMAT = 'Y-m-d\TH:i:s';
    
    protected $basedir;
    protected $fileFormat;
    protected $timeFormat;
    
    public function __construct(
        string $baseDirectory = '',
        string $logfileFormat = self::FILE_FORMAT,
        string $timeFormat = self::TIME_FORMAT
    ) {
        if (\strlen($baseDirectory) < 2) {
            $this->basedir = ROOT.'/tmp/logs/';
        } else {
            $this->basedir = $baseDirectory;
        }
        if (!\is_dir($this->basedir)) {
            \mkdir($this->basedir, 0775);
        }
        $this->fileFormat = $logfileFormat;
        $this->timeFormat = $timeFormat;
    }
    
    /**
     * Store a log message -- used by Ledger
     * 
     * @param string $level
     * @param string $message
     * @param string $context (JSON encoded)
     */
    public function store(string $level, string $message, string $context)
    {
        $now = new \DateTime('now');
        $filename = $now->format($this->fileFormat);
        
        \touch($this->basedir . DIRECTORY_SEPARATOR . $filename);
        $file = \realpath($this->basedir . DIRECTORY_SEPARATOR . $filename);
        if ($file === false) {
            throw new \Airship\Alerts\FileSystem\AccessDenied(
                \trk('errors.file.lfi')
            );
        }
        if (\strpos($file, $this->basedir) === false) {
            header('Content-Type: text/plain');
            throw new \Airship\Alerts\FileSystem\AccessDenied(
                \trk('errors.file.lfi')
            );
        }
        
        if (!\file_exists($file)) {
            \touch($file);
            \chmod($file, 0770);
        }
        
        $time = $now->format($this->timeFormat);
        
        return \file_put_contents(
            $file,
            $time . 
                "\t" . \preg_replace('#[^a-z]*#', '', $level) .
                "\t" . \json_encode($message) .
                "\t" . $context .
                "\n",
            FILE_APPEND
        );
    }
}
