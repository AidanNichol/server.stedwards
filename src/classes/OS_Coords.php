<?php
require __DIR__ . '/../vendor/autoload.php';
require '../vendor/php-coord/php-coord/OSRef.php';
require '../vendor/php-coord/php-coord/LatLng.php';
require '../vendor/php-coord/php-coord/UTMRef.php';
use PHPCoord\OSRef as OSRef;
// require "../packages/phpcoord-2.3/phpcoord-2.3.php";
// echo "\n";
// $pt0 = OS_Coords::deLetterMapCoords('SD 52406 99645');
// $pt1 =  getOSRefFromSixFigureReference('SD524996');
//   $LatLng1 = $pt1->toLatLng();
//   $OSRef = new OSRef($pt0[0], $pt0[1]); //Easting, Northing
//   $LatLng2 = $OSRef->toLatLng();
//   $LatLng3 = $LatLng2->OSGB36ToWGS84();
// $pt = OS_Coords::mapOsToLatLong('SD 52406 99645');
// // $LatLng2 = $pt->toLatLng();
// echo "SD 52406 99645 ===================\n";
// var_dump( $pt0, $pt1, $pt, $LatLng1, $LatLng2, $LatLng3);
//
// $LatLng = new LatLng(54.3900310288, -2.7344360338);
// var_dump('distance', $LatLng->distance($LatLng2));
// $LatLng->WGS84ToOSGB36();
// $pt4 = $LatLng->toOSRef();
//
// // This is the process of translating OS Coordinates like "264046", "192487", which as a GridRef looks like SN26401924 to their Lat/Long equivalents : 051°50′40″N, 004°31′13″W or, in decimal 51.61430, -3.96382
// echo "54.3900310288, -2.7344360338 =======================\n";
// var_dump( $pt4, $pt4->toSixFigureString());


class OS_Coords
{
    public static $gridLetters = array(
                         "SH" => array(2,3),
                         "SJ" => array(3,3),
                         "SK" => array(4,3),
                         "TF" => array(5,3),
                         "TG" => array(6,3),
                         "SD" => array(3,4),
                         "SE" => array(4,4),
                         "TA" => array(5,4),
                         "NW" => array(1,5),
                         "NX" => array(2,5),
                         "NY" => array(3,5),
                         "NZ" => array(4,5),
                         "NR" => array(1,6),
                         "NS" => array(2,6),
                         "NT" => array(3,6),
                         "NU" => array(4,6)
                         );

    static function getMapCoords($gridpos, $minX, $maxY)
    {
        $pos = explode(" ", $gridpos);
        $x = self::$gridLetters[$pos[0]][0].$pos[1];
        $y = self::$gridLetters[$pos[0]][1].$pos[2];
        $x = ($x - $minX)/100;
        $y = ($maxY - $y)/100;
        return array("x"=>$x, "y"=>$y);
    }
    static function deLetterMapCoords($gridpos)
    {
        $pos = explode(" ", $gridpos);
        $x = self::$gridLetters[$pos[0]][0].$pos[1];
        $y = self::$gridLetters[$pos[0]][1].$pos[2];
        return array($x, $y);
    }
    static function getGridLetters($x1, $y1)
    {
        $x = intval($x1/100000);
        $y = intval($y1/100000);
        //print "x1:$x1 y1:$y1 x:$x y:$y \n";
        foreach(self::$gridLetters as $let => $pt)
        {
        //print "$x $y $let ".$pt[0]." ".$pt[1]."<br/>\n";
            if ($x == $pt[0] && $y == $pt[1])
                return $let;
         }
         return "??";
    }
    static function mapLatLongToOs ($lat, $lon){
      $LatLng = new LatLng($lat, $lon);
      $LatLng->WGS84ToOSGB36();
      $OSRef = $LatLng->toOSRef();

      $easting = $OSRef->eastings;
      $northing = $OSRef->northing;
      return [$easting, $northing];
    }
    static function mapOsToLatLong ($gridpos){
      $pos = explode(" ", $gridpos);
      $x = self::$gridLetters[$pos[0]][0].$pos[1];
      $y = self::$gridLetters[$pos[0]][1].$pos[2];
      $OSRef = new OSRef($x, $y); //Easting, Northing
      $LatLng = $OSRef->toLatLng();
      $LatLng->toWGS84(); //optional, for GPS compatibility

      $lat =  $LatLng->getLat();
      $long = $LatLng->getLng();

      return [$lat, $long];
    }
    static function mapOs6ToLatLong ($gridpos){
      $pos = explode(" ", $gridpos);
      $OSRef = getOSRefFromSixFigureReference($pos[0].substr($pos[1], 0, 3), substr($pos[2], 0,3));
      $LatLng = $OSRef->toLatLng();
      $LatLng->toWGS84(); //optional, for GPS compatibility

      $lat =  $LatLng->getLat();
      $long = $LatLng->getLng();

      return [$lat, $long];
    }
}
