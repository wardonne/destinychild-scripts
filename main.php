<?php
declare(strict_types=1);

class DCScripts 
{
    private const PCK_EXE = __DIR__ . '/bin/PCK.exe';

    private const UPLOAD_PATH = __DIR__ . '/upload';

    private const CHARACTER_BASEPATH = __DIR__ . '/source/character';

    private const CHARACTER_OUTPUT_PATH = __DIR__ . '/output/character';

    private const CHARACTER_UPLOAD_PATH = __DIR__ . '/upload/character';

    private const ITEM_ICON_BASEPATH = __DIR__ . '/source/icon/item';

    private const ITEM_ICON_OUTPUT_PATH = __DIR__ . '/output/icon/item';

    private const PORTRAIT_BATTLE_ICON_BASEPATH = __DIR__ . '/source/icon/portrait_battle';

    private const PORTRAIT_BATTLE_ICON_OUTPUT_PATH = __DIR__ . '/output/icon/portrait_battle';

    private const SPA_ICON_BASEPATH = __DIR__ . '/source/icon/spa';

    private const SPA_ICON_OUTPUT_PATH = __DIR__ . '/output/icon/spa';

    private const SOUL_CARTA_BASEPATH = __DIR__ . '/source/soul_carta';

    private const SOUL_CARTA_OUTPUT_PATH = __DIR__ . '/output/soul_carta';

    private const SOUL_CARTA_UPLOAD_PATH = __DIR__ . '/upload/soul_carta';

    private const LOCALE_PCK = __DIR__ . '/source/locale.pck';

    private const LOCALE_OUTPUT = __DIR__ . '/output/locale';

    private const CHARACTER_DESCRIPTION_TXT = self::LOCALE_OUTPUT . '/locale/00000011.unk';

    private const ARCHIVE_ZIP_PATH = __DIR__ . '/dc.zip';

    private const SFTP_AUTH_CONFIG = __DIR__ . '/auth.json';

    private array $fileHash = [];

    private array $unpacked = [];

    private bool $skipUnpack;

    private bool $skipCollate;

    private bool $skipDataGenerate;

    private bool $skipArchive;

    public function __construct(
        bool $skipUnpack = false, 
        bool $skipCollate = false,
        bool $skipDataGenerate = false,
        bool $skipArchive = false
    ) {
        $this->skipUnpack = $skipUnpack;
        $this->skipCollate = $skipCollate;
        $this->skipDataGenerate = $skipDataGenerate;
        $this->skipArchive = $skipArchive;
    }

    private function loadHash() : void
    {
        if(is_file('hash.json')) {
            $this->fileHash = json_decode(file_get_contents('hash.json'), true);
        }
    }

