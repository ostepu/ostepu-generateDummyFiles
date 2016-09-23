<?php
#region GenerateDummyFiles
class GenerateDummyFiles
{
    private static $initialized=false;
    public static $name = 'generateDummyFiles';
    public static $installed = false;
    public static $page = 8;
    public static $rank = 100;
    public static $enabledShow = true;
    private static $langTemplate='GenerateDummyFiles';

    public static $onEvents = array(
                                    'listFiles'=>array(
                                        'name'=>'listFiles',
                                        'event'=>array('actionListFiles'),
                                        'procedure'=>'installListFiles',
                                        'enabledInstall'=>true
                                        ),
                                    'generateFiles'=>array(
                                        'name'=>'generateFiles',
                                        'event'=>array('actionGenerateFiles'),
                                        'procedure'=>'installGenerateFiles',
                                        'enabledInstall'=>true
                                        )
                                    );

    public static function getDefaults($data)
    {
        $res = array();
        return $res;
    }

    public static function init($console, &$data, &$fail, &$errno, &$error)
    {
        Installation::log(array('text'=>Installation::Get('main','functionBegin')));
        Language::loadLanguageFile('de', self::$langTemplate, 'json', dirname(__FILE__).'/');
        Installation::log(array('text'=>Installation::Get('main','languageInstantiated')));

        self::$initialized = true;
        Installation::log(array('text'=>Installation::Get('main','functionEnd')));
    }

    public static function show($console, $result, $data)
    {
        if (!Einstellungen::$accessAllowed) return;

        Installation::log(array('text'=>Installation::Get('main','functionBegin')));
        $text='';
        $text .= Design::erstelleBeschreibung($console,Installation::Get('main','description',self::$langTemplate));

        if (self::$onEvents['listFiles']['enabledInstall']){
            $text .= Design::erstelleZeile($console, Installation::Get('listFiles','listDesc',self::$langTemplate), 'e',  Design::erstelleSubmitButton(self::$onEvents['listFiles']['event'][0],Installation::Get('listFiles','list',self::$langTemplate)), 'h');
        }

        if (isset($result[self::$onEvents['listFiles']['name']])){
            $content = $result[self::$onEvents['listFiles']['name']]['content'];
            if (!isset($content['filesTypes'])){
                $content['filesTypes'] = array();
            }
            
            if (!isset($content['missingFiles'])){
                $content['missingFiles'] = array();
            }
            
            if (count($content['filesTypes'])>0){
                $text .= Design::erstelleZeile($console, '','','','' );
                $text .= Design::erstelleZeile($console, Installation::Get('listFiles','summary',self::$langTemplate),'e' );
            }

            $all = 0;
            $existing = 0;
            foreach($content['filesTypes'] as $key => $amount){
                $all += $amount;
                $name = $key;
                if ($name === 'unknown'){
                    $name = Installation::Get('listFiles','unknown',self::$langTemplate);
                }
                $existingFiles = $amount - (isset($content['missingFiles'][$key]) ? $content['missingFiles'][$key] : 0);
                $existing+=$existingFiles;
                $text .= Design::erstelleZeile($console, $name , 'e', $existingFiles.'/'.$amount, 'v');
            }
            
            if (count($content['filesTypes'])>0){
                $text .= Design::erstelleZeile($console, Installation::Get('listFiles','sum',self::$langTemplate),'e',$existing.'/'.$all,'v_c' );
                $text .= Design::erstelleZeile($console, '','','','' );
            }

            if (empty($content['filesTypes'])){
                $text .= Design::erstelleZeile($console, '','e',Installation::Get('listFiles','noData',self::$langTemplate),'v_c' );
            }
        }
        
        if (self::$onEvents['generateFiles']['enabledInstall']){
            $text .= Design::erstelleZeile($console, Installation::Get('generateFiles','generateDesc',self::$langTemplate), 'e',  Design::erstelleSubmitButton(self::$onEvents['generateFiles']['event'][0],Installation::Get('generateFiles','generate',self::$langTemplate)), 'h');
        }
        
        if (isset($result[self::$onEvents['generateFiles']['name']])){
            $content = $result[self::$onEvents['generateFiles']['name']]['content'];
            if (!isset($content['generatedFiles'])){
                $content['generatedFiles'] = 0;
            }
            
            $text .= Design::erstelleZeile($console, '','','','' );
            $text .= Design::erstelleZeile($console, Installation::Get('generateFiles','generatedFiles',self::$langTemplate),'e',$content['generatedFiles'],'v_c' );
            $text .= Design::erstelleZeile($console, '','','','' );
        }
        
        echo Design::erstelleBlock($console, Installation::Get('main','title',self::$langTemplate), $text);

        Installation::log(array('text'=>Installation::Get('main','functionEnd')));
        return null;
    }
    
