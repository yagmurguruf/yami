<?php
    require_once __DIR__ . '/lib/crypto.php';
    include('lib/pMongoData.php');
    //error_reporting(E_ALL);
// ini_set("display_errors", 1);
    include('classes.php');
    /*session_start ( [
        'cookie_lifetime' => 86400
    ] );*/

    const EXCLUDED_VPN_USERS = array("hgermiyan", "agoksu", "egenc", "bmarangoz", "maskin", "fgakansel", "gdogan", "bguruf", "eulcayli", "ceyuksel");

    define("DATEFORMAT", "d/m/Y");
    define("CACHE_SHORT", 6000);
    define("CACHE_LONG", 6000);
// define("DATEFORMAT", "16/10/2015");
    define("TIMEFORMAT", "H:i");

    const epocDateFix = 10800; //todo epoc değeri gmt+0 olarak tutulduğundan ve türkiye nin timezonu +3 olduğundan eklendi.

    $coookieSecKey = "3c5340eca1e0a1ac201e4ae648ba11f2";

    $blackListKeys = ['teklif', 'engagement', 'proposal', 'avukatlik-mektubu', 'offer', 'mutabakat', 'hukuki-danismanlik', 'engegement'];
    $elasticFields = array("User", "Author^2", "sourcefile.content", "CommitDesc^15", "RepoName^4", "DocName.keyword", "id", "fileName", "RowNumber");


    $settings = settings(true);
    $debug = false;
    $onlyAdminLogin = false;
    $timers = [];
    $version = $settings["site"]["version"];
    $counterPro = 0;
    $debuggers = [
        'fearslan',
        'acugur',
        'hgermiyan',
        'akamis'
    ];
    $dgr = null;
    $timers [] = array(
        'time' => time(),
        'method' => '---',
        'url' => 'LOAD'
    );

    if (class_exists('Memcached')) {
        $mem = new Memcached ();
        $mem->addServer("127.0.0.1", 11211);
        $timers [] = array(
            'time' => time(),
            'method' => '---',
            'url' => 'MEMCACHE LOADED'
        );
    }

    /***$blackListKeys deki kelimeler görülünce dosyalar gizli moda alınır***/

    function hideBlackList($group, $repo, $folder = "*", $file)
    {


        global $blackListKeys;

        $return = true;

        foreach ($blackListKeys as $key) {

            $key = renameForId($key);

            if (stristr($file, $key)) {

                $return = false;

            }


        }

        if (!$return) {

            removePrivate($group, $repo, $folder, $file);

            addPrivate($group, $repo, $folder, $file);

            $insert = array(
                'group_name' => $group,
                'repo_name' => $repo,
                'folder_name' => "$folder",
                'file_name' => $file,
                'date' => date('Y-m-d H:i:s'),
                'time' => time()
            );
            // //var_dump($insert); exit();
            $result = InsertToMongo('auto-privates', $insert);

            return $result;

        }
    }

    function settings($production = true)
    {
        $configPath = __DIR__ . "/config/config.ini";
        if (!file_exists($configPath)) {
            die("config/config.ini dosyası eksik!");
        } else {
            return parse_ini_file($configPath, true);

        }
    }


    function getIndexName($name)
    {
        global $settings;
        switch ($name) {
            case "gsiclient2" :
                return $settings['elastic'] ['indexForIgsi'];
                break;
            case "gsiclientv2e5" :
                return 'indexForIgsiDoc';
                break;
            case "gsicliente5" :
                return $settings['elastic'] ['indexForIgsi'];
                break;

            case "gsie5withstop" :
                return $settings['elastic'] ['index'];
                break;
            case "gsie5" :
                return $settings['elastic'] ['index'];
                break;
            case "gsiarsiv" :
                return $settings['elastic'] ['gsiarsiv'];
                break;
            case "gsiarsive5" :
                return $settings['elastic'] ['gsiarsiv'];
                break;
        }


        return $name;
    }


    function can_view_library($username)
    {
        $username = strtoupper($username);

        $search = array(
            'clientName' => $username
        );
        $library = CallMongo('library', $search);

        foreach ($library->response as $mresult) {
            return true;
        }

        return false;
    }

    function set_view_library($username, $status)
    {
        $username = strtoupper($username);

        $search = array(
            'clientName' => $username
        );
        if ($status) {
            $library = InsertToMongo('library', $search);
        } else {
            $library = RemoveFromMongo('library', $search);
        }

        return $library;
    }


    function set_view_library_mongo($username, $json_array)
    {
        $username = strtoupper($username);

        $whichData = array(
            'userName' => $username
        );

        $upData = array('$set' => $json_array);


        $library = UpdateToMongo('user', $whichData, $upData, 'gsiMongoGitData');
        return $library;
    }

    function documentAccessCount($filename, $duration = '5')
    {
        return count(documentAccessUsers($filename, $duration));
    }

    function documentAccessUsers($filename, $duration = '5')
    {
        $result = array();

        $currentDate = strtotime('now');
        $futureDate = $currentDate - (60 * $duration);
        $start = date("Y-m-d H:i:s", $futureDate);

        // //var_dump($start);

        $search = array(
            'filename' => $filename,
            'date' => array(
                '$gt' => $futureDate
            )
        );
        $access = CallMongo('activity_log', $search);

        foreach ($access->response as $mresult) {
            $result [$mresult ['user']] = $mresult ['user'];
        }

        return $result;
    }


    function renameForId($fileName)
    {

        // değiştirelecek türkçe karakterler
        $TR = array(
            'ç',
            'Ç',
            'ı',
            'İ',
            'ş',
            'Ş',
            'ğ',
            'Ğ',
            'ö',
            'Ö',
            'ü',
            'Ü'
        );
        $EN = array(
            'c',
            'c',
            'i',
            'i',
            's',
            's',
            'g',
            'g',
            'o',
            'o',
            'u',
            'u'
        );
        // türkçe karakterleri değiştirir
        $fileName = str_replace($TR, $EN, $fileName);
        // tüm karakterleri küçüklür strtolower
        // $baslik=mb_strtolower($baslik,'UTF-8');
        $fileName = strtolower($fileName);
        // a'dan z'ye olan harfler, 0'dan 9 a kadar sayılar, tire (-),
        // boşluk ve altçizgi (_) dışındaki tüm karakteri siler
        $fileName = preg_replace('#[^-a-zA-Z0-9_ ]#', '', $fileName);
        // cümle aralarındaki fazla boşluğü kaldırır
        $fileName = trim($fileName); // cümle aralarındaki
        // boşluğun yerine tire (-) koyar
        $fileName = preg_replace('#[-_ ]+#', '-', $fileName);

        return $fileName;
    }


    /*
     * function setReposToMyMemory(){
     * global $timers;
     * $_SESSION['repositories'] = getMyRepos();
     * $key = '/getuserrepos/'.current_user_name();
     *
     * $result = $_SESSION['repositories'];
     * if(class_exists('Memcache')){
     * global $mem;
     * $mem->set($key, $result,CACHE_LONG) or die("Memcache Hatası");
     * $timers[] = array(
     * 'time' => time(),
     * 'method' => '---',
     * 'url' => $key . ' SETTED TO MEMCACHE. TIMEOUT: ' . CACHE_LONG
     * );
     * }
     *
     *
     * }
     *
     */
    function CallMongo($collection, $query, $anotherdb = false, $order = false)
    {
        global $settings, $timers;

        $timers [] = array(
            'time' => time(),
            'method' => 'START MONGO',
            'url' => $collection
        );

        $dbhost = $settings ['mongo'] ['ip'];
        $dbname = $settings ['mongo'] ['db'];

        // allows to connect another database on mongodb
        if ($anotherdb !== false)
            $dbname = $anotherdb;

        try {
            // Connect to test database
            $m = new MongoDB\Client("mongodb://$dbhost");
            $db = $m->$dbname or die ('Error selecting database');

            // Get the users collection
            $collectionData = $db->$collection->find($query);

            if ($order !== false) {
                $collectionData->sort($order);
            }

            $timers [] = array(
                'time' => time(),
                'method' => 'END MONGO',
                'url' => $collection
            );

            $ret = new stdClass ();

            $ret->code = 200;
            $ret->response = $collectionData;
        } catch (MongoConnectionException $e) {
            die ('Error connecting to MongoDB server');
        } catch (MongoException $e) {
            die ('Error: ' . $e->getMessage());
        }

        return ($ret);
    }

    function InsertToMongo($collection, $query, $anotherdb = false)
    {
        global $settings, $timers;

        $timers [] = array(
            'time' => time(),
            'method' => 'START MONGO',
            'url' => $collection
        );

        $dbhost = $settings ['mongo'] ['ip'];
        $dbname = $settings ['mongo'] ['db'];

        // allows to connect another database on mongodb
        if ($anotherdb !== false)
            $dbname = $anotherdb;

        try {
            // Connect to test database
            $m = new MongoDB\Client("mongodb://" . settings()['mongo'] ['ip']);
            $db = $m->$dbname;

            // Get the users collection
            $collectionData = $db->$collection->insertOne($query);

            $timers [] = array(
                'time' => time(),
                'method' => 'END MONGO',
                'url' => $collection
            );

            $ret = new stdClass ();

            $ret->code = 200;
            $ret->response = $collectionData;
        } catch (MongoConnectionException $e) {
            die ('Error connecting to MongoDB server::errDesc:actionLog');
        } catch (MongoException $e) {
            die ('Error: ' . $e->getMessage());
        }

        return ($ret);
    }


    function GetFromMongo($collection, $query, $anotherdb)
    {
        global $settings, $timers;

        $timers [] = array(
            'time' => time(),
            'method' => 'START MONGO',
            'url' => $collection
        );

        $dbhost = $settings ['mongo'] ['ip'];
        $dbname = $settings ['mongo'] ['db'];

        // allows to connect another database on mongodb
        if ($anotherdb !== false)
            $dbname = $anotherdb;

        try {
            // Connect to test database
            $m = new MongoDB\Client("mongodb://$dbhost");
            $db = $m->$dbname;

            // Get the users collection
            // $collectionData = $db->$collection->update($whichData , $query );
            $collectionData = $db->$collection->find($query)->sort(array('userName' => 1))->limit(10000);


            $timers [] = array(
                'time' => time(),
                'method' => 'END MONGO',
                'url' => $collection
            );

            $ret = new stdClass ();

            $ret->code = 200;
            $ret->response = $collectionData;
        } catch (MongoConnectionException $e) {
            die ('Error connecting to MongoDB server');
        } catch (MongoException $e) {
            die ('Error: ' . $e->getMessage());
        }

        //var_dump (json_encode(iterator_to_array($ret->response )));
        /*   foreach (($ret->response) as $u){
               var_dump($u["userName"]);
               die();
           }*/

        return ($ret->response);
    }


    function UpdateToMongo($collection, $whichData, $query, $anotherdb)
    {
        global $settings, $timers;

        $timers [] = array(
            'time' => time(),
            'method' => 'START MONGO',
            'url' => $collection
        );

        $dbhost = $settings ['mongo'] ['ip'];
        $dbname = $settings ['mongo'] ['db'];

        // allows to connect another database on mongodb
        if ($anotherdb !== false)
            $dbname = $anotherdb;

        try {
            // Connect to test database
            $m = new MongoDB\Client("mongodb://$dbhost");
            $db = $m->$dbname;

            // Get the users collection
            $collectionData = $db->$collection->update($whichData, $query);


            $timers [] = array(
                'time' => time(),
                'method' => 'END MONGO',
                'url' => $collection
            );

            $ret = new stdClass ();

            $ret->code = 200;
            $ret->response = $collectionData;
        } catch (MongoConnectionException $e) {
            die ('Error connecting to MongoDB server');
        } catch (MongoException $e) {
            die ('Error: ' . $e->getMessage());
        }

        return ($ret);
    }


    function RemoveFromMongo($collection, $query, $anotherdb = false)
    {
        global $settings, $timers;

        $timers [] = array(
            'time' => time(),
            'method' => 'START MONGO',
            'url' => $collection
        );

        $dbhost = $settings ['mongo'] ['ip'];
        $dbname = $settings ['mongo'] ['db'];
        if ($anotherdb !== false)
            $dbname = $anotherdb;

        try {
            // Connect to test database
            $m = new MongoDB\Client("mongodb://$dbhost");
            $db = $m->$dbname;

            // Get the users collection
            $collectionData = $db->$collection->remove($query);

            $timers [] = array(
                'time' => time(),
                'method' => 'END MONGO',
                'url' => $collection
            );

            $ret = new stdClass ();

            $ret->code = 200;
            $ret->response = $collectionData;
        } catch (MongoConnectionException $e) {
            die ('Error connecting to MongoDB server');
        } catch (MongoException $e) {
            die ('Error: ' . $e->getMessage());
        }

        return ($ret);
    }

    function visibleNameFixer($o)
    {
        return $o->VisibleName;
    }

    function fixRepoMongo($r, $filter = false)
    {
        global $settings, $timers;

        $nResults = [];
        $subFolders = [];
        $edition = [];

        $timers [] = array(
            'time' => time(),
            'method' => 'START FIX',
            'url' => ''
        );

        $filter = rtrim($filter, '/');

        foreach ($r->response as $key => $value) {

            if ($filter !== FALSE) :
                $relativePath = str_replace($filter, '', $value ['DPath']);

            endif;

            if (($filter !== FALSE && !empty ($filter) && strstr($filter, $value ['DPath']) !== FALSE && $value ['DPath'] != $filter) || $filter == FALSE && !empty ($value ['DPath'])) {
                $subFolders [$value ['DPath']] = $value ['DPath'];
            }

            if (($filter == false && empty ($value ['DPath'])) || $filter == $value ['DPath']) {

                $arr = array(
                    'type' => 'blob',
                    'name' => $value ['DName'],
                    'path' => $value ['DPath'],
                    'Doctype' => isset ($value ['Doctype']) ? $value ['Doctype'] : 'documents',
                    'size' => $value ['size'],
                    'Datetime' => $value ['Datetime']
                );

                $nResults [$value ['DName']] = $arr;
                $edition [$value ['DName']] = $arr ['name'];
            }
        }

        array_multisort($edition, SORT_ASC, $nResults);

        foreach ($subFolders as $value) {

            $arr = array(
                'type' => 'tree',
                'name' => $value
            );

            $nResults = array_merge(array(
                $arr
            ), $nResults);
        }

        $timers [] = array(
            'time' => time(),
            'method' => 'END FIX',
            'url' => ''
        );

        $ret = new stdClass ();
        $ret->code = 200;
        $ret->response = $nResults;

        return $ret;
    }

    function ElasticToESB($e)
    {

        // //var_dump($e);
        return $e;
    }

    function fixFileMongo($r)
    {
        global $settings, $timers;

        $nResults = [];

        $timers [] = array(
            'time' => time(),
            'method' => 'START FIX',
            'url' => ''
        );

        $prevCommmit = '';
        foreach ($r->response as $key => $value) {
            // //var_dump($key);
            // //var_dump($value);
            $arr = array(
                'user' => $value ['UId'],
                'matter' => $value ['Matter'],
                'category' => $value ['Category'],
                'language' => $value ['Language'],
                'commitid' => $value ['CommitId'],
                'comment' => $value ['UNote'],
                'filename' => !empty ($value ['DPath']) ? $value ['DPath'] . '/' . $value ['DName'] : $value ['DName'],
                'datetime' => !empty ($value ['Datetime']) ? $value ['Datetime'] : strtotime('now')
            );

            if ($prevCommmit != $value ['UNote']) {
                $nResults [] = ( object )$arr;
            }

            $prevCommmit = $value ['UNote'];
        }

        $timers [] = array(
            'time' => time(),
            'method' => 'END FIX',
            'url' => ''
        );

        $r->response = $nResults;

        return $r;
    }

    function CallAPI($method, $url, $header, $body, $timeout = false, $json = true)
    {
        global $timers;
        $timeout = '30L';
        $ch = curl_init();
        // Set url
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // SSL

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, '10L');
        // curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1024*1024); //1 Mb/s

        // Set timeout
        if ($timeout !== FALSE) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        // Set body
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        // Send the request & save response to $resp
        $resp = curl_exec($ch);

        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!$resp) {
            // //var_dump(json_encode($info));
            // //var_dump(($resp));
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);
            die ('URL: "' . $url . ' Error: "' . $curl_error . '" - Code: ' . $curl_errno . '<hr> LINE' . __LINE__ . '<br>FILE ' . __FILE__);
        } else {
            curl_close($ch);            // Close request to clear up some resources
            if ($json) {
                $ret = json_decode($resp);
            } else {
                $ret = $resp;
            }
            return json_encode(array(
                'code' => $info,
                'response' => $ret
            ));
            // echo "Response HTTP Status Code : " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // echo "\nResponse HTTP Body : " . $resp;
        }


    }


    /*
     * function repoNameToId($name) {
     *
     * global $settings;
     *
     * if (logged_in() ) {
     * echo $name;
     * $repoArray = getAllRepos(true);
     *
     * $name=str_replace('/', ' / ', $name);
     *
     * //echo $name;
     *
     *
     * //var_dump($repoArray);
     *
     * $key = array_search($name, array_column($repoArray, 'name_with_namespace'));
     *
     * if (isset($repoArray[$name]['id']))
     * return $repoArray[$name]['id'];
     * else
     * return false;
     *
     * }
     * return false;
     * }
     */
    function repoNameToId($name)
    {
        global $settings;

        if (logged_in()) {
            // echo $token->response->access_token; die();
            //
            $repoArray = getAllRepos(true);

            // //var_dump(array_column($repoArray, 'name_with_namespace')); die();
            $name = str_replace('/', ' / ', $name);
            $key = array_search($name, array_column($repoArray, 'name_with_namespace'));
            if ($key !== false) {
                return $repoArray [$key]['id'];
            } else {
                return false;
            }

        }
        return false;
    }

    function getAllRepoList()
    {
        global $settings;

        if (logged_in()) {
            // echo $token->response->access_token; die();
            //
            // $repo_results = getAllRepos(false);

            $repo_results = getAllRepos(true);

            $myrepos = [];

            foreach ($repo_results as $repo) {

                $myrepos [$repo ['name_with_namespace']] = array(
                    'id' => $repo ['id'],
                    'path_with_namespace' => $repo ['path_with_namespace'],
                    'name_with_namespace' => $repo ['name_with_namespace'],
                    'file_repository_access' => false
                );
            }

            return $myrepos;
        }
        return false;
    }

    function getMyRepos()
    {
        global $settings;

        if (logged_in()) {
            // echo $token->response->access_token; die();
            //

            $repo_results = getAllRepos(false);


            $myrepos = [];

            foreach ($repo_results as $repo) {

                $myrepos [$repo ['name_with_namespace']] = array(
                    'id' => $repo ['id'],
                    'path_with_namespace' => $repo ['path_with_namespace'],
                    'name_with_namespace' => $repo ['name_with_namespace'],
                    'file_repository_access' => false
                );
            }
            // get other repositories from file permissions
            $permissions = getUsersPermissions();
            // //var_dump($permissions); die();
            if (is_array($permissions)) :
                foreach ($permissions as $permission) {
                    // do not bother if user already has repository access
                    if (!isset ($myrepos [$permission ['DRepository']])) :
                        // we need to get id and path_with_namespace for repoistory then connect to mongo

                        list ($group, $project) = explode(' / ', $permission ['DRepository']);

                        $search = array(
                            'clientName' => $group,
                            'projectName' => $project
                        );
                        $git = CallMongo('git', $search, 'gsiMongoGitData');
                        $mId = 0;
                        $mPt = '';

                        foreach ($git->response as $mresult) {
                            $mId = $mresult ['projectId'];
                            $mPt = renameForId($group) . '/' . renameForId($project);
                            break;
                        }

                        $myrepos [$permission ['DRepository']] = array(
                            'id' => $mId,
                            'name_with_namespace' => $permission ['DRepository'],
                            'path_with_namespace' => $mPt,
                            'file_repository_access' => true
                        );

                    endif;
                }

            endif;

            return $myrepos;
        }

        return false;
    }

    /**
     * var_dump() çıktısını biçimli şekilde gösterir.
     * @param $var değişken
     */
    function myDump($var)
    {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }

    function objToArray($obj, &$arr)
    {
        if (!is_object($obj) && !is_array($obj)) {
            $arr = $obj;
            return $arr;
        }

        foreach ($obj as $key => $value) {
            if (!empty ($value)) {
                $arr [$key] = array();
                objToArray($value, $arr [$key]);
            } else {
                $arr [$key] = $value;
            }
        }
        return $arr;
    }

    /*
     * function repoIdToName($id,$short=false) {
     *
     * global $settings;
     *
     * if (logged_in() ) {
     * // get all repos
     * $repoArray = getAllRepos(true);
     *
     * ////var_dump($repoArray);
     *
     * $repoStorage=array();
     *
     * foreach($repoArray as $key=>$val){
     *
     * $repoStorage[$val['id']]['path_with_namespace']=$val['path_with_namespace'];
     * $repoStorage[$val['id']]['name_with_namespace']=$val['name_with_namespace'];
     *
     * }
     *
     * if (isset($repoStorage[$id])) {
     *
     * if ($short):
     * return($repoStorage[$id]['name_with_namespace']);
     * else:
     * return($repoStorage[$id]['path_with_namespace']);
     * endif;
     * }
     *
     * }
     * return 0;
     * }
     */
    function repoIdToName($id, $short = false)
    {
        global $settings;

        if (logged_in()) {
            // get all repos
            $repoArray = getAllRepos(true);

            $key = array_search($id, array_column($repoArray, 'id'));

            if ($key !== FALSE) {
                if ($short) :
                    return ($repoArray [$key] ['name_with_namespace']);
                else :
                    return ($repoArray [$key] ['path_with_namespace']);
                endif;
            }
        }
        return 0;
    }

    function typeToTr($type)
    {
        return typeToTrNonReturned($type);
    }

    function keyToHumanReadable($type)
    {
        switch ($type) {
            case "DContent.Page.Paragraph.Content" :
                return "İçerik";
                break;
            case "CommitDesc" :
            case "BookName" :
                return "Kitap Adı";
                break;
            case "DocName" :
                return "Dosya Adı";
                break;
        }
        return $type;
    }

    function actionToTr($action)
    {
        $actions = array(
            'comment' => 'Yorum Yazma',
            'move' => 'Dosya Taşıma / Yenide Adlandırma',
            'delete' => 'Dosya silme',
            'upload' => 'Dosya Yükleme',
            'login' => 'Giriş',
            'managelogs' => 'Log inceleme',
            'manageusers' => 'Kullanıcı düzenleme',
            'managepermissions' => 'İzinleri düzenleme',
            'browse' => 'Gözat',
            'uadd' => 'Kullanıcı ekleme',
            'reader' => 'Kitap görüntüleyici',
            'mevzuatReader' => 'Mevzuat görüntüleyici',
            'uedit' => 'Kullanıcı düzenleme',
            'search' => 'Arama',
            'logout' => 'Çıkış',
            'file' => 'Dosya',
            'download' => 'İndirme',
            'username' => 'Kullanıcı adı',
            'usermail' => 'Email',
            'deleteuser' => 'Kullanıcı silme',
            'gadd' => 'Müvekkil ekleme',
            'padd' => 'Proje ekleme',
            'igsiemail' => 'IGSI Email Görüntüleme',
            'view' => 'Döküman Görüntüleme'
        );
        return $actions[$action];
    }

    function typeToTrNonReturned($type)
    {
        $type = strtolower($type);
        switch ($type) {
            case "tum":
                return "Tümü";
            case "document" :
            case "documents" :
                return "Doküman";
            case "book" :
            case "books" :
                return "Kitap";
            case "magazine" :
            case "magazines" :
                return "Dergi";
            case "yoktez":
                return "YÖK Tezleri";
            /* case "gsivdo":
                return "Videolar";*/
            case "rkbkrm":
                return "Rekabet Kurumu Kararları";
            case "spk":
                return "Sermaye Piyasası Kurulu";
            case "epdk":
                return "Enerji Piyasası Kurumu";
            case "uymev":
                return "Mevzuat(Beta)";
            case "mevzuat":
                return "Mevzuat";
            case "uyict":
                return "İctihad(Beta)";
            case "arsiv":
                return "Arsiv";
            case "ictihat":
                return "İctihad";
            case "crwbddk":
                return "BDDK Kararları";
            case "adv_email":
                return "iGSI Email";
            case "adv_belge":
                return "iGSI Doküman";
            case "adv_dava":
                return "iGSI Dava";
            case "adv_dataroom":
                return "iGSI DataRoom";

            default:
                return $type;

        }
    }


    function DocTypeFilter($type)
    {
        $type = strtolower($type);
        switch ($type) {
            case "documents" :
            case "arsiv" :
            case "books" :
            case "magazines" :
                // case "gsivdo" :
            case "yoktez":
            case "rkbkrm":
            case "spk":
            case "epdk":
            case "uymev":
            case "igsi":
            case "adv_email":
            case "adv_dava":
            case "adv_dataroom":
            case "adv_belge":
            case "crwbddk":
            case "uyict":

            case "tum":
                return $type;
            default:
                return "notype";
                break;
        }

    }

    function indexForSearch($type)
    {
        global $settings;

        $type = strtolower($type);
        switch ($type) {
            case "documents" :
                return $settings ['elastic'] ['index'];

            case "books" :
                return $settings ['elastic'] ['index'];
            case "igsi" :
                return $settings ['elastic'] ['indexForIgsi'];
            case "adv_email" :
                return $settings ['elastic'] ['indexForIgsi'];
            case "adv_dataroom" :
                return $settings ['elastic'] ['indexForIgsi'];
            case "adv_dava" :
                return $settings ['elastic'] ['indexForIgsi'];
            case "adv_belge" :
                return $settings ['elastic'] ['indexForIgsi'];
            case "uymev" :
                return $settings ['elastic'] ['indexForMevzuat'];
            case "uyict" :
                return $settings ['elastic'] ['indexForMevzuat'];
            case "arsiv" :
                return $settings ['elastic'] ['indexForArsiv'];

            case "tum":
                return $settings ['elastic'] ['index'] . "," . $settings ['elastic'] ['indexForMevzuat'] . "," . $settings ['elastic'] ['indexForIgsi'] . "," . $settings ['elastic'] ['indexForArsiv'];
            default:
                return $settings ['elastic'] ['index'];

        }

    }


// Result counter for each type
    $resultCounters = array(
        'documents' => 0,
        'arsiv' => 0,
        'books' => 0,
        'magazines' => 0,
        // 'gsivdo' => 0,
        'yoktez' => 0,
        'rkbkrm' => 0,
        'spk' => 0,
        'epdk' => 0,
        'crwbddk' => 0,
        'uymev' => 0,
        'uyict' => 0,
        'adv_email' => 0,
        'adv_dataroom' => 0,
        'adv_belge' => 0,
        'adv_dava' => 0,
        'tum' => 0,
    );

    $resultCountersGroups = array(
        'documents' => 0,
        'arsiv' => 0,
        'books' => 0,
        'magazines' => 0,
        // 'gsivdo' => 0,
        'yoktez' => 0,
        'rkbkrm' => 0,
        'spk' => 0,
        'epdk' => 0,
        'crwbddk' => 0,
        'uymev' => 0,
        'uyict' => 0,
        'adv_email' => 0,
        'adv_dataroom' => 0,
        'adv_belge' => 0,
        'adv_dava' => 0,
        'tum' => 0,
    );

    function GitLevels($type)
    {
        $type = strtolower($type);
        switch ($type) {
            case "10" :
                return "Okuma";
                break;
            case "30" :
                return "Yazma";
                break;
            case "50" :
                return "Yönetici";
                break;
        }
        return "Diğer";
    }

    function typeToClass($type)
    {
        if (substr($type, -1) == 's') {
            $type = substr($type, 0, strlen($type) - 1);
        }
        return $type;
    }

    function versionCount($versions, $prefix = '')
    {
        $count = count(explode(',', $versions));
        return $prefix . ++$count; //
    }

    function curl_execute($url, $cookie)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Cookie:" . $cookie

            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response);
        return $response;
    }

    function logged_in()
    {
        $userName = current_user_name();
        $userName = strtolower($userName);

        $sessionCookie = $_COOKIE["ochaa53c42io"];
        $sessionPassphraseCookie = $_COOKIE["oc_sessionPassphrase"];
        $cookie = "nc_sameSiteCookielax=true; nc_sameSiteCookiestrict=true; ochaa53c42io=" . $sessionCookie . "; oc_sessionPassphrase=" . urlencode($sessionPassphraseCookie) . ";";
        $url = "https://docs.gsimecelle.com/index.php/apps/gsiapi/accessControl/checkLoggedIn";
        $response = curl_execute($url, $cookie);
        if (!isset($response->isLoggedIn) || !$response->isLoggedIn) {
            header("Location: https://docs.gsimecelle.com/index.php/logout");
            die();
        }
        if (!cidr_match($_SERVER['REMOTE_ADDR']) && !in_array($userName, EXCLUDED_VPN_USERS)) {
            header("Location: https://www.gsimecelle.com/vpn-erisim.php");
            die();
        }

        return true;
    }

    function is_debugger()
    {
        global $debuggers;
        if (in_array(current_user_name(), $debuggers))
            return true;
        return false;
    }


    function current_user_name()
    {
        /*if (isset ( $_SESSION [''] ) && ! empty ( $_SESSION ['name'] )) {
            return $_SESSION ['name'];
        }
        return $_SESSION ['username'];*/


        $cookie = $_COOKIE['oc_user_logged_in'];
        if ($cookie == NULL) return NULL;
        $userId = Crypto::decrypt($cookie);

        return $userId;
    }

    function getExt($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext;
    }

    function getAwesomeFileIcon($ext)
    {
        switch ($ext) {
            case 'doc' :
            case 'docx' :
                return 'file-word-o';
            case 'xls' :
            case 'xlsx' :
                return 'file-excel-o';
            case 'ppt' :
            case 'pptx' :
                return 'file-powerpoint-o';
            case 'jpg' :
            case 'jpeg' :
            case 'gif' :
            case 'png' :
            case 'svg' :
            case 'bmp' :
            case 'tif' :
            case 'tiff' :
                return 'file-image-o';
            // return 'file-image-o';
            case 'zip' :
            case 'rar' :
                return 'file-archive-o';
            case 'wav' :
            case 'mp3' :
                return 'file-audio-o';
            case 'pdf' :
                return 'file-pdf-o';
            case 'avi' :
            case 'mp4' :
            case 'mov' :
                return 'file-video-o';
            case 'html' :
            case 'htm' :
                return 'file-code-o';
            case 'txt' :
            case 'rtf' :
                return 'file-text-o';
            case 'gsi' :
                return 'book';
            case 'dergi' :
                return 'newspaper-o';
            case 'eml' :
                return 'envelope';
            default :
                return 'file-o';
        }
    }

    function errTr($err)
    {
        switch ($err) {
            case 'invalid_grant' :
                return 'Yönetici parolanız doğrulanamadı ya da bu işlemi yapmaya yetkiniz yok.';
            case 'project created' :
                return 'Yeni proje açılmıştır.';
            case 'password changed' :
                return 'Şifreniz değiştirilmiştir. Bundan sonraki işlemlerde yeni şifrenizi kullanınınız.';
            case 'user created' :
                return 'Yeni kullanıcı oluşturulmuştur. ';
            case 'user updated' :
                return 'Kullanıcı bilgileri güncellenmiştir. ';
            case 'user permission given' :
                return 'Kullanıcı yetkilendirilmiştir. ';
            case 'user permission removed' :
                return 'Kullanıcı yetkisi kaldırılmıştır. ';
            case 'svg' :
            case 'bmp' :
            case 'tif' :
            case 'tiff' :
                return 'file-image-o';
            case 'zip' :
            case 'rar' :
                return 'file-archive-o';
            case 'wav' :
            case 'mp3' :
                return 'file-audio-o';
            case 'pdf' :
                return 'file-pdf-o';
            case 'avi' :
            case 'mp4' :
            case 'mov' :
                return 'file-video-o';
            case 'html' :
            case 'htm' :
                return 'file-code-o';
            case 'txt' :
            case 'rtf' :
                return 'file-text-o';
            default :
                return $err;
        }
    }

    function rip_tags($string)
    {

        // ----- remove HTML TAGs -----
        $string = preg_replace('/<[^>]*>/', ' ', $string);

        // ----- remove control characters -----
        $string = str_replace("\r", '', $string); // --- replace with empty space
        $string = str_replace("\n", ' ', $string); // --- replace with space
        $string = str_replace("\t", ' ', $string); // --- replace with space

        // ----- remove multiple spaces -----
        $string = trim(preg_replace('/ {2,}/', ' ', $string));

        return $string;
    }

    function LongText($str, $len)
    {
        $words = explode(' ', $str);
        if (count($words) > $len) {
            $p1 = (array_slice($words, 0, $len));
            $p2 = array_slice($words, $len, count($words) - $len);
            // //var_dump($p1);
            // //var_dump(implode(' ',$p2)); die();
            if (count($p2) > $len) {
                $p2 = explode(' ', LongText(implode(' ', $p2), $len));
            }
            $words = array_merge($p1, array(
                "\n"
            ), $p2);
        }
        return implode($words, ' ');
    }

    function recursive_array_replace($find, $replace, $array)
    {
        if (!is_array($array)) {
            return str_replace($find, $replace, $array);
        }
        $newArray = array();
        foreach ($array as $key => $value) {
            $newArray [$key] = recursive_array_replace($find, $replace, $value);
        }
        return $newArray;
    }

    /**
     * Converts all accent characters to ASCII characters.
     *
     * If there are no accent characters, then the string given is just returned.
     *
     * @since 1.2.1
     *
     * @param string $string
     *            Text that might have accent characters
     * @return string Filtered string with replaced "nice" characters.
     */
    function mbstring_binary_safe_encoding($reset = false)
    {
        static $encodings = array();
        static $overloaded = null;

        if (is_null($overloaded))
            $overloaded = function_exists('mb_internal_encoding') && (ini_get('mbstring.func_overload') & 2);

        if (false === $overloaded)
            return;

        if (!$reset) {
            $encoding = mb_internal_encoding();
            array_push($encodings, $encoding);
            mb_internal_encoding('ISO-8859-1');
        }

        if ($reset && $encodings) {
            $encoding = array_pop($encodings);
            mb_internal_encoding($encoding);
        }
    }

    function reset_mbstring_encoding()
    {
        mbstring_binary_safe_encoding(true);
    }

    function seems_utf8($str)
    {
        mbstring_binary_safe_encoding();
        $length = strlen($str);
        reset_mbstring_encoding();
        for ($i = 0; $i < $length; $i++) {
            $c = ord($str [$i]);
            if ($c < 0x80)
                $n = 0; // 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0)
                $n = 1; // 110bbbbb
            elseif (($c & 0xF0) == 0xE0)
                $n = 2; // 1110bbbb
            elseif (($c & 0xF8) == 0xF0)
                $n = 3; // 11110bbb
            elseif (($c & 0xFC) == 0xF8)
                $n = 4; // 111110bb
            elseif (($c & 0xFE) == 0xFC)
                $n = 5; // 1111110b
            else
                return false; // Does not match any model
            for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow ?
                if ((++$i == $length) || ((ord($str [$i]) & 0xC0) != 0x80))
                    return false;
            }
        }
        return true;
    }

    function remove_accents($string)
    {
        if (!preg_match('/[\x80-\xff]/', $string))
            return $string;

        if (seems_utf8($string)) {
            $chars = array(
                // Decompositions for Latin-1 Supplement
                chr(194) . chr(170) => 'a',
                chr(194) . chr(186) => 'o',
                chr(195) . chr(128) => 'A',
                chr(195) . chr(129) => 'A',
                chr(195) . chr(130) => 'A',
                chr(195) . chr(131) => 'A',
                chr(195) . chr(132) => 'A',
                chr(195) . chr(133) => 'A',
                chr(195) . chr(134) => 'AE',
                chr(195) . chr(135) => 'C',
                chr(195) . chr(136) => 'E',
                chr(195) . chr(137) => 'E',
                chr(195) . chr(138) => 'E',
                chr(195) . chr(139) => 'E',
                chr(195) . chr(140) => 'I',
                chr(195) . chr(141) => 'I',
                chr(195) . chr(142) => 'I',
                chr(195) . chr(143) => 'I',
                chr(195) . chr(144) => 'D',
                chr(195) . chr(145) => 'N',
                chr(195) . chr(146) => 'O',
                chr(195) . chr(147) => 'O',
                chr(195) . chr(148) => 'O',
                chr(195) . chr(149) => 'O',
                chr(195) . chr(150) => 'O',
                chr(195) . chr(153) => 'U',
                chr(195) . chr(154) => 'U',
                chr(195) . chr(155) => 'U',
                chr(195) . chr(156) => 'U',
                chr(195) . chr(157) => 'Y',
                chr(195) . chr(158) => 'TH',
                chr(195) . chr(159) => 's',
                chr(195) . chr(160) => 'a',
                chr(195) . chr(161) => 'a',
                chr(195) . chr(162) => 'a',
                chr(195) . chr(163) => 'a',
                chr(195) . chr(164) => 'a',
                chr(195) . chr(165) => 'a',
                chr(195) . chr(166) => 'ae',
                chr(195) . chr(167) => 'c',
                chr(195) . chr(168) => 'e',
                chr(195) . chr(169) => 'e',
                chr(195) . chr(170) => 'e',
                chr(195) . chr(171) => 'e',
                chr(195) . chr(172) => 'i',
                chr(195) . chr(173) => 'i',
                chr(195) . chr(174) => 'i',
                chr(195) . chr(175) => 'i',
                chr(195) . chr(176) => 'd',
                chr(195) . chr(177) => 'n',
                chr(195) . chr(178) => 'o',
                chr(195) . chr(179) => 'o',
                chr(195) . chr(180) => 'o',
                chr(195) . chr(181) => 'o',
                chr(195) . chr(182) => 'o',
                chr(195) . chr(184) => 'o',
                chr(195) . chr(185) => 'u',
                chr(195) . chr(186) => 'u',
                chr(195) . chr(187) => 'u',
                chr(195) . chr(188) => 'u',
                chr(195) . chr(189) => 'y',
                chr(195) . chr(190) => 'th',
                chr(195) . chr(191) => 'y',
                chr(195) . chr(152) => 'O',
                // Decompositions for Latin Extended-A
                chr(196) . chr(128) => 'A',
                chr(196) . chr(129) => 'a',
                chr(196) . chr(130) => 'A',
                chr(196) . chr(131) => 'a',
                chr(196) . chr(132) => 'A',
                chr(196) . chr(133) => 'a',
                chr(196) . chr(134) => 'C',
                chr(196) . chr(135) => 'c',
                chr(196) . chr(136) => 'C',
                chr(196) . chr(137) => 'c',
                chr(196) . chr(138) => 'C',
                chr(196) . chr(139) => 'c',
                chr(196) . chr(140) => 'C',
                chr(196) . chr(141) => 'c',
                chr(196) . chr(142) => 'D',
                chr(196) . chr(143) => 'd',
                chr(196) . chr(144) => 'D',
                chr(196) . chr(145) => 'd',
                chr(196) . chr(146) => 'E',
                chr(196) . chr(147) => 'e',
                chr(196) . chr(148) => 'E',
                chr(196) . chr(149) => 'e',
                chr(196) . chr(150) => 'E',
                chr(196) . chr(151) => 'e',
                chr(196) . chr(152) => 'E',
                chr(196) . chr(153) => 'e',
                chr(196) . chr(154) => 'E',
                chr(196) . chr(155) => 'e',
                chr(196) . chr(156) => 'G',
                chr(196) . chr(157) => 'g',
                chr(196) . chr(158) => 'G',
                chr(196) . chr(159) => 'g',
                chr(196) . chr(160) => 'G',
                chr(196) . chr(161) => 'g',
                chr(196) . chr(162) => 'G',
                chr(196) . chr(163) => 'g',
                chr(196) . chr(164) => 'H',
                chr(196) . chr(165) => 'h',
                chr(196) . chr(166) => 'H',
                chr(196) . chr(167) => 'h',
                chr(196) . chr(168) => 'I',
                chr(196) . chr(169) => 'i',
                chr(196) . chr(170) => 'I',
                chr(196) . chr(171) => 'i',
                chr(196) . chr(172) => 'I',
                chr(196) . chr(173) => 'i',
                chr(196) . chr(174) => 'I',
                chr(196) . chr(175) => 'i',
                chr(196) . chr(176) => 'I',
                chr(196) . chr(177) => 'i',
                chr(196) . chr(178) => 'IJ',
                chr(196) . chr(179) => 'ij',
                chr(196) . chr(180) => 'J',
                chr(196) . chr(181) => 'j',
                chr(196) . chr(182) => 'K',
                chr(196) . chr(183) => 'k',
                chr(196) . chr(184) => 'k',
                chr(196) . chr(185) => 'L',
                chr(196) . chr(186) => 'l',
                chr(196) . chr(187) => 'L',
                chr(196) . chr(188) => 'l',
                chr(196) . chr(189) => 'L',
                chr(196) . chr(190) => 'l',
                chr(196) . chr(191) => 'L',
                chr(197) . chr(128) => 'l',
                chr(197) . chr(129) => 'L',
                chr(197) . chr(130) => 'l',
                chr(197) . chr(131) => 'N',
                chr(197) . chr(132) => 'n',
                chr(197) . chr(133) => 'N',
                chr(197) . chr(134) => 'n',
                chr(197) . chr(135) => 'N',
                chr(197) . chr(136) => 'n',
                chr(197) . chr(137) => 'N',
                chr(197) . chr(138) => 'n',
                chr(197) . chr(139) => 'N',
                chr(197) . chr(140) => 'O',
                chr(197) . chr(141) => 'o',
                chr(197) . chr(142) => 'O',
                chr(197) . chr(143) => 'o',
                chr(197) . chr(144) => 'O',
                chr(197) . chr(145) => 'o',
                chr(197) . chr(146) => 'OE',
                chr(197) . chr(147) => 'oe',
                chr(197) . chr(148) => 'R',
                chr(197) . chr(149) => 'r',
                chr(197) . chr(150) => 'R',
                chr(197) . chr(151) => 'r',
                chr(197) . chr(152) => 'R',
                chr(197) . chr(153) => 'r',
                chr(197) . chr(154) => 'S',
                chr(197) . chr(155) => 's',
                chr(197) . chr(156) => 'S',
                chr(197) . chr(157) => 's',
                chr(197) . chr(158) => 'S',
                chr(197) . chr(159) => 's',
                chr(197) . chr(160) => 'S',
                chr(197) . chr(161) => 's',
                chr(197) . chr(162) => 'T',
                chr(197) . chr(163) => 't',
                chr(197) . chr(164) => 'T',
                chr(197) . chr(165) => 't',
                chr(197) . chr(166) => 'T',
                chr(197) . chr(167) => 't',
                chr(197) . chr(168) => 'U',
                chr(197) . chr(169) => 'u',
                chr(197) . chr(170) => 'U',
                chr(197) . chr(171) => 'u',
                chr(197) . chr(172) => 'U',
                chr(197) . chr(173) => 'u',
                chr(197) . chr(174) => 'U',
                chr(197) . chr(175) => 'u',
                chr(197) . chr(176) => 'U',
                chr(197) . chr(177) => 'u',
                chr(197) . chr(178) => 'U',
                chr(197) . chr(179) => 'u',
                chr(197) . chr(180) => 'W',
                chr(197) . chr(181) => 'w',
                chr(197) . chr(182) => 'Y',
                chr(197) . chr(183) => 'y',
                chr(197) . chr(184) => 'Y',
                chr(197) . chr(185) => 'Z',
                chr(197) . chr(186) => 'z',
                chr(197) . chr(187) => 'Z',
                chr(197) . chr(188) => 'z',
                chr(197) . chr(189) => 'Z',
                chr(197) . chr(190) => 'z',
                chr(197) . chr(191) => 's',
                // Decompositions for Latin Extended-B
                chr(200) . chr(152) => 'S',
                chr(200) . chr(153) => 's',
                chr(200) . chr(154) => 'T',
                chr(200) . chr(155) => 't',
                // Euro Sign
                chr(226) . chr(130) . chr(172) => 'E',
                // GBP (Pound) Sign
                chr(194) . chr(163) => '',
                // Vowels with diacritic (Vietnamese)
                // unmarked
                chr(198) . chr(160) => 'O',
                chr(198) . chr(161) => 'o',
                chr(198) . chr(175) => 'U',
                chr(198) . chr(176) => 'u',
                // grave accent
                chr(225) . chr(186) . chr(166) => 'A',
                chr(225) . chr(186) . chr(167) => 'a',
                chr(225) . chr(186) . chr(176) => 'A',
                chr(225) . chr(186) . chr(177) => 'a',
                chr(225) . chr(187) . chr(128) => 'E',
                chr(225) . chr(187) . chr(129) => 'e',
                chr(225) . chr(187) . chr(146) => 'O',
                chr(225) . chr(187) . chr(147) => 'o',
                chr(225) . chr(187) . chr(156) => 'O',
                chr(225) . chr(187) . chr(157) => 'o',
                chr(225) . chr(187) . chr(170) => 'U',
                chr(225) . chr(187) . chr(171) => 'u',
                chr(225) . chr(187) . chr(178) => 'Y',
                chr(225) . chr(187) . chr(179) => 'y',
                // hook
                chr(225) . chr(186) . chr(162) => 'A',
                chr(225) . chr(186) . chr(163) => 'a',
                chr(225) . chr(186) . chr(168) => 'A',
                chr(225) . chr(186) . chr(169) => 'a',
                chr(225) . chr(186) . chr(178) => 'A',
                chr(225) . chr(186) . chr(179) => 'a',
                chr(225) . chr(186) . chr(186) => 'E',
                chr(225) . chr(186) . chr(187) => 'e',
                chr(225) . chr(187) . chr(130) => 'E',
                chr(225) . chr(187) . chr(131) => 'e',
                chr(225) . chr(187) . chr(136) => 'I',
                chr(225) . chr(187) . chr(137) => 'i',
                chr(225) . chr(187) . chr(142) => 'O',
                chr(225) . chr(187) . chr(143) => 'o',
                chr(225) . chr(187) . chr(148) => 'O',
                chr(225) . chr(187) . chr(149) => 'o',
                chr(225) . chr(187) . chr(158) => 'O',
                chr(225) . chr(187) . chr(159) => 'o',
                chr(225) . chr(187) . chr(166) => 'U',
                chr(225) . chr(187) . chr(167) => 'u',
                chr(225) . chr(187) . chr(172) => 'U',
                chr(225) . chr(187) . chr(173) => 'u',
                chr(225) . chr(187) . chr(182) => 'Y',
                chr(225) . chr(187) . chr(183) => 'y',
                // tilde
                chr(225) . chr(186) . chr(170) => 'A',
                chr(225) . chr(186) . chr(171) => 'a',
                chr(225) . chr(186) . chr(180) => 'A',
                chr(225) . chr(186) . chr(181) => 'a',
                chr(225) . chr(186) . chr(188) => 'E',
                chr(225) . chr(186) . chr(189) => 'e',
                chr(225) . chr(187) . chr(132) => 'E',
                chr(225) . chr(187) . chr(133) => 'e',
                chr(225) . chr(187) . chr(150) => 'O',
                chr(225) . chr(187) . chr(151) => 'o',
                chr(225) . chr(187) . chr(160) => 'O',
                chr(225) . chr(187) . chr(161) => 'o',
                chr(225) . chr(187) . chr(174) => 'U',
                chr(225) . chr(187) . chr(175) => 'u',
                chr(225) . chr(187) . chr(184) => 'Y',
                chr(225) . chr(187) . chr(185) => 'y',
                // acute accent
                chr(225) . chr(186) . chr(164) => 'A',
                chr(225) . chr(186) . chr(165) => 'a',
                chr(225) . chr(186) . chr(174) => 'A',
                chr(225) . chr(186) . chr(175) => 'a',
                chr(225) . chr(186) . chr(190) => 'E',
                chr(225) . chr(186) . chr(191) => 'e',
                chr(225) . chr(187) . chr(144) => 'O',
                chr(225) . chr(187) . chr(145) => 'o',
                chr(225) . chr(187) . chr(154) => 'O',
                chr(225) . chr(187) . chr(155) => 'o',
                chr(225) . chr(187) . chr(168) => 'U',
                chr(225) . chr(187) . chr(169) => 'u',
                // dot below
                chr(225) . chr(186) . chr(160) => 'A',
                chr(225) . chr(186) . chr(161) => 'a',
                chr(225) . chr(186) . chr(172) => 'A',
                chr(225) . chr(186) . chr(173) => 'a',
                chr(225) . chr(186) . chr(182) => 'A',
                chr(225) . chr(186) . chr(183) => 'a',
                chr(225) . chr(186) . chr(184) => 'E',
                chr(225) . chr(186) . chr(185) => 'e',
                chr(225) . chr(187) . chr(134) => 'E',
                chr(225) . chr(187) . chr(135) => 'e',
                chr(225) . chr(187) . chr(138) => 'I',
                chr(225) . chr(187) . chr(139) => 'i',
                chr(225) . chr(187) . chr(140) => 'O',
                chr(225) . chr(187) . chr(141) => 'o',
                chr(225) . chr(187) . chr(152) => 'O',
                chr(225) . chr(187) . chr(153) => 'o',
                chr(225) . chr(187) . chr(162) => 'O',
                chr(225) . chr(187) . chr(163) => 'o',
                chr(225) . chr(187) . chr(164) => 'U',
                chr(225) . chr(187) . chr(165) => 'u',
                chr(225) . chr(187) . chr(176) => 'U',
                chr(225) . chr(187) . chr(177) => 'u',
                chr(225) . chr(187) . chr(180) => 'Y',
                chr(225) . chr(187) . chr(181) => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin)
                chr(201) . chr(145) => 'a',
                // macron
                chr(199) . chr(149) => 'U',
                chr(199) . chr(150) => 'u',
                // acute accent
                chr(199) . chr(151) => 'U',
                chr(199) . chr(152) => 'u',
                // caron
                chr(199) . chr(141) => 'A',
                chr(199) . chr(142) => 'a',
                chr(199) . chr(143) => 'I',
                chr(199) . chr(144) => 'i',
                chr(199) . chr(145) => 'O',
                chr(199) . chr(146) => 'o',
                chr(199) . chr(147) => 'U',
                chr(199) . chr(148) => 'u',
                chr(199) . chr(153) => 'U',
                chr(199) . chr(154) => 'u',
                // grave accent
                chr(199) . chr(155) => 'U',
                chr(199) . chr(156) => 'u'
            );

            $string = strtr($string, $chars);
        } else {
            $chars = array();
            // Assume ISO-8859-1 if not UTF-8
            $chars ['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158) . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194) . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202) . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210) . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218) . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227) . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235) . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243) . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251) . chr(252) . chr(253) . chr(255);

            $chars ['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

            $string = strtr($string, $chars ['in'], $chars ['out']);
            $double_chars = array();
            $double_chars ['in'] = array(
                chr(140),
                chr(156),
                chr(198),
                chr(208),
                chr(222),
                chr(223),
                chr(230),
                chr(240),
                chr(254)
            );
            $double_chars ['out'] = array(
                'OE',
                'oe',
                'AE',
                'DH',
                'TH',
                'ss',
                'ae',
                'dh',
                'th'
            );
            $string = str_replace($double_chars ['in'], $double_chars ['out'], $string);
        }

        return $string;
    }

    function safe_name($s)
    {
        $s = remove_accents($s);
        $s = strtolower($s);
        $s = strtolower($s);
        $s = preg_replace('|[^a-z0-9 _.\-@]|i', '', $s);
        $s = preg_replace('| |i', '-', $s);
        return $s;
    }

    function searchQueryCleaner($searchKey)
    {
        $blackList [] = "acaba";
        $blackList [] = "altmış";
        $blackList [] = "altmiş";
        $blackList [] = "altı";
        $blackList [] = "alti";
        $blackList [] = "ama";
        $blackList [] = "ancak";
        $blackList [] = "arada";
        $blackList [] = "aslında";
        $blackList [] = "aslinda";
        $blackList [] = "ayrıca";
        $blackList [] = "ayrica";
        $blackList [] = "bana";
        $blackList [] = "bazı";
        $blackList [] = "bazi";
        $blackList [] = "belki";
        $blackList [] = "ben";
        $blackList [] = "benden";
        $blackList [] = "beni";
        $blackList [] = "benim";
        $blackList [] = "beri";
        $blackList [] = "beş";
        $blackList [] = "bile";
        $blackList [] = "bin";
        $blackList [] = "bir";
        $blackList [] = "birçok";
        $blackList [] = "biri";
        $blackList [] = "birkaç";
        $blackList [] = "birkez";
        $blackList [] = "birşey";
        $blackList [] = "birşeyi";
        $blackList [] = "biz";
        $blackList [] = "bize";
        $blackList [] = "bizden";
        $blackList [] = "bizi";
        $blackList [] = "bizim";
        $blackList [] = "böyle";
        $blackList [] = "böylece";
        $blackList [] = "bu";
        $blackList [] = "buna";
        $blackList [] = "bunda";
        $blackList [] = "bundan";
        $blackList [] = "bunlar";
        $blackList [] = "bunları";
        $blackList [] = "bunlari";
        $blackList [] = "bunların";
        $blackList [] = "bunlarin";
        $blackList [] = "bunu";
        $blackList [] = "bunun";
        $blackList [] = "burada";
        $blackList [] = "çok";
        $blackList [] = "çünkü";
        $blackList [] = "da";
        $blackList [] = "daha";
        $blackList [] = "dahi";
        $blackList [] = "de";
        $blackList [] = "defa";
        $blackList [] = "değil";
        $blackList [] = "diğer";
        $blackList [] = "diye";
        $blackList [] = "doksan";
        $blackList [] = "dokuz";
        $blackList [] = "dolayı";
        $blackList [] = "dolayi";
        $blackList [] = "dolayısıyla";
        $blackList [] = "dolayisiyla";
        $blackList [] = "dört";
        $blackList [] = "edecek";
        $blackList [] = "eden";
        $blackList [] = "ederek";
        $blackList [] = "edilecek";
        $blackList [] = "ediliyor";
        $blackList [] = "edilmesi";
        $blackList [] = "ediyor";
        $blackList [] = "eğer";
        $blackList [] = "elli";
        $blackList [] = "en";
        $blackList [] = "etmesi";
        $blackList [] = "etti";
        $blackList [] = "ettiği";
        $blackList [] = "ettiğini";
        $blackList [] = "gibi";
        $blackList [] = "göre";
        $blackList [] = "halen";
        $blackList [] = "hangi";
        $blackList [] = "hatta";
        $blackList [] = "hem";
        $blackList [] = "henüz";
        $blackList [] = "hep";
        $blackList [] = "hepsi";
        $blackList [] = "her";
        $blackList [] = "herhangi";
        $blackList [] = "herkesin";
        $blackList [] = "hiç";
        $blackList [] = "hiçbir";
        $blackList [] = "için";
        $blackList [] = "iki";
        $blackList [] = "ile";
        $blackList [] = "ilgili";
        $blackList [] = "ise";
        $blackList [] = "işte";
        $blackList [] = "itibaren";
        $blackList [] = "itibariyle";
        $blackList [] = "kadar";
        $blackList [] = "karşın";
        $blackList [] = "karşin";
        $blackList [] = "katrilyon";
        $blackList [] = "kendi";
        $blackList [] = "kendilerine";
        $blackList [] = "kendini";
        $blackList [] = "kendisi";
        $blackList [] = "kendisine";
        $blackList [] = "kendisini";
        $blackList [] = "kez";
        $blackList [] = "ki";
        $blackList [] = "kim";
        $blackList [] = "kimden";
        $blackList [] = "kime";
        $blackList [] = "kimi";
        $blackList [] = "kimse";
        $blackList [] = "kırk";
        $blackList [] = "kirk";
        $blackList [] = "milyar";
        $blackList [] = "milyon";
        $blackList [] = "mu";
        $blackList [] = "mü";
        $blackList [] = "mı";
        $blackList [] = "mi";
        $blackList [] = "nasıl";
        $blackList [] = "nasil";
        $blackList [] = "ne";
        $blackList [] = "neden";
        $blackList [] = "nedenle";
        $blackList [] = "nerde";
        $blackList [] = "nerede";
        $blackList [] = "nereye";
        $blackList [] = "niye";
        $blackList [] = "niçin";
        $blackList [] = "o";
        $blackList [] = "olan";
        $blackList [] = "olarak";
        $blackList [] = "oldu";
        $blackList [] = "olduğu";
        $blackList [] = "olduğunu";
        $blackList [] = "olduklarını";
        $blackList [] = "olduklarini";
        $blackList [] = "olmadı";
        $blackList [] = "olmadi";
        $blackList [] = "olmadığı";
        $blackList [] = "olmadiği";
        $blackList [] = "olmak";
        $blackList [] = "olması";
        $blackList [] = "olmasi";
        $blackList [] = "olmayan";
        $blackList [] = "olmaz";
        $blackList [] = "olsa";
        $blackList [] = "olsun";
        $blackList [] = "olup";
        $blackList [] = "olur";
        $blackList [] = "olursa";
        $blackList [] = "oluyor";
        $blackList [] = "on";
        $blackList [] = "ona";
        $blackList [] = "ondan";
        $blackList [] = "onlar";
        $blackList [] = "onlardan";
        $blackList [] = "onları";
        $blackList [] = "onlari";
        $blackList [] = "onların";
        $blackList [] = "onlarin";
        $blackList [] = "onu";
        $blackList [] = "onun";
        $blackList [] = "otuz";
        $blackList [] = "oysa";
        $blackList [] = "öyle";
        $blackList [] = "pek";
        $blackList [] = "rağmen";
        $blackList [] = "sadece";
        $blackList [] = "sanki";
        $blackList [] = "sekiz";
        $blackList [] = "seksen";
        $blackList [] = "sen";
        $blackList [] = "senden";
        $blackList [] = "seni";
        $blackList [] = "senin";
        $blackList [] = "siz";
        $blackList [] = "sizden";
        $blackList [] = "sizi";
        $blackList [] = "sizin";
        $blackList [] = "şey";
        $blackList [] = "şeyden";
        $blackList [] = "şeyi";
        $blackList [] = "şeyler";
        $blackList [] = "şöyle";
        $blackList [] = "şu";
        $blackList [] = "şuna";
        $blackList [] = "şunda";
        $blackList [] = "şundan";
        $blackList [] = "şunları";
        $blackList [] = "şunlari";
        $blackList [] = "şunu";
        $blackList [] = "tarafından";
        $blackList [] = "tarafindan";
        $blackList [] = "trilyon";
        $blackList [] = "tüm";
        $blackList [] = "üç";
        $blackList [] = "üzere";
        $blackList [] = "var";
        $blackList [] = "vardı";
        $blackList [] = "vardi";
        $blackList [] = "ve";
        $blackList [] = "veya";
        $blackList [] = "ya";
        $blackList [] = "yani";
        $blackList [] = "yapacak";
        $blackList [] = "yapılan";
        $blackList [] = "yapilan";
        $blackList [] = "yapılması";
        $blackList [] = "yapilmasi";
        $blackList [] = "yapıyor";
        $blackList [] = "yapiyor";
        $blackList [] = "yapmak";
        $blackList [] = "yaptı";
        $blackList [] = "yapti";
        $blackList [] = "yaptığı";
        $blackList [] = "yaptiği";
        $blackList [] = "yaptığını";
        $blackList [] = "yaptiğini";
        $blackList [] = "yaptıkları";
        $blackList [] = "yaptiklari";
        $blackList [] = "yedi";
        $blackList [] = "yerine";
        $blackList [] = "yetmiş";
        $blackList [] = "yine";
        $blackList [] = "yirmi";
        $blackList [] = "yoksa";
        $blackList [] = "yüz";
        $blackList [] = "zaten";

        mb_internal_encoding('UTF-8');
        $searchKey = str_replace('"', ' " ', $searchKey);

        $searchWords = explode(" ", $searchKey);

        $returnSearchKey = "";

        foreach ($blackList as $blKey => $blVal) :

            $blackListArray [] = trim($blVal);
        endforeach;

        foreach ($searchWords as $sKey => $sVal) {

            if (trim($sVal) != "") {

                if (in_array(trim($sVal), $blackListArray)) {
                } else {

                    $returnSearchKey .= $sVal . " ";
                }
            }
        }

        return $returnSearchKey;
    }

    function is_base64($val)
    {
        return ( bool )!preg_match('/[a-zA-Z0-9_-]/', $val);
    }

    function alternativeWords($input)
    {
        $words = [];

        $input = strtolower($input);

        $input_words = explode(' ', $input);

        $handle = fopen("data/sentences.txt", "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                // process the line read.
                $words [] = $line;
            }

            fclose($handle);
        } else {
            // error opening the file.
        }

        // en kısa mesafenin bulunamaması durumu
        $shortest = -1;

        // En kısa mesafeyi bulmak iÃ§in dÃ¶ngÃ¼
        foreach ($words as $word) {

            // girdi ile sÃ¶zcÃ¼k arasındaki mesafeyi hesaplatalım
            $lev = levenshtein($input, $word);

            // Bir eÅŸleÅŸme var mı bakalım
            if ($lev == 0) {

                // en yakın sÃ¶zcÃ¼k bu olacak (tam eÅŸleÅŸme)
                $closest = $word;
                $shortest = 0;

                // Tam bir eÅŸleÅŸme bulduÄŸumuza gÃ¶re dÃ¶ngÃ¼den Ã§ıkalım
                break;
            }

            // EÄŸer bu mesafe bir Ã¶ncekinden kısaysa
            // veya en kısa mesafe henÃ¼z bulunamadıysa
            if ($lev <= $shortest || $shortest < 0) {
                // en yakın eÅŸleÅŸmeyi ve en kısa mesafeyi tanımlayalım
                $closest = $word;
                $shortest = $lev;
            }
        }

        $acceptable_diff = count($input_words) * 2; // 2 letter per word

        if ($shortest != 0 && $shortest < $acceptable_diff + 1) {
            return $closest;
        }
        return false;
    }

    function elastic_safe_query($s)
    {
        $s = str_replace('kitapadı:', 'BookName:', $s);
        $s = str_replace('yazaradı:', 'Author:', $s);
        $s = str_replace('basımyılı:', 'Copyright:', $s);
        $s = str_replace('yayınevi:', 'Publisher:', $s);
        $s = str_replace('basımyeri:', 'PrintLocation:', $s);
        $s = str_replace('kutuphaneno:', 'RowNumber:', $s);
        $s = str_replace('/', '\/', $s);
        return $s;
    }

    function get_mime($filename)
    {
        $idx = explode('.', $filename);
        $count_explode = count($idx);
        $idx = strtolower($idx [$count_explode - 1]);

        $mimet = array(
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'asc' => 'text/plain',
            'atom' => 'application/atom+xml',
            'avi' => 'video/x-msvideo',
            'bcpio' => 'application/x-bcpio',
            'bmp' => 'image/bmp',
            'cdf' => 'application/x-netcdf',
            'cgm' => 'image/cgm',
            'cpio' => 'application/x-cpio',
            'cpt' => 'application/mac-compactpro',
            'crl' => 'application/x-pkcs7-crl',
            'crt' => 'application/x-x509-ca-cert',
            'csh' => 'application/x-csh',
            'css' => 'text/css',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'djv' => 'image/vnd.djvu',
            'djvu' => 'image/vnd.djvu',
            'doc' => 'application/msword',
            'dtd' => 'application/xml-dtd',
            'dvi' => 'application/x-dvi',
            'dxr' => 'application/x-director',
            'eps' => 'application/postscript',
            'etx' => 'text/x-setext',
            'ez' => 'application/andrew-inset',
            'gif' => 'image/gif',
            'gram' => 'application/srgs',
            'grxml' => 'application/srgs+xml',
            'gtar' => 'application/x-gtar',
            'hdf' => 'application/x-hdf',
            'hqx' => 'application/mac-binhex40',
            'html' => 'text/html',
            'html' => 'text/html',
            'ice' => 'x-conference/x-cooltalk',
            'ico' => 'image/x-icon',
            'ics' => 'text/calendar',
            'ief' => 'image/ief',
            'ifb' => 'text/calendar',
            'iges' => 'model/iges',
            'igs' => 'model/iges',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'kar' => 'audio/midi',
            'latex' => 'application/x-latex',
            'm3u' => 'audio/x-mpegurl',
            'man' => 'application/x-troff-man',
            'mathml' => 'application/mathml+xml',
            'me' => 'application/x-troff-me',
            'mesh' => 'model/mesh',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mif' => 'application/vnd.mif',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2' => 'audio/mpeg',
            'mp3' => 'audio/mpeg',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpga' => 'audio/mpeg',
            'ms' => 'application/x-troff-ms',
            'msh' => 'model/mesh',
            'mxu m4u' => 'video/vnd.mpegurl',
            'nc' => 'application/x-netcdf',
            'oda' => 'application/oda',
            'ogg' => 'application/ogg',
            'pbm' => 'image/x-portable-bitmap',
            'pdb' => 'chemical/x-pdb',
            'pdf' => 'application/pdf',
            'pgm' => 'image/x-portable-graymap',
            'pgn' => 'application/x-chess-pgn',
            'php' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'png' => 'image/png',
            'pnm' => 'image/x-portable-anymap',
            'ppm' => 'image/x-portable-pixmap',
            'ppt' => 'application/vnd.ms-powerpoint',
            'ps' => 'application/postscript',
            'qt' => 'video/quicktime',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ras' => 'image/x-cmu-raster',
            'rdf' => 'application/rdf+xml',
            'rgb' => 'image/x-rgb',
            'rm' => 'application/vnd.rn-realmedia',
            'roff' => 'application/x-troff',
            'rtf' => 'text/rtf',
            'rtx' => 'text/richtext',
            'sgm' => 'text/sgml',
            'sgml' => 'text/sgml',
            'sh' => 'application/x-sh',
            'shar' => 'application/x-shar',
            'shtml' => 'text/html',
            'silo' => 'model/mesh',
            'sit' => 'application/x-stuffit',
            'skd' => 'application/x-koan',
            'skm' => 'application/x-koan',
            'skp' => 'application/x-koan',
            'skt' => 'application/x-koan',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'snd' => 'audio/basic',
            'spl' => 'application/x-futuresplash',
            'src' => 'application/x-wais-source',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc' => 'application/x-sv4crc',
            'svg' => 'image/svg+xml',
            'swf' => 'application/x-shockwave-flash',
            't' => 'application/x-troff',
            'tar' => 'application/x-tar',
            'tcl' => 'application/x-tcl',
            'tex' => 'application/x-tex',
            'texi' => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'tgz' => 'application/x-tar',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'tr' => 'application/x-troff',
            'tsv' => 'text/tab-separated-values',
            'txt' => 'text/plain',
            'ustar' => 'application/x-ustar',
            'vcd' => 'application/x-cdlink',
            'vrml' => 'model/vrml',
            'vxml' => 'application/voicexml+xml',
            'wav' => 'audio/x-wav',
            'wbmp' => 'image/vnd.wap.wbmp',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wml' => 'text/vnd.wap.wml',
            'wmlc' => 'application/vnd.wap.wmlc',
            'wmlc' => 'application/vnd.wap.wmlc',
            'wmls' => 'text/vnd.wap.wmlscript',
            'wmlsc' => 'application/vnd.wap.wmlscriptc',
            'wmlsc' => 'application/vnd.wap.wmlscriptc',
            'wrl' => 'model/vrml',
            'xbm' => 'image/x-xbitmap',
            'xht' => 'application/xhtml+xml',
            'xhtml' => 'application/xhtml+xml',
            'xls' => 'application/vnd.ms-excel',
            'xml xsl' => 'application/xml',
            'xpm' => 'image/x-xpixmap',
            'xslt' => 'application/xslt+xml',
            'xul' => 'application/vnd.mozilla.xul+xml',
            'xwd' => 'image/x-xwindowdump',
            'xyz' => 'chemical/x-xyz',
            'zip' => 'application/zip'
        );

        if (isset ($mimet [$idx])) {
            return $mimet [$idx];
        } else {
            return 'application/octet-stream';
        }
    }


    function addActionToLog($file, $action, $args = array())
    {
        if ($action == "search" && !trim($args["query"])) {
            return false;
        }
        $insert = array(
            'user' => current_user_name(),
            'date' => new MongoDB\BSON\UTCDateTime(time()),
            'action' => $action,
            'platform' => 'mecelle',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'userIp' => $_SERVER ['REMOTE_ADDR'],
            'programName' => basename($file, '.php')
        ) // __FILE__ should be defined where it is calling
        ;
        if (!empty ($args)) {
            $args = handleArgs($args);
            $insert = array_merge($insert, $args);
        }

        $result = InsertToMongo('activity_log', $insert);
        return $result;
    }

    /*rename field names to handle access log collection in mongo*/
    function handleArgs($args)
    {
        $excludedFields = array("repository", "CommitDesc", "file", "username", "processedUser", "docsUserId", "commitDesc", "error", "realFileName", "medium", "library", "client", "folder");
        $fieldList = array("type" => "docType", "query" => "searchQuery", "page" => "pageID", "filename" => "fileName", "prettyDocName" => "fileDisplayName", "filediskname" => "docName");
        $newArgs = array();
        foreach ($args as $key => $arg) {
            if (!in_array($key, $excludedFields)) {
                $newKey = str_replace(array_keys($fieldList), array_values($fieldList), $key);
                $newArgs[$newKey] = $arg;
            }
        }

        return $newArgs;
    }

    /*
     * function is_permission($repo){
     *
     * foreach($_SESSION['repositories'] as $rep){
     *
     * if($repo['path']==$rep['path_with_namespace']){
     *
     * return true;
     * }
     * }
     *
     * return false;
     * }
     */


    /*
    if(isset($_SESSION['ctrl']))
    //var_dump($_SESSION['ctrl']);*/

    function getFileNameForCover($filename)
    {
        return "coverImg/" . $filename . ".jpg";
    }

    function strtoupperTR($str)
    {
        $str = str_replace(array('i', 'ı', 'ü', 'ğ', 'ş', 'ö', 'ç'), array('İ', 'I', 'Ü', 'Ğ', 'Ş', 'Ö', 'Ç'), $str);
        return strtoupper($str);
    }

    function strtolowerTR($str)
    {
        $str = str_replace(array('İ', 'I', 'Ü', 'Ğ', 'Ş', 'Ö', 'Ç'), array('i', 'ı', 'ü', 'ğ', 'ş', 'ö', 'ç'), $str);
        return strtolower($str);
    }

    function strtolowerEN($str)
    {
        $str = str_replace(array('i', 'ı', 'ü', 'ğ', 'ş', 'ö', 'ç'), array('i', 'i', 'u', 'g', 's', 'o', 'c'), $str);
        return strtolower($str);
    }