    public function store()
    {
        chdir(__DIR__);
        file_put_contents('hash.json', json_encode($this->fileHash, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        file_put_contents('unpacked.json', json_encode($this->unpacked, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

    private function createAndEnterDir(string $path) : void
    {
        if(!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        chdir($path);
    }

    private function unpackCharacters() 
    {
        $this->output('Unpacking characters');
        $this->createAndEnterDir(self::CHARACTER_OUTPUT_PATH);
        $this->loopUnpackDir(self::CHARACTER_BASEPATH, '/^(s?c|ig)\d+_\d+.pck$/');
        $this->output('Unpacked characters');
    }

    private function unpackSoulCartas()
    {
        $this->output('Unpacking soul cartas');
        $this->createAndEnterDir(self::SOUL_CARTA_OUTPUT_PATH);
        $this->loopUnpackDir(self::SOUL_CARTA_BASEPATH);
        $this->output('Unpacked soul cartas');
    }

    private function unpackItemIcon()
    {
        $this->output('Unpacking item icons');
        $this->createAndEnterDir(self::ITEM_ICON_OUTPUT_PATH);
        $this->loopUnpackDir(self::ITEM_ICON_BASEPATH, '/^(pc|ig)\d+.pck$/');
        $this->output('Unpacked item icons');
    }

    private function unpackSpaIcon()
    {
        $this->output('Unpacking spa icons');
        $this->createAndEnterDir(self::SPA_ICON_OUTPUT_PATH);
        $this->loopUnpackDir(self::SPA_ICON_BASEPATH, '/^sc\d+.pck$/');
        $this->output('Unpacked spa icons');
    }

    private function unpackPortraitBattle()
    {
        $this->output('Unpacking portrait battle icons');
        $this->createAndEnterDir(self::PORTRAIT_BATTLE_ICON_OUTPUT_PATH);
        $this->loopUnpackDir(self::PORTRAIT_BATTLE_ICON_BASEPATH, '/^c\d+.pck$/');
        $this->output('Unpacked portrait battle icons');
    }

    private function unpackLocale() 
    {
        $this->output('Unpacking locale');
        $this->createAndEnterDir(self::LOCALE_OUTPUT);
        $this->unpack(self::LOCALE_PCK);
        $this->output('Unpacked locale');
    }

    private function loopUnpackDir(string $dirpath, ?string $include = null, ?string $exclude = null) : void
    {
        $dirEntries = scandir($dirpath);
        foreach($dirEntries as $dirEntry) {
            if(
                ($dirEntry == '.' || $dirEntry == '..') 
                || is_dir($dirEntry)
                || (!is_null($include) && !preg_match($include, $dirEntry)) 
                || (!is_null($exclude) && preg_match($exclude, $dirEntry))
            ) {
                continue;
            }
            $pckPath = $dirpath . '/' . $dirEntry;
            $this->unpack($pckPath);
        }
    }

    private function unpack(string $pck) 
    {
        $relativePath = str_replace(__DIR__, '', $pck);
        if(($this->fileHash[$relativePath] ?? '') == md5_file($pck)) {
            return;
        }
        $this->output("Unpacking $pck");
        $unpackCommand = implode(' ', [self::PCK_EXE, '/F', '/L', $pck]);
        $this->execute($unpackCommand);
        $headerPath = getcwd() . '/' . explode('.', basename($pck))[0] . '/_header';
        if(is_file($headerPath)) {
            unlink($headerPath);
        }
        $this->unpacked[] = $relativePath;
        $this->fileHash[$relativePath] = md5_file($pck);
        $this->output("Unpacked $pck");
    }

    private function execute(string $command) 
    {
        $handle = popen($command, 'r');
        while (!feof($handle)) {
            $line = fgets($handle);
            if($line !== false) {
                $this->output($line, false);
            }
        }
        pclose($handle);
    }

    private function output(string $message, bool $eol = true) : void
    {
        echo $message, $eol ? PHP_EOL : '';
    }

    private function copyDir(string $from, string $to) : void
    {
        $copyCommand = implode(' ', [
            'xcopy', 
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR ,$from), 
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $to), 
            '/Y', 
            '/E', 
            '/I', 
            '/Q',
            '/D',
        ]);
        $this->execute($copyCommand);
    }

    private function rmdir(string $dirpath) 
    {
        $rmdirCommand = implode(' ', ['rmdir', str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dirpath), '/S', '/Q', ]);
        $this->execute($rmdirCommand);
    }

    private function collateCharacterResource() 
    {
        $this->output('Collating character resource');
        $live2dPath = self::CHARACTER_UPLOAD_PATH . '/live2d';
        if(!is_dir(dirname($live2dPath))) {
            mkdir(dirname($live2dPath), 0777, true);
        }

        $dirEntries = scandir(self::CHARACTER_OUTPUT_PATH);
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            if(!preg_match('/^s?c\d+_\d+$/', $dirEntry)) {
                continue;
            }
            $charLive2DPath = self::CHARACTER_OUTPUT_PATH . '/' . $dirEntry;
            $this->copyDir($charLive2DPath, $live2dPath . '/' . $dirEntry);
        }
        
        $portraitBattlePath = self::CHARACTER_UPLOAD_PATH . '/avatars/battle_avatar';
        if(!is_dir(dirname($portraitBattlePath))) {
            mkdir(dirname($portraitBattlePath), 0777, true);
        }
        $this->copyDir(self::PORTRAIT_BATTLE_ICON_OUTPUT_PATH, $portraitBattlePath);

        $spaPath = self::CHARACTER_UPLOAD_PATH . '/avatars/spa';
        $this->copyDir(self::SPA_ICON_OUTPUT_PATH, $spaPath);
        $this->output('Collated character resources');
    }

    private function collateSoulCartaResource()
    {
        $this->output('Collating soul carta resources');
        $avatarPath = self::SOUL_CARTA_UPLOAD_PATH . '/avatars';
        if(is_dir($avatarPath)) {
            $this->rmdir($avatarPath);
        }
        if(!is_dir(dirname($avatarPath))) {
            mkdir(dirname($avatarPath), 0777, true);
        }
        $this->copyDir(self::ITEM_ICON_OUTPUT_PATH, $avatarPath);

        $imagePath = self::SOUL_CARTA_UPLOAD_PATH . '/images';
        if(!is_dir(dirname($imagePath))) {
            mkdir(dirname($imagePath), 0777, true);
        }
        $this->copyDir(self::SOUL_CARTA_OUTPUT_PATH, $imagePath);

        $live2dPath = self::SOUL_CARTA_UPLOAD_PATH . '/live2d';
        if(!is_dir(dirname($live2dPath))) {
            mkdir(dirname($live2dPath), 0777, true);
        }
        $dirEntries = scandir(self::CHARACTER_OUTPUT_PATH);
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            if(!preg_match('/^ig\d+_\d+$/', $dirEntry)) {
                continue;
            }

            $charLive2DPath = self::CHARACTER_OUTPUT_PATH . '/' . $dirEntry;
            if(is_dir($live2dPath . '/' . $dirEntry)) {
                $this->rmdir($live2dPath . '/' . $dirEntry);
            }
            $this->copyDir($charLive2DPath, $live2dPath . '/' . $dirEntry);
        }
        $this->output('Collated soul carta resources');
    }

    private function generateCharacterList()
    {
        $this->output('Generating character data file');
        $avatars = [];
        $dirEntries = scandir(self::CHARACTER_UPLOAD_PATH . '/avatars/battle_avatar');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $avatarEntries = scandir(self::CHARACTER_UPLOAD_PATH . '/avatars/battle_avatar/' . $dirEntry);
            foreach($avatarEntries as $avatarEntry) {
                if($avatarEntry === '.' || $avatarEntry === '..') {
                    continue;
                }
                $avatars[] = 'battle_avatar/' . $dirEntry . '/' . $avatarEntry;
            }
        }

        $spaAvatars = [];
        $dirEntries = scandir(self::CHARACTER_UPLOAD_PATH . '/avatars/spa');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $spaAvatarEntries = scandir(self::CHARACTER_UPLOAD_PATH . '/avatars/spa/' . $dirEntry);
            foreach($spaAvatarEntries as $spaAvatarEntry) {
                if($spaAvatarEntry === '.' || $spaAvatarEntry === '..') {
                    continue;
                }
                $spaAvatars[] = 'spa/' . $dirEntry . '/' . $spaAvatarEntry;
            }
        }

