<?php
require "../public/galleryFuncs.php";
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;
Kint::$enabled_mode = false;
define('CPGBASE', realpath($_SERVER['DOCUMENT_ROOT'].'/../gallery/').'/');
define('PICTURE_LOC', realpath($_SERVER['DOCUMENT_ROOT'].'/../gallery/albums/').'/');
define('FULLPATH', realpath($_SERVER['DOCUMENT_ROOT'].'/../gallery/').'/');
$logger;
class CpgMapper extends Mapper {


  function GetPictures()
  {
    $sql = "SELECT p.pid, p.filepath, p.filename, p.srcset, p.user1 as photographer, p.pwidth, p.pheight, p.title, p.caption, a.title AS album FROM cpg132_pictures AS p "
    ."JOIN cpg132_albums AS a USING (aid) WHERE filename like '%.jpg' AND substr( a.title, 1, 2) = '20' ORDER BY p.aid DESC  LIMIT 30";

    $pictures = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($pictures as &$pic){
      $pic['filepath']= 'albums/'.$pic['filepath'];
      $file = $pic['filename'];
      $thumb= 'thumb_'.$pic['filename'];
      $normal= 'normal_'.$pic['filename'];
      // if (!file_exists($pic['filepath'].$normal))$normal = $file;
      // if (!file_exists($pic['filepath'].$thumb))$thumb = $normal;
      $pic['normal'] = $normal;
      $pic['thumb'] = $thumb;
      $pic['srcset'] = $this->calcSrcset($pic['filepath'], $pic['filename'],$pic['srcset']);
    }
    return $pictures;
  }

