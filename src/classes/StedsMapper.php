<?php
$intFields = ['left'=>1, 'right'=>1, 'top'=>1, 'bottom'=>1, 'viaTT'=>1, 'pickupGHS'=>1, 'legendTop'=>1, 'legendLeft'=>1];

class StedsMapper extends Mapper {

    function GetNextWalkData($dat)
    {
        $result = $this->db->query('SELECT * FROM walkday JOIN regions ON walkday.region = regions.regno where date >= "'.$dat.'" order by date limit 1')->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    function GetPastWalks()
    {
        $dat = date('Y-m-d');
        $sql = "SELECT date, area FROM walkday WHERE date < '$dat' ORDER BY date DESC LIMIT 25";
        $result = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($result as $i => $alb){
          $result[$i] = $alb['date'].' '.$alb['area'];
        }
        return $result;
    }
    function GetWalksByDateIndex()
    {
        $result = $this->db->query('SELECT date, area,details FROM walkday  order by substr(date,1,4) DESC, date ASC')->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    function GetWalksByRegionIndex()
    {
        $result = $this->db->query('SELECT date, area,regname,finish,details FROM walkday JOIN regions ON walkday.region = regions.regno order by regname,finish, date')->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
    function GetWalkRawData($dat){
        $results = array();
        $results['walkDetails'] = $this->db->query('SELECT * FROM walkday WHERE date = "'.$dat.'" ')->fetch(PDO::FETCH_ASSOC);
        $results['routes'] = $this->db->query('SELECT * FROM walks WHERE date = "'.$dat.'" ')->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }
    function GetWalkDetails($dat)
    {
        $results = array();
        $dets = $this->GetNextWalkData($dat);
        $dets['routes']=[];
        $dat = $dets['date'];
        $routes = $this->db->query('SELECT * FROM walks WHERE date = "'.$dat.'" ')->fetchAll(PDO::FETCH_ASSOC);
        $base = "walkdata/".substr($dat,0,4)."/$dat";
        //$dets['img'] = $this->FindImageFile("$base/map-$dat");
        $dets['img'] = "$base/map-$dat.pdf";
        foreach($routes as $i =>$rte)
        {
            unset($routes[$i]['date']);
            $routes[$i]['distance'] = round($routes[$i]['distance']/1000,1);
            $routes[$i]['mmdistance'] = round($routes[$i]['mmdistance']/1000,1);
            $routes[$i]['prfImg'] = $this->FindImageFile("$base/profile-$dat-walk-{$rte['no']}");
            list($routes[$i]['imgWd'], $routes[$i]['imgHt'], $routes[$i]['imgSize'])
              =[10, 10, 10];
                //  = $this->FindImageSize($routes[$i]['prfImg']);
        }
        $dets['routes']=$routes;
        $dets['gpx'] = ($dets['details'] === 'Y') ? $this->getRoutesGpxJ($dat) : [];
        return $dets;
        // return array('walkDetails'=>$dets, 'routes'=>$routes);
    }

    function getWalkRouteGpx($date, $no){
      $xFile = WALKDATA."walkdata/".substr($date,0,4)."/{$date}/data-{$date}-walk-{$no}.gpx";

      if (!file_exists($xFile)){

        $this->makeAllRoutesGpx($date);
      }
      $wd= $this->db->query('SELECT area FROM walkday WHERE date = "'.$date.'" ')->fetch(PDO::FETCH_ASSOC);
      $area = $wd['area'];
      $res = file_get_contents($xFile);
      // preg_match('/<name>(?<name>.*)<\/name>/', $res, $match);
      // $name=$match['name'];
      $name ="{$area} {$no} {$date}";
      return [$res, $name];
    }

    function walkdata($params, $response){
      $xFile = WALKDATA."walkdata/".$params;
      $data = file_get_contents($xFile);
      $mime = mime_content_type($xFile);
      $this->logger->info("Content-Type $xFile", [$mime]);
      $response = $response->withHeader('Content-type', $mime);
      $response->getBody()->write($data);
      return $response;
    }

    function makeAllRoutesGpx($date){
      $jFile = WALKDATA."walkdata/".substr($date,0,4)."/{$date}/data-{$date}-walk-gpx.json";
      $gxpJ = [];
      $wd= $this->db->query('SELECT area FROM walkday WHERE date = "'.$date.'" ')->fetch(PDO::FETCH_ASSOC);
      $area = $wd['area'];
      for ($no=1; $no < 6 ; $no++) {
        $gxpJ[$no] = $this->makeRouteGpx($date, $no, $area);
      }
      $gxpJ['area'] = $area;
      file_put_contents($jFile, json_encode($gxpJ));
      return $gxpJ;
    }
    function getRoutesGpxJ($date){
      $jFile = WALKDATA."walkdata/".substr($date,0,4)."/{$date}/data-{$date}-walk-gpx.json";
      if (file_exists($jFile))return json_decode(file_get_contents($jFile));
      else return $this->makeAllRoutesGpx($date);
    }
    function makeRouteGpx($date, $no, $area){
      $wFile = WALKDATA."walkdata/".substr($date,0,4)."/{$date}/data-{$date}-walk-{$no}.txt";
      if (!file_exists($wFile))return null;
      $xFile = WALKDATA."walkdata/".substr($date,0,4)."/{$date}/data-{$date}-walk-{$no}.gpx";
      $name = "{$area} {$no} {$date}";
      eval(file_get_contents($wFile));
      $res = '<?xml version="1.0" encoding="ISO-8859-1"?>';
      $res .= '<gpx version="1.1" creator="Memory-Map 5.4.2.1089 http://www.memory-map.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.topografix.com/GPX/1/1" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd http://www.topografix.com/GPX/gpx_overlay/0/3 http://www.topografix.com/GPX/gpx_overlay/0/3/gpx_overlay.xsd http://www.topografix.com/GPX/gpx_modified/0/1 http://www.topografix.com/GPX/gpx_modified/0/1/gpx_modified.xsd">';
      $res .= "<rte><name>{$name}</name>";
      $minLat = 999999; $maxLat=-999999;
      $minLng = 999999; $maxLng=-999999;
      $start = false;
      foreach ($wp as $i => $p)
      {
        $pt = OS_Coords::deLetterMapCoords($p['pos']);
        if (!$start)$start = $pt;
        $end = $pt;
        list($lat, $lng) = $pt;
        $minLat = min($minLat, $lat);
        $maxLat = max($maxLat, $lat);
        $minLng = min($minLng, $lng);
        $maxLng = max($maxLng, $lng);
        $pt = OS_Coords::mapOsToLatLong($p['pos']);
        list($lat, $lng) = $pt;

        $res .= '<rtept lat="'.$lat.'" lon="'.$lng.'"><name>'.$p['name'].'</name><sym>Dot</sym><type>Waypoints</type></rtept>';
      }
      $res .= '</rte></gpx>';
      file_put_contents($xFile, $res);
      $cent = [($minLat + $maxLat) / 2, ($minLng + $maxLng) / 2];
      return compact('minLat', 'maxLat', 'minLng', 'maxLng', 'start', 'end', 'cent');
    }
    // function UpdateRouteLeader($date, $no, $leader)
    // {
    //     $sql = "UPDATE walks SET leader = \"{$leader}\" ".
    //             "WHERE date = \"{$date}\" and no = \"{$no}\" ";
    //     kint::dump($sql);
    //     $this->db->exec($sql);
    // }
    function UpdateWalkDetails($date, $upt)
    {
        if (count($upt) == 0)return;
        $sql = "UPDATE walkday SET";
        foreach($upt as $nam=>$val){
          if ($nam === 'auth')continue;
          if ($nam === 'gpx')continue;
          if (!isset($intFields[$nam])) $val ='"'.$val.'"';
          $sql .= " $nam = {$val},";
        }
        $sql =  trim($sql, ',')." WHERE date = \"{$date}\" ";
        kint::dump($upt,$sql);
        $this->db->exec($sql);
    }
    function UpdateRoute($date, $no, $leader, $dist,$mdist, $asc, $dsc)
    {
        $sql = "UPDATE walks SET distance = \"{$dist}\", ".
                "mmdistance = \"{$mdist}\", ascent = \"{$asc}\", descent =\"{$dsc}\" ".
                "WHERE date = \"{$date}\" and no = \"{$no}\" ";
        kint::dump($sql);
        $this->db->exec($sql);
    }
    function UpdateRouteArray($date, $no, $upt)
    {
        if (count($upt) == 0)return;
        $sql = "UPDATE walks SET";
        foreach($upt as $nam=>$val)$sql .= " $nam=\"{$val}\",";
        $sql =  trim($sql, ',')." WHERE date = \"{$date}\" and no = \"{$no}\" ";
        kint::dump($sql);
        $this->db->exec($sql);
    }
    function InsertRouteArray($data)
    {
        if (count($data) == 0)return;
        $sql = "INSERT INTO walks (".join(',', array_keys($data)).") ".
                'VALUES ("'.join('","', array_values($data)).'") ';
        kint::dump($sql);
        $this->db->exec($sql);
    }
    function InsertRoute($date, $no, $leader, $dist,$mdist, $asc, $dsc)
    {
        $sql = "INSERT INTO walks (date,no,distance,mmdistance,ascent,descent) ".
                "VALUES (\"{$date}\", \"{$no}\", \"{$dist}\",\"{$mdist}\", \"{$asc}\", \"{$dsc}\") ";
        kint::dump($sql);
        $this->db->exec($sql);
    }
    function CreateYearsWalkDays($dat)
    {
      $yearEnd = substr($dat, 0, 4)."-12-20";
      $date = new DateTime($dat);
      $I14days = new DateInterval('P14D');
      $n = 0;
      while($dat < $yearEnd){
        $sql = 'INSERT INTO walkday (date) VALUES ("'.$dat.'") ';
        $this->db->exec($sql);
        echo "adding {$dat} \n";
        $date->add($I14days);
        $dat = $date->format('Y-m-d');
        $n++;
      }
      return [message=> "$n walk date created"];
    }
    function AddWalkDay($dat)
    {
      try {
        $this->logger->info("AddWalkDay $dat");
        if ($this->doesWalkDayExist($dat))throw new ErrorException( "$dat already exists as a walk day.");
        $sql = 'INSERT INTO walkday (date) VALUES ("'.$dat.'") ';
        $this->db->exec($sql);
        $sql = 'SELECT * FROM walkday WHERE date = "'.$dat.'" ';
        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

      } catch (Exception $e) {return $this->relayError($e); }

    }
    function doesWalkDayExist($dat){
      $sql = 'SELECT *, rowid FROM walkday WHERE date = "'.$dat.'" ';
      $rec = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
      if (!$rec)$this->logger->debug("doesWalkDayExist $rec");
      else $this->logger->debug("doesWalkDayExist", $rec);

          // var_dump($rec);
      return ($rec !== false);

    }
    function relayError(Exception $e){
      if ($e->getCode()===0) $txt = $e->getMessage();
      else $txt = $e->toString();
      return ['error'=>$txt];
    }
    function DelWalkDay($dat)
    {
      try{
        if (!$this->doesWalkDayExist($dat))throw new ErrorException( "$dat does not exists as a walk day.");
        $sql = 'DELETE FROM walkday WHERE date = "'.$dat.'" ';
        $this->db->exec($sql);
        // echo "deleting {$dat} \n";
        return ['message'=> "walk deleted for $dat"];
      } catch (Exception $e) {return $this->relayError($e); }
      // kint::dump($sql);
    }
    function GetMapData($dat)
    {
        $r = $this->GetNextWalkData($dat);
        $dat = $r['date'];
        $base = "walkdata/".substr($dat,0,4)."/$dat";
        $r['mapimg'] = $this->FindImageFile("$base/map-$dat");
        $r['mapimgR'] = $this->FindImageFile("$base/mapR-$dat");
        $r['heading'] = $this->FindImageFile("$base/heading-$dat");
        $r['headingR'] = $this->FindImageFile("$base/headingR-$dat");
        $r['overlay'] = $this->GetFile("$base/map-{$dat}.ovl");
        $r['headPos'] = ($r['headingR']!==false ? "Side" : "Top");
        $r['mapRot'] = ($r['mapimgR']!==false ? "Yes" : "No");
        return $r;
    }
    function GetFile($nam)
    {
        $rt = WALKDATA;
        if (file_exists($rt.$nam))return file_get_contents($rt.$nam);
        return false;
    }
    function FindImageSize($nam)
    {
        $size = getimagesize(WALKDATA.$nam, $info);
        Kohana::debug($size);
        return array($size[0], $size[1], $size[3]);
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
    function GetYearsData($yr)
    {
        $results = array();
        $results['walksDetails'] = $this->db->query('SELECT * FROM walkday JOIN regions ON walkday.region = regions.regno where substr(date,1,4) = "'.substr($yr,0,4).'" order by date')->fetchAll(PDO::FETCH_ASSOC);
        $now = date('Y-m-d');
        if (substr($now,0,4) == $yr) $yr = $now;
        $results['hiDate'] = $this->GetNextWalkData($yr);
        $prog = "walkdata/".substr($yr,0,4)."/StEdwardsWalksProgramme".substr($yr,0,4).".pdf";
        if (file_exists(WALKDATA.$prog))$results['docname'] = $prog;
        //$results['docname'] = $prog;
        return $results;
    }
    function programmePDF($Year){
      $walksProg = $this->GetYearsData($Year);
      require "./progPDF.php";
      return null;
    }
    function GetColumnNames($table)
    {
        return $this->db->exec("PRAGMA table_info($table)");
    }
     function GetYearsDataIndex($yr)
    {
        return $this->db->query('SELECT date, area FROM walkday where substr(date,1,4) = "'.$yr.'" order by date')->fetchAll(PDO::FETCH_ASSOC);
    }
   function GetYears()
    {
        $result = $this->db->query('SELECT DISTINCT substr(date,1,4) as year FROM walkday order by year DESC')->fetchAll(PDO::FETCH_COLUMN);
        return $result;
    }
   function GetRegions()
    {
        $result = $this->db->query('SELECT regno as no, regname as name FROM regions order by regname')->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
   function AddRegion($no, $name)
    {
      $sql = "INSERT INTO regions (regno, regname) VALUES ('$no', '$name') ";
      $this->db->exec($sql);

      return [];
    }
}