        $live2ds = [];
        $dirEntries = scandir(self::CHARACTER_UPLOAD_PATH . '/live2d');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $live2ds[] = $dirEntry;
        }

        $descriptions = [];
        $file = fopen(self::CHARACTER_DESCRIPTION_TXT, 'r');
        while (!feof($file)) {
            $line = fgets($file);
            if($line !== false) {
                if(trim($line) && substr(trim($line), 0, 2) !== '//') {
                    $parts = array_values(array_filter(explode("\t", trim($line))));
                    $descriptions[$parts[0]] = [
                        'code' => $parts[0],
                        'name' => trim($parts[1], '_'),
                        'description' => $parts[2] ?? '',
                    ];
                }
            }
        }

        $characters = [];
        foreach($live2ds as $index => $live2d) {
            list($maybeCharacter, $_) = explode('_', $live2d);
            $character = $maybeCharacter;
            $isSpa = false;
            if($maybeCharacter[0] === 's') {
                $character = substr($maybeCharacter, 1);
                $isSpa = true;
            }
            $description = $descriptions[$live2d] ?? [];
            $item = $characters[$character] ?? ['skins' => []];
            if($isSpa) {
                $item['spring'] = [
                    'code' => $live2d,
                    'avatar' => array_shift($spaAvatars),
                    'name' => str_replace('_', '', $description['name'] ?? ''),
                    'description' => $description['description'] ?? '',
                    'live2d' => $live2d,
                ];
            } else {
                $skin = [
                    'code' => $live2d,
                    'avatar' => array_shift($avatars),
                    'name' => $description['name'] ?? '',
                    'description' => $description['description'] ?? '',
                    'live2d' => $live2d,
                ];
                $item['skins'][] = $skin;
            }
            $characters[$character] = $item;
        }

        foreach($characters as $index => $character) {
            $skinCount = count($character['skins']);
            if($skinCount === 0) {
                unset($characters[$index]);
                continue;
            }
            $defaultSkin = $character['skins'][0];
            if($index !== 'c000') {
                if($skinCount >= 3) {
                    $defaultSkin = $character['skins'][2];
                } else {
                    $defaultSkin = end($character['skins']);
                }
            }
            $characters[$index] = [
                'name' => explode('_', $character['skins'][0]['name'] ?? '')[1] ?? $character['skins'][0]['name'] ?? '',
                'avatar' => $defaultSkin['avatar'] ?? '',
                'skins' => $character['skins'],
            ];
            if(isset($character['spring'])) {
                $characters[$index]['spring'] = $character['spring'];
            }
            foreach($characters[$index]['skins'] as &$skin) {
                $skin['name'] = str_replace('_', '', $skin['name']);
            }
        }
        
        file_put_contents(self::CHARACTER_UPLOAD_PATH . '/data.json', json_encode(array_values($characters), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->output('Generated character data file');
    }

    private function generateSoulCartaList()
    {
        $this->output('Generating soul carta data file');
        $avatars = [];
        $dirEntries = scandir(self::SOUL_CARTA_UPLOAD_PATH . '/avatars');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $avatarEntries = scandir(self::SOUL_CARTA_UPLOAD_PATH . '/avatars/' . $dirEntry);
            foreach($avatarEntries as $avatarEntry) {
                if($avatarEntry === '.' || $avatarEntry === '..') {
                    continue;
                }
                $avatars[] = $dirEntry . '/' . $avatarEntry;
            }
        }

        $images = [];
        $dirEntries = scandir(self::SOUL_CARTA_UPLOAD_PATH . '/images');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $imageEntries = scandir(self::SOUL_CARTA_UPLOAD_PATH . '/images/' . $dirEntry);
            foreach($imageEntries as $imageEntry) {
                if($imageEntry === '.' || $imageEntry === '..') {
                    continue;
                }
                $images[] = $dirEntry . '/' . $imageEntry;
            }
        }

        $live2ds = [];
        $dirEntries = scandir(self::SOUL_CARTA_UPLOAD_PATH . '/live2d');
        foreach($dirEntries as $dirEntry) {
            if($dirEntry === '.' || $dirEntry === '..') {
                continue;
            }
            $live2ds[] = $dirEntry;
        }

        $soulCartas = [];
        foreach($avatars as $index => $avatar) {
            $isLive2D = preg_match('/ig\d+/', explode('/', $avatar)[0]);
            $soulCarta = [
                'name' => '',
                'avatar' => $avatar,
                'image' => $images[$index],
                'use_live2d' => boolval($isLive2D),
                'live2d' => $isLive2D ? array_shift($live2ds) : '',
            ];
            $soulCartas[] = $soulCarta;
        }

        file_put_contents(self::SOUL_CARTA_UPLOAD_PATH . '/data.json', json_encode($soulCartas, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->output('Generated soul carta data file');
    }

    private function archive()
    {
        $this->output('Archiving zip');
        $zip = new ZipArchive();
        $zip->open(self::ARCHIVE_ZIP_PATH, ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $fileList = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::UPLOAD_PATH));
        foreach($fileList as $file) {
            if(!$file->isDir()) {
                $filepath = $file->getRealPath();
                $relativePath = substr($filepath, strlen(self::UPLOAD_PATH)+1);
                $this->output('Add: ' . $filepath);
                $zip->addFile($filepath, $relativePath);
            }
        }
        $zip->close();
        $this->output('Archived zip');
    }

    private function upload()
    {
        $configuration = json_decode(file_get_contents(self::SFTP_AUTH_CONFIG), true);
        $this->output('Connecting: ' . $configuration['host'] . ':' . $configuration['port']);
        $session = ssh2_connect($configuration['host'], $configuration['port']);
        if($session === false) {
            throw new Exception('Can\'t connect to ' . $configuration['host'] . ':' . $configuration['port']);
        }
        $this->output('Connected');
        $this->output('Login authenticating');
        if(!ssh2_auth_pubkey_file($session, $configuration['username'], $configuration['pubkey'], $configuration['prikey'])) {
            throw new Exception('Can\'t pass login authentication');
        }
        $this->output('Authenticated');
        $this->output('Opening SFTP Connection');
        $sftp = ssh2_sftp($session);
        if($sftp === false) {
            throw new Exception('Can\'t open sftp');
        }
        $this->output('Opened SFTP');
        $this->output('Uploading: ' . self::ARCHIVE_ZIP_PATH);
        $remotePath = $configuration['remotePath'] . '/' . date('Y-m-d');
        $stat = @ssh2_sftp_stat($sftp, $remotePath);
        if($stat === false) {
            ssh2_sftp_mkdir($sftp, $remotePath, 0777, true);
        }
        ssh2_scp_send($session, self::ARCHIVE_ZIP_PATH, $remotePath . '/' . basename(self::ARCHIVE_ZIP_PATH), 0777);
        $this->output('Uploaded');
        ssh2_exec($session, 'exit');
        $this->output('Exit SFTP');
    }

    private function push() : void
    {
        chdir(self::UPLOAD_PATH);
        $addCommand = implode(' ', ['git', 'add', '.']);
        $this->execute($addCommand);
        $commitCommand = implode(' ', ['git', 'commit', '-m', sprintf('"%s"', 'update-' . date('Y-m-d H:i:s'))]);
        $this->execute($commitCommand);
        $pushCommand = implode(' ', ['git', 'push']);
        $this->execute($pushCommand);
    }

    public function run() : void
    {
        $this->loadHash();
        
        if(!$this->skipUnpack) {
            $this->unpackCharacters();
            $this->unpackSoulCartas();
            $this->unpackItemIcon();
            $this->unpackSpaIcon();
            $this->unpackPortraitBattle();
            $this->unpackLocale();
        }

        $this->store();
    
        if(!$this->skipCollate) {
            $this->collateCharacterResource();
            $this->collateSoulCartaResource();
        }
        
        if(!$this->skipDataGenerate) {
            $this->generateCharacterList();
            $this->generateSoulCartaList();
        }
        
        // if(!$this->skipArchive) {
        //     $this->archive();
        // }
        // $this->push();
        // $this->clear();
    }

    private function clear()
    {
        $this->rmdir(__DIR__ . '/output');
    }
}

function main() {
    if(version_compare(PHP_VERSION, '7.4.0', '<')) {
        echo 'php version ^7.4.0 is required', PHP_EOL;
        exit();
    }
    try {
        $skipUnpack = false;
        $skipCollate = false;
        $skipDataGenerate = false;
        $skipArchive = true;

        $scripts = new DCScripts(
            $skipUnpack, 
            $skipCollate, 
            $skipDataGenerate,
            $skipArchive,
        );

        sapi_windows_set_ctrl_handler(function ($event) use ($scripts) {
            if($event === PHP_WINDOWS_EVENT_CTRL_C) {
                throw new Exception('Cancelled');
            }
        });

        $scripts->run();
    } catch(Throwable $e) {
        $scripts->store();
        echo $e->getMessage(), PHP_EOL;
        echo $e->getTraceAsString(), PHP_EOL;
        exit();
    }
}

main();