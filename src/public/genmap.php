<?php defined('SYSPATH') or die('No directscript access.');
define( 'MARGIN', 10);
    function imagecreatefrombmp($p_sFile)
    {
        //    Load the image into a string
        $file    =    fopen($p_sFile,"rb");
        $read    =    fread($file,10);
        while(!feof($file)&&($read<>""))
            $read    .=    fread($file,1024);

        $temp    =    unpack("H*",$read);
        $hex    =    $temp[1];
        $header    =    substr($hex,0,108);
        //    Process the header
        //    Structure: http://www.fastgraph.com/help/bmp_header_format.html
        if (substr($header,0,4)=="424d")
        {
            //    Cut it in parts of 2 bytes
            $header_parts    =    str_split($header,2);

            //    Get the width        4 bytes
            $width            =    hexdec($header_parts[19].$header_parts[18]);

            //    Get the height        4 bytes
            $height            =    hexdec($header_parts[23].$header_parts[22]);
            $pixelSize = hexdec($header_parts[28])/8;
            //    Unset the header params
            unset($header_parts);
        }

        //    Define starting X and Y
        $x                =    0;
        $y                =    1;

        //    Create newimage
        $image            =    imagecreatetruecolor($width,$height);

        //    Grab the body from the image
        $body            =    substr($hex,108);

        //    Calculate if padding at the end-line is needed
        //    Divided by two to keep overview.
        //    1 byte = 2 HEX-chars
        $body_size        =    (strlen($body)/2);
        $header_size    =    ($width*$height);

        //    Use end-line padding? Only when needed
        $usePadding        =    ($body_size>($header_size*$pixelSize)+4);

        //    Using a for-loop with index-calculation instaid of str_split to avoid large memory consumption
        //    Calculate the next DWORD-position in the body
        for ($i=0;$i<$body_size;$i+=$pixelSize)
        {
            //    Calculate line-ending and padding
            if ($x>=$width)
            {
                //    If padding needed, ignore image-padding
                //    Shift i to the ending of the current 32-bit-block
                if ($usePadding)
                    $i    +=    $width%4;

                //    Reset horizontal position
                $x    =    0;

                //    Raise the height-position (bottom-up)
                $y++;

                //    Reached the image-height? Break the for-loop
                if ($y>$height)
                    break;
            }

            //    Calculation of the RGB-pixel (defined as BGR in image-data)
            //    Define $i_pos as absolute position in the body
            $i_pos    =    $i*2;
            $clr = substr($body, $i*2, 6);
            $r        =    hexdec($clr[4].$clr[5]);
            $g        =    hexdec($clr[2].$clr[3]);
            $b        =    hexdec($clr[0].$clr[1]);
            //    Calculate and draw the pixel
            $color    =    imagecolorallocate($image,$r,$g,$b);
            imagesetpixel($image,$x,$height-$y,$color);

            //    Raise the horizontal position
            $x++;
        }
        // Kint::dump($colours);
        //    Unset the body / free the memory
        unset($body);

        //    Return image-object
        return $image;
    }

class Genmap_Controller extends Controller {
    public $data;
    public $map;
    public $libdir;
    function __construct()
    {
        parent::__construct();
        $this->data = new Stedsdata;
        header("Access-Control-Allow-Origin: *");
    }

	function index()
	{
        $view = new View('genMap');
        $view->set_global('title',"Generate Route Maps");
        $view->render(TRUE);
	}
	function showMap($dat)
	{
        $this->template->set_global('title',"Route Map $dat");
        $this->template->main = new View('showMap', $this->data->GetMapData($dat));
        $this->template->main->dbg = kohana::debug($this->data->GetMapData($dat));
    }

    // function xUpdateMapSvG($walkdate)
    // {
    //     if (!request::is_ajax()) print("<pre>");
    //     $walkdate = $this->uri->segment('UpdateMap', date('Y-m-d'));
    //     $this->libdir = WALKDATA.substr($walkdate, 0, 4)."/$walkdate";
    //     $gm = new Genmap($this->libdir);
    //     if(1):
    //     // accumulate the data
    //     $this->map = $gm->BuildMapData($walkdate);
    //     flush();

    //     //Generate and save the SVG File
    //     $fil= "$this->libdir/map-$walkdate.svg";
    //     file_put_contents($fil, View::factory('mapsvg/svgMain')->render());
    //     flush();
    //     endif;
    //     //$this->map = $gm->RestoreMapData($walkdate);
    //     //echo var_dump($this->map);
    //     // Convert the SVG file to JPG and generate the graphic headers
    //     $gm->ConvertMapToJPG($walkdate);
    //     flush();
    //     // Generate the hotpoint overlays  --------------------------------*/
    //     $fil= "$this->libdir/map-$walkdate.ovl";
    //     file_put_contents($fil, View::factory('mapsvg/mapOverlay')->render());