//hangi diske olduğu belirleniyor.
    function getDistributedImagePath($filename)
    {
        global $sourceArrayOfOCR;
        $pathImg = "https://img.gsimecelle.com/img/";
        $sourceName = explode("-", $filename);
        $sourceName = $sourceName[0];
        $keyExist = false;
        $keyExist = array_key_exists($sourceName, $sourceArrayOfOCR);
        if ($keyExist) $pathImg = $sourceArrayOfOCR[$sourceName];
        return $pathImg;
    }

    $sourceArrayOfOCR = array(
        'rkbkrm' => "https://img.gsimecelle.com/imgc1/",
        'yoktez' => "https://img.gsimecelle.com/imgc2/",
        'spk' => "https://img.gsimecelle.com/imgc2/",
        'epdk' => "https://img.gsimecelle.com/imgc2/",
        'crwbddk' => "https://img.gsimecelle.com/imgc2/",
        'igsi' => "https://img.gsimecelle.com/imgc2/"
    );


    function redirect($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        die();
    }


    function docIds($resultsPaged)
    {
        global $settings;
        $elasticIds = array();
        $owncloudPermissions = new PermissionRepository();

        foreach ($resultsPaged as $results2) {
            /*  var_dump($results2);
             die();*/
            $results = $results2->tops->hits->hits[0];
            $docType = $results->_type;
            $index = $results->_index;
            // var_dump($docType);
            $dId = $results->_id;
            $status = true;
            if ($index == "gsiclientv2e5") $index = "gsiclientv2";
            /*  var_dump($index);
            die();*/
            if ($docType == "documents" || $docType == "document") {

                $status = false;

                if ($index == $settings['elastic']['indexForIgsi']) $dId = getMappedId($results->_index, $results->_source->RepoName, $results->_source->clientNameId, $results->_source->matterId);
                else {
                    $re = '/ncld_[0-9]*/';

                    preg_match_all($re, $dId, $matches, PREG_SET_ORDER, 0);
                    $dId = $matches[0][0];
                    $dId = fixDocId($dId);
                }

                $status = $owncloudPermissions->getPermissions($dId);
            }


            $elasticIds[] = $status;
        }
        return $elasticIds;
    }

    function fixDocId($docId)
    {
        return substr($docId, 5);
    }

    function getFilePermission($docId)
    {
        $owncloudPermissions = new PermissionRepository();
        $status = $owncloudPermissions->getPermissions($docId);
        return $status;

    }


    function getFilePermissionForIgsi($fileJson)
    {
        if (is_admin()) return true;
        $results = $fileJson;

        $docId = getMappedId($results->_index, $results->_source->RepoName, $results->_source->clientNameId, $results->_source->matterId);
        $permission = getFilePermission($docId);
        return $permission;

    }

    function getMappedId($index, $repo, $clientNameId, $projectId)
    {
        global $settings;
        global $mem;
        if ($index == "gsiclientv2e5") $index = "gsiclientv2";
        //var_dump($index); die();

        if ($index == $settings['elastic']['indexForIgsi'] || $index == $settings['elastic']['indexForIgsiFile']) {
            // var_dump($clientNameId .":". $projectId); die();
            if ($mem != NULL) $mappedData = $mem->get("mappedArrayNextCloud");
            // $mappedData = NULL;
            if ($mappedData == NULL) {
                $query = array();
                $mongoRes = CallMongo("igsi", $query, "gsiMapping");
                $mappedArray = array();
                foreach ($mongoRes->response as $mresult) {
                    $pId = $mresult ['projectId'];
                    $pName = $mresult ['projectName'];
                    $cName = $mresult ['clientName'];
                    $cId = $mresult ['clientId'];
                    $nextCloudId = $mresult ['nextcloudId'];

                    $mappedArray[$cId . ":" . $pId] = $nextCloudId;

                }
                if ($mem != NULL) {
                    $mem->set("mappedArrayNextCloud", $mappedArray, CACHE_LONG) or die ("Memcache Hatası");
                    $mappedData = $mem->get("mappedArrayNextCloud");
                } else $mappedData = $mappedArray;
            }

            // var_dump($mappedData);
            // die();
            $mextCloudId = $mappedData[$clientNameId . ":" . $projectId];
            if ($mextCloudId == NULL) $mextCloudId = -1;

            return $mextCloudId;
        } else {

            return null;
        }


    }

    function getCookie($cookie_name)
    {

        $mycookie = $_COOKIE[$cookie_name];
        return $mycookie;

    }

    function decodeData($encrypted)
    {

        $key = '3c5340eca1e0a1ac201e4ae648ba11f2';

        // $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))));
        $decrypted = Crypto::decrypt($encrypted);

        return $decrypted;

    }

    function cidr_match($ip)
    {

        $network = "192.168.113.0/24";
        $cidr = "/24";
        list($subnet, $mask) = explode('/', $network);

        if (!((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet))) {
            return true;
        }

        return false;
    }

    function fixNameForId($fullName)
    {
        $myDocName = $fullName;
        $docArr = explode('_', $myDocName);
        $docArrShift = array_shift($docArr);
        $name = explode($docArrShift . "_", $myDocName);
        if ($name[1] != "") $myDocName = "..." . $name[1];
        return $myDocName;
    }

    function getElasticSearchQuery($query_string)
    {
        $query_string = trLowerCase($query_string);
        $query_string = str_replace("\\", "\\\\", $query_string); // \ karekteri escape edilir.. hilmig
        $query_string = str_replace(" - ", " ", $query_string); //aramada problem çıkarıyor. hilmig
        $query_string = str_replace(" / ", " ", $query_string); //aramada problem çıkarıyor. hilmig
        $query_string = preg_replace('/\s+/', ' ', $query_string);
        $query_string = str_replace(" OR ", " {OR} ", $query_string);
        $re = '/(?:(?:"(?:\\\\"|[^"])+"))~[0-9]*/is';
        preg_match_all($re, $query_string, $matchesQuoteTilde);
        foreach ($matchesQuoteTilde[0] as $match) {
            $query_string = str_replace($match, "", $query_string);
        }

        $re = "/(?:(?:\"(?:\\\\\"|[^\"])+\"))/is";
        preg_match_all($re, $query_string, $matchesQuote);
        foreach ($matchesQuote[0] as $match) {
            $query_string = str_replace($match, "", $query_string);
        }


        $query_string = explode(" ", trim($query_string));
        $query_string_holder = "";
        foreach ($query_string as $key => $word) {
            if (strpos($word, '~') !== false || strpos($word, '*') !== false || strpos($word, '/') || strpos($word, '^') !== false) {
                $query_string_holder = $query_string_holder . " " . $word;
            } elseif ($word == "") {
                continue;
            } else {
                $query_string_holder = $query_string_holder . " " . $word . "*";
            }
        }


        foreach ($matchesQuote[0] as $match) {
            $query_string_holder = $query_string_holder . " " . $match . " ";
        }
        foreach ($matchesQuoteTilde[0] as $match) {
            $query_string_holder = $query_string_holder . " " . $match . " ";
        }

        //TODO: REGEXPLE TOPLU HALE GETİR
        $query_string_holder = preg_replace_callback('~(?:^|})(.*?)(?:\{|$)~', function ($match) {
            return strtolowerTR($match[1]);
        }, $query_string_holder);
        $query_string_holder = str_replace("OR*", "OR", $query_string_holder);
        $query_string_holder = str_replace("/", "\\/", $query_string_holder);
        $query_string_holder = str_replace("(", "\\(", $query_string_holder);
        $query_string_holder = str_replace(")", "\\)", $query_string_holder);
        $query_string_holder = str_replace("[", "\\[", $query_string_holder);
        $query_string_holder = str_replace("]", "\\]", $query_string_holder);
        $query_string_holder = str_replace("{", "\\{", $query_string_holder);
        $query_string_holder = str_replace("}", "\\}", $query_string_holder);
        $query_string_holder = str_replace(":", "\\:", $query_string_holder);

        $re = '/\*(.*?)(\**)|(\*.*$)/';
        $query_string_holder = preg_replace($re, "*", $query_string_holder);

        return trim($query_string_holder);

    }

    function getPageNumFromId($Id)
    {


        $array = explode("_page_", $Id);
        $pageNumTmp = $array[1];
        $pageNum = explode(".", $pageNumTmp);

        return (int)$pageNum[0];

    }


    function fixEpocDate($epoc)
    {

        return $epoc + epocDateFix;
    }

    function object_to_array($data)
    {
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = object_to_array($value);
            }
            return $result;
        }
        return $data;
    }


    function humanTiming($time)
    {

        $time = time() - $time; // to get the time since that moment
        $time = ($time < 1) ? 1 : $time;
        $tokens = array(
            31536000 => 'yıl',
            2592000 => 'ay',
            604800 => 'hafta',
            86400 => 'gün',
            3600 => 'saat',
            60 => 'dakika',
            1 => 'saniye'
        );

        foreach ($tokens as $unit => $text) {
            if ($time < $unit) continue;
            $numberOfUnits = floor($time / $unit);
            return $numberOfUnits . ' ' . $text . ' önce';
        }

    }



    function getUserPermission($cookie_name)
    {
        $str = (decodeData(getCookie($cookie_name)));
        if (!stristr($str, "GSI-Raporlama")) {
            return true;
        } else {
            return false;
        }

    }

    function isContains($str, $findme)
    {
        $pos = strpos($str, $findme);
        if ($pos === false) {
            return false;
        } else {
            return true;
        }
    }

    function trLowerCase($str)
    {
        $str = str_replace(array('Â', 'â', 'Î', 'î', 'Û', 'û'), array('A', 'a', 'İ', 'i', 'U', 'u'), $str);
        $str = str_replace(array('İ', 'I', 'ı', 'Ü', 'ü', 'Ğ', 'ğ', 'Ş', 'ş', 'Ö', 'ö', 'Ç', 'ç'), array('i', 'i', 'i', 'u', 'u', 'g', 'g', 's', 's', 'o', 'o', 'c', 'c'), $str);
        return $str;
    }

    //bu UniformDisplayAllResources.php de var.
    function getPageNumber($requestedDocumentId)
    {

        $re = '/_page_[0-9]*/';
        preg_match_all($re, $requestedDocumentId, $matches, PREG_SET_ORDER, 0);
        $pn = $matches[0][0];
        $pn = substr($pn, 6);
        return $pn;

    }

    //bu UniformDisplayAllResources.php de var.
    function doIdForHTML($requestedDocumentId)
    {
        $pn = getPageNumber($requestedDocumentId);
        $docIdFix = explode("_page_", $requestedDocumentId);
        $docIdFixMain = $docIdFix[0];
        $docIdFixMainCleared = $docIdFixMain;//str_replace(".", "", $docIdFixMain);
        return $docIdFixMainCleared . "_page_" . $pn;
    }

    function securityCheck($sId)
    {
        // echo "sid=" . $sId; "\n";
        $cId = Crypto::decrypt($sId);
        // echo "sssssid=" . $cId; "<br>"; die();
        parse_str($cId, $var);
        $loggedInuserName = current_user_name();
        $subtraction = (time() - $var["date"]);

        if ($var["userName"] != $loggedInuserName || $subtraction > 5000000) {
            echo "Yetkisiz kullanıcı yada geçmiş zamanlı indirme işlemi....Doküman görüntüleme sayfasını yenileyip yeniden deneyiniz.";
            die();
        }

        return $var;


    }

    function formatMongoDateToTr($dateString)
    {
        $epoch = strtotime($dateString);
        return date("d-m-Y h:i", $epoch);
    }

    function getCompanyID()
    {
        return isset($_COOKIE["companyID"]) ? Crypto::decrypt($_COOKIE["companyID"]) : 1;
    }


    function is_admin()
    {

        $accessController = new PermissionRepository();
        return $accessController->is_admin();
    }