   function AlbumList()
    {
      try {
        $sql = 'SELECT p.aid, count(p.aid), a.title as album FROM cpg132_pictures AS p JOIN cpg132_albums AS a USING (aid) GROUP BY aid DESC' ;
        // $sql = 'SELECT aid, title FROM cpg132_albums ORDER BY title DESC  LIMIT 30';
        $this->logger->addInfo("AlbumList ".$sql);
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $result;

      } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        $this->logger->addError("AlbumList ", $e);
        return $e->getMessage();
      }

    }
   function getAlbums($year)
    {
      try {
        $sql = "SELECT p.aid as aid, count(p.aid) as count, a.title as title FROM cpg132_pictures AS p JOIN cpg132_albums AS a USING (aid) WHERE substr(a.title, 1, 4) = '$year' GROUP BY aid DESC ORDER BY a.title DESC" ;
        // $sql = 'SELECT aid, title FROM cpg132_albums ORDER BY title DESC  LIMIT 30';
        $this->logger->addInfo("AlbumList ".$sql);
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $result;

      } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        $this->logger->addError("AlbumList ", $e);
        return $e->getMessage();
      }

    }
   function getYears()
    {
      try {
        $sql = 'SELECT count(aid) as count, substr(title, 1, 4) as year FROM cpg132_albums GROUP BY year DESC ORDER BY year DESC' ;
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $result;

      } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        $this->logger->addError("AlbumList ", $e);
        return $e->getMessage();
      }

    }
   function AlbumPictures($aid)
    {
      $sql = "SELECT pid, filepath, filename, srcset, user1 as photographer, pwidth, pheight, title, caption FROM `cpg132_pictures` WHERE aid = $aid";
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
   function getAlbum($aid)
    {
      $sql = "SELECT pid, filepath, filename, srcset, user1 as photographer, pwidth, pheight, title, caption FROM `cpg132_pictures` WHERE aid = $aid";
        $pictures = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($pictures as &$pic){
          $pic['filepath']= 'albums/'.$pic['filepath'];
          $thumb= 'thumb_'.$pic['filename'];
          $normal= 'normal_'.$pic['filename'];
          if (file_exists(CPGBASE.$pic['filepath'].$normal))$pic['normal'] = $normal;
          if (file_exists(CPGBASE.$pic['filepath'].$thumb))$pic['thumb'] = $thumb;
          $pic['srcset'] = $this->calcSrcset($pic['filepath'], $pic['filename'],$pic['srcset']);
        }
        return $pictures;
    }
   function Get10001Pictures($aid)
    {
      $sql = "SELECT pid, filepath, filename, srcset, user1 as photographer, pwidth, pheight, title, caption FROM `cpg132_pictures` ";
        $pictures = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        // var_dump($pictures);
        $result = [];
        foreach ($pictures as $i => $p)
        {
          if (substr($p['filepath'], 0, 14) !== 'userpics/10001')continue;
          // var_dump($p);
          $ps = [$p['filepath'], $p['pwidth'], $p['pheight']];
          // $txt = "";
          // $txt .= $this->FindImageSize($p['filepath'], $p['filename'], "thumb_");
          // $txt .= $this->FindImageSize($p['filepath'], $p['filename'], "normal_");
          // $txt .= $this->FindImageSize($p['filepath'], $p['filename'], "");
          $ps[] = $this->calSrcset($p['filepath'], $p['filename'],$p['srcset']);
          var_dump($ps);
          $result[] = $ps;
        }
        return $result;
    }

    function upload ( $request, $response){
      global $logger;
      $logger = $this->logger;
      $this->logger->addInfo("In cpg mapper - upload");
      // $album = "1917-07-01_somewhere";
      // $photographer = "Aidan Nichol";
      try {
        extract($request->getParsedBody());
        $this->logger->warning("upload data ".serialize($request->getParsedBody()), $request->getParsedBody());
        $this->logger->warning("upload to album ".$album);
        $this->logger->warning("upload to photographer ".$photographer);

        $uploadedFiles = $request->getUploadedFiles();
        $directory = CPGBASE.'albums/temp/uploaded.jpg';
        $directory = CPGBASE.'albums/temp';
        $this->logger->addInfo("Uploaded request", $uploadedFiles);
        $data = [];

        // $photos = $uploadedFiles['photos'];
        // if (!is_array($photos))$photos = [$photos];
        // $this->logger->info(" count:".count($photos));
        // handle single input with multiple file uploads
        foreach ($uploadedFiles as $photo) {
          // foreach ($photos as $photo) {
          $this->logger->addInfo("Uploaded file");
          // $data = ['sz' => $photo->getSize(),
          //         'error' => $photo->getError(),
          //         "ok" => UPLOAD_ERR_OK,
          //         'name' => $photo->getClientFilename(),
          //         'type' => $photo->getClientMediaType()];
          // $this->logger->addInfo("Uploaded file", $data);
          if ($photo->getError() === UPLOAD_ERR_OK) {
            $filename = $photo->getClientFilename();
            // $filename = $this->moveUploadedFile($directory, $photo);
            $tmpFile = "{$directory}/{$filename}";
            $this->logger->info("About to move $tmpFile");
            $res = $photo->moveTo($tmpFile);
            $this->logger->info("Moved $tmpFile");
            // $this->logger->addInfo("Moved to ".$res);
            $res = process_picture( $this->db, $tmpFile, $filename, $album, $photographer);
            // $this->addPicture($album, $photographer, "{$directory}/{$filename}", $filename);
            $ret = ['filename'=>$filename, 'id'=>'1', 'originalName'=>$filename, 'url'=>$res];
            $this->logger->addInfo("processed ".$res, $ret);
            $data[] = $ret;
            // $response->write('uploaded ' . $filename . '<br/>');
          }
        }
        $this->logger->info("returning ", $data);
        return $response->withJson($data);

      } catch (Exception $e) {
        $msg = "Upload Error at {$e->getFile()} line {$e->getLine()}: {$e->getCode()} - {$e->getMessage()}";
        $this->logger->error($msg);
        return $response->getBody()->write($msg);
      }
    }

    function calcSrcset($path, $nam, $scrset)
    {
      if (strlen($scrset) > 10) return $scrset;
      $srcset = '';
      $opts = ['thumb_', 'normal_', ''];
      // $base="/www/sites/steds-server/Coppermine/";
      foreach($opts as $opt){
        $file = "{$path}{$opt}{$nam}";
        $this->logger->addInfo("calcSrcset checking: ". CPGBASE.$file);
        if (!file_exists(CPGBASE.$file))continue;
        $size = getimagesize(CPGBASE.$file);

        // $srcset .= "{$file} {$size[0]}w {$size[0]}w, ";
        $srcset .= str_replace(" ", "%20", $file)." {$size[0]}w, ";

      }
      $this->logger->addInfo("calcSrcset result: ". $srcset);
      return trim($srcset, ', ');
    }
    function FindImageFile($nam)
    {
        $rt = WALKDATA;
        if (file_exists("{$rt}{$nam}.pdf"))return "/$nam.pdf";
        if (file_exists("{$rt}{$nam}.jpg"))return "/$nam.jpg";
        if (file_exists("{$rt}{$nam}.png"))return "/$nam.png";
        if (file_exists("{$rt}{$nam}.bmp"))return "/$nam.bmp";
        return "walkdata/mapnotavailable.pdf";
    }
    function addPicture($albumName, $photographer, $filename = 'High-up.jpg') {
      $tempfile = CPGBASE."userpics/temp/{$filename}";
      copy(CPGBASE.'userpics/10001/High-up.jpg', $tempfile);
      $res = process_picture($this->db, $tempfile, $filename, $albumName, $photographer);
      var_dump($res);
      return $res;
    }
    function getAlbumId($albumName)
    {
      $albumDate = substr($albumName, 0, 10);
      $alb = $this->db->query("SELECT aid, title FROM cpg132_albums WHERE substr( title, 1, 10 ) = '{$albumDate}'")->fetchAll(PDO::FETCH_ASSOC);
      // Kint::dump($alb);
      // var_dump($alb);
      if (count($alb) !== 0)return $alb['aid'];
      // Doesn't exist so creat it
      // $category = 19; //assume 2017 for now
      $query = "INSERT INTO cpg132_albums (category, title, uploads, pos) VALUES ('20', '{$albumName}', 'NO',  '0')";
      $this->db->query($query);
      $aid = $this->db->lastInsertId();
      return $aid;
    }
    /**
    * Moves the uploaded file to the upload directory and assigns it a unique name
    * to avoid overwriting an existing uploaded file.
    *
    * @param string $directory directory to which the file is moved
    * @param UploadedFile $uploaded file uploaded file to move
    * @return string filename of moved file
    */
    function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
      $this->logger->info("moveUploadedFile {$directory}");
      $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
      $this->logger->info("moveUploadedFile {$extension}");
      $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
      $this->logger->info("moveUploadedFile {$basename}");
      $filename = sprintf('%s.%0.8s', $basename, $extension);
      $this->logger->info("filename {$filename}");
      $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

      return $filename;
    }
}