    //     if (!request::is_ajax()) print("</pre>");
    //     return ;
    // }
    // function xSvgMap($dat)
    // {
    //     $gm = new Genmap();
    //     $dat = $this->uri->segment(3, date('Y-m-d'));
    //     $this->map = $gm->RestoreMapData($dat);
    //     //echo kohana::debug($this->map);
    //     echo "<pre>".htmlentities(View::factory('mapsvg/svgMain')->render())."</pre>";
    // }
    function UpdateMap($walkdate)
    {
        $this->libdir = WALKDATA."walkdata/".substr($walkdate, 0, 4)."/$walkdate";
        $gm = new Genmap($this->libdir);
        if (1):
        if (!request::is_ajax()) print("<pre>");
        // accumulate the data
        $this->map = $gm->BuildMapData($walkdate);
        endif;
        //$gm = new Genmap($this->libdir);
        //$walkdate = $this->uri->segment('PdfMap', date('Y-m-d'));
        $this->map = $gm->RestoreMapData($walkdate);
        //echo kohana::debug($this->map);
        echo View::factory('mappdf/pdfMain')->render();
        print "</pre>";
    }
    function getImage($file)
    {
        $walkdate = substr($file,8,10);
        $typ = substr($file, -3);
        $this->libdir = WALKDATA."walkdata/".substr($walkdate, 0, 4)."/$walkdate";
        $fil = "{$this->libdir}/{$file}";
        if($typ==='jpg')$im = imagecreatefromjpeg($fil);
        if($typ==='bmp')$im = imagecreatefrombmp($fil);

        header('Content-Type: image/png');

        imagepng($im);
        imagedestroy($im);

    }
    function updateWalkWithRemoteData($walkdate)
    {
        try{
        // get current data
        echo "updateWalkWithRemoteData: $walkdate\n";
        $walk = $this->data->GetWalkRawData($walkdate);
        // pull out the walk numbers already there
        $walkNo=array();
        foreach($walk['routes'] as $i=>$route)$walkNo[$route['no']]=$route;
        //var_dump($walk, $_POST);
        // get new remote data
        $upt = json_decode($_POST['data'], true);
        if ($upt == NULL) {
               echo 'json decoding error';
        }
        //return;
        foreach($upt['routes'] as $i=>$route)
        {
           //$j = extract($route, EXTR_OVERWRITE );
           $no = $route['no'];
            if (isset($walkNo[$no])){
                $route = array_diff_assoc($route,$walkNo[$no]);
                //$this->data->UpdateRoute($walkdate, $no, $leader, $distance,$mmdistance, $ascent, $descent);
                $this->data->UpdateRouteArray($walkdate, $no, $route);
            } else
                //$this->data->InsertRoute($walkdate, $no, $leader, $distance,$mmdistance, $ascent, $descent);
                $this->data->InsertRouteArray($route);
        }
        //array_diff();
        $data = $upt["walkDetails"];
        $data = array_diff_assoc($data, $walk["walkDetails"]);
        $this->data->UpdateWalkDetails($walkdate, $data);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getCode(), $e->getMessage(),"in",$e->getFile(),"at line",$e->getLine(), "\n";
        }

    }
    function putWalkDetails()
    {
        $dat = $this->uri->segment(3);
        if (isset($_POST['data'])){
            $upt = json_decode(stripslashes($_POST['data']), true);
        } else {
            $data = file_get_contents('php://input');
            $upt = json_decode(stripslashes($data), true);
        }

        // Kint::dump($_GET, $_POST, $this->uri, $data);
        // echo kohana::debug($upt);
        $this->data->UpdateWalkDetails($dat, $upt);
    }
    function updateWalkLeader($dat, $no)
    {
        //$dat = $this->uri->segment(3);
        $leader = $_GET['leader'];
        $this->data->UpdateRouteLeader($dat, $no, $leader);
    }
    function getWalkData()
    {
        $dat = $this->uri->segment(3, date('Y-m-d'));
        $walk = $this->data->GetWalkDetails($dat);
        echo json_encode($walk);
    }
    function GetNextWalkData()
    {
        $dat = $this->uri->segment(3, date('Y-m-d'));
        $walk = $this->data->GetWalkDetails($dat);
        echo json_encode($walk);
    }
    function getYearsDataIndex()
    {
        $yr = $this->uri->segment(3, date('Y'));
        $years = $this->data->GetYearsDataIndex($yr);
        echo json_encode($years);
    }
    function getYearsData()
    {
        $yr = $this->uri->segment(3, date('Y'));
        $years = $this->data->GetYearsData($yr);
        echo json_encode($years);
    }
    function getYears()
    {
        $years = $this->data->GetYears();
        echo json_encode($years);
    }
    function uploadWalkToWeb($walkdate)
    {
        $walk = $this->data->GetWalkRawData($walkdate);
        print "<pre>";
        $dir = substr($walkdate, 0, 4)."/$walkdate";
        $libdir = WALKDATA."walkdata/$dir";

            // set up basic connection
//        $site = "91.206.183.21";
        $site = "stedwardsfellwalkers.co.uk";
            $conn_id = ftp_connect($site);

            // login with username and password
            $login_result = ftp_login($conn_id, "walks@stedwardsfellwalkers.co.uk", "X1X5D9AEqdF4");
            // check connection
            if ((!$conn_id) || (!$login_result)) {
                die("FTP connection has failed !\n");
            }
            echo "connected to $site\n";
            echo "Current remote directory: " . ftp_pwd($conn_id) . "\n";
            flush();
            ftp_pasv($conn_id, true);

            // try to change the directory to somedir
            foreach(array(substr($walkdate, 0, 4), $walkdate) as $dir){
                if (@ftp_chdir($conn_id, $dir)) {
                    echo "Current remote directory is now: " . ftp_pwd($conn_id) . "\n";
                } else {
                    if (!ftp_mkdir($conn_id, $dir))echo "Couldn't change directory $dir\n" ;
                    if (ftp_chdir($conn_id, $dir)) {
                        echo "Current remote directory is now: " . ftp_pwd($conn_id) . "\n";
                    } else {
                        echo "Couldn't change directory $dir\n";
                    }
                }
            }
            flush();
            print("libdir=$libdir\n");
            $unchanged = [];
            $uploaded = [];
            if (file_exists($libdir) && $l1 = opendir($libdir))
            {
                while (false !== ($filx = readdir($l1)))
                {
                    if (is_dir("$filx") ) continue;
                    if (substr($filx, -4) == ".bmp")continue;
                    if (substr($filx, -5) == ".srlz")continue;
                    //print("processing $filx\n");
                    $file= "$libdir/$filx";
                    $remote_file = $filx;
                    //  get the last modified time
                    $mdtm_remote = ftp_mdtm($conn_id, $remote_file);
                    $mdtm_local = filemtime($file);

                    if ($mdtm_remote == $mdtm_local){
                        $unchanged[] = "$remote_file unchanged (". date("F d Y H:i:s.", $mdtm_remote).")";
                    } else {
                        if (ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) {
                            $uploaded[] = "$file successfully uploaded";
                            $dat = gmdate("YmdHis", $mdtm_local);
                            $cmd = "SITE UTIME $remote_file $dat $dat $dat UTC";
                            $buff = ftp_raw($conn_id, $cmd);
                            $uploaded[] = join("\n", $buff);
                         } else {
                            echo "There was a problem while uploading $file\n";
                        }
                    }
                    flush();
                    //sleep(1);
                }
            }
            Kint::dump($libdir, $unchanged, $uploaded);
             // close the connection
            ftp_close($conn_id);
            $wk = json_encode($walk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
            /*if (json_last_error() != JSON_ERROR_NONE){
                echo "JSON encoding Error: ".json_last_error()."<BR>";
                echo "Uploaded aborted!";
                return;
            } */
            //var_dump(array('wks'=>$walk));
            $this->do_post_request(
                //"http://kohsteds/genmap/updateWalkWithRemoteData/$walkdate",
                "http://www.stedwardsfellwalkers.co.uk/genmap/updateWalkWithRemoteData/$walkdate",
//                "http://91.206.183.21/genmap/updateWalkWithRemoteData/$walkdate",
                $wk);

//             $this->updateWalkWithRemoteData($walkdate);
            echo "upload request completed\n";
            print "</pre>";
            flush();
    }
    function do_post_request($url, $data)
      {
        ini_set("track_errors", 1);
        $ch = curl_init();
        curl_setopt_array($ch, array(CURLOPT_URL=>$url,
                              CURLOPT_FAILONERROR=>1,
                              CURLOPT_TIMEOUT=>3,
                              CURLOPT_RETURNTRANSFER=>1,
                              CURLOPT_POST=>1,
                              CURLOPT_POSTFIELDS=>"data=$data"));
         $result = curl_exec($ch);
         if (curl_errno($ch)!=0){
             print_r(curl_getinfo($ch));
             echo "\ncurl error number:".curl_errno($ch)."\n";
             echo "\ncurl error:" .curl_error($ch)."\n\n";
         }

         curl_close($ch);
         Kint::dump($result);
      }

}