    public static function getFileType($file)
    {
        // der Typ der Datei wird anhand des mimeType oder des Namens ermittelt
        if (isset($file['mimeType'])){
            if ($file['mimeType'] === 'application/pdf') return 'PDF';
        
        }
        
        $ext = strtolower(pathinfo($file['displayName'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') return 'PDF';
        if ($ext === 'java') return 'JAVA';
        if ($ext === 'txt') return 'TEXT';
        if ($ext === 'hs') return 'HASKELL';
        
        if (isset($file['mimeType'])){
            if ($file['mimeType'] === 'text/plain') return 'TEXT';
        
        }     

        if (!isset($file['mimeType'])){
            return 'TEXT';
        
        }
        return false;
    }

    public static function installListFiles($data, &$fail, &$errno, &$error)
    {
        Installation::log(array('text'=>Installation::Get('main','functionBegin')));
        $res = array('filesTypes'=>array('unknown'=>0), 'missingFiles'=>array('unknown'=>0));

        $list = Einstellungen::getLinks('getFiles', dirname(__FILE__), '/tgeneratefiles_cconfig.json');

        $multiRequestHandle = new Request_MultiRequest();

        for ($i=0;$i<count($list);$i++){
            // collect files
            $handler = Request_CreateRequest::createGet($list[$i]->getAddress(). '/file',array(), '');
            $multiRequestHandle->addRequest($handler);
        }

        $answer = $multiRequestHandle->run();
        
        for ($i=0;$i<count($list);$i++){
            $result = $answer[$i];
            if (isset($result['content']) && isset($result['status']) && $result['status'] === 200){
                $result['content'] = json_decode($result['content'], true);
                foreach ($result['content'] as $file){
                    // ermittle den Typ der Datei
                    $type = self::getFileType($file); // bestimme den Dateityp
                    if ($type !== false){
                        if (!isset($res['filesTypes'][$type])) $res['filesTypes'][$type] = 0;
                        $res['filesTypes'][$type]++;
                    } else {
                        $res['filesTypes']['unknown']++;                        
                    }
                    
                    // prüfe, ob die Datei fehlt
                    $path = $data['PL']['files'] . DIRECTORY_SEPARATOR . $file['address'];
                    if (!file_Exists($path)){
                        if ($type !== false){
                            if (!isset($res['missingFiles'][$type])) $res['missingFiles'][$type] = 0;
                            $res['missingFiles'][$type]++;
                        } else {
                            $res['missingFiles']['unknown']++;
                        }
                    }
                }
                unset($result);
            } else {
                // Fehler ???
            }
        }
        unset($answer);

        Installation::log(array('text'=>Installation::Get('main','functionEnd')));
        return $res;
    }

    private static $cachedDummyData = array();
    public static function getDummyContent($type)
    {
        $dummyFile = 'unknown';
        if ($type === 'JAVA') $dummyFile = 'Hallo.java';
        if ($type === 'HASKELL') $dummyFile = 'Hallo.hs';
        if ($type === 'TEXT') $dummyFile = 'Hallo.txt';
        if ($type === 'PDF') $dummyFile = 'Hallo.pdf';
        
        if (isset($cachedDummyData[$type])) return $cachedDummyData[$type];
        $dummyPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'samples' . DIRECTORY_SEPARATOR . $dummyFile;
        if (!file_exists($dummyPath)) return null;
        $cachedDummyData[$type] = file_get_contents($dummyPath);
        return $cachedDummyData[$type];
    }
    
    public static function installGenerateFiles($data, &$fail, &$errno, &$error)
    {
        Installation::log(array('text'=>Installation::Get('main','functionBegin')));
        $res = array('generatedFiles'=>0);

        $list = Einstellungen::getLinks('getFiles', dirname(__FILE__), '/tgeneratefiles_cconfig.json');

        $multiRequestHandle = new Request_MultiRequest();

        for ($i=0;$i<count($list);$i++){
            // collect files
            $handler = Request_CreateRequest::createGet($list[$i]->getAddress(). '/file',array(), '');
            $multiRequestHandle->addRequest($handler);
        }
        

        $answer = $multiRequestHandle->run();
        
        for ($i=0;$i<count($list);$i++){
            $result = $answer[$i];
            if (isset($result['content']) && isset($result['status']) && $result['status'] === 200){
                $result['content'] = json_decode($result['content'], true);
                foreach ($result['content'] as $file){
                    // ermittle den Typ der Datei
                    $type = self::getFileType($file); // bestimme den Dateityp
                    
                    if ($type !== false){
                        // prüfe, ob die Datei fehlt
                        $path = $data['PL']['files'] . DIRECTORY_SEPARATOR . $file['address'];
                        if (!file_Exists($path)){
                            // füge den Dummy ein
                            Einstellungen::generatepath(dirname($path));
                            file_put_contents($path, self::getDummyContent($type));
                            $res['generatedFiles']++;
                        }
                    }
                }
                unset($result);
            } else {
                // Fehler ???
            }
        }
        unset($answer);

        Installation::log(array('text'=>Installation::Get('main','functionEnd')));
        return $res;
    }
}
#endregion GenerateDummyFiles