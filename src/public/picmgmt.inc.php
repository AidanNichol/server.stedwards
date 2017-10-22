<?php
/*************************
  Coppermine Photo Gallery
  ************************
  Copyright (c) 2003-2011 Coppermine Dev Team
  v1.0 originally written by Gregory Demar

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License version 3
  as published by the Free Software Foundation.

  ********************************************
  Coppermine version: 1.5.12
  $HeadURL: https://coppermine.svn.sourceforge.net/svnroot/coppermine/trunk/cpg1.5.x/include/picmgmt.inc.php $
  $Revision: 8154 $
**********************************************/
// Add a picture to an album
function add_picture($arrgs)
{
    global $CONFIG, $USER_DATA, $PIC_NEED_APPROVAL, $CURRENT_PIC_DATA;
    global $lang_errors, $lang_db_input_php;
    extract($arrgs);
    $image = PICTURE_LOC . $filepath . $filename;
    $normal = PICTURE_LOC . $filepath . 'normal_'. $filename;
    $thumb = PICTURE_LOC . $filepath . 'thumb_' . $filename;
    // $orig = PICTURE_LOC . $filepath  . $filename;
    // $mini = $CONFIG['fullpath'] . $filepath . $CONFIG['mini_pfx'] . $filename;
    Kint::dump('add_picture', $db, $aid, $filepath, $filename, $position, $title , $caption , $keywords , $photographer, $user1 , $user2 , $user3 , $user4 , $category, $image, $normal, $thumb);
    $work_image = $image;
    $imagesize = getimagesize($image);


    // resize picture if it's bigger than the max width or height for uploaded pictures
    if (max($imagesize[0], $imagesize[1]) > 1280) {

            resize_image($image, $image, 1280, 'any');
            $imagesize = getimagesize($image);
    }


    if (!file_exists($thumb)) {
        // create thumbnail
        if (($result = resize_image($work_image, $thumb, 100, 'ht')) !== true) {
            return $result;
        }
    }

    if ($imagesize[0] > 400 && !file_exists($normal)) {
        // create intermediate sized picture
        if (($result = resize_image($work_image, $normal, 400, 'wd')) !== true) { return $result; }
    }
    if (preg_match("/(.+)\.(.*?)\Z/", $image, $matches)) {
      $picName = $matches[1] . '~60x60' . '.' . $matches[2];
      if (($result = resize_image($work_image, $picName, 60, 'ex')) !== true) { return $result; }

      $ht = round($imagesize[1] * (300 / $imagesize[0]));
      $picName = "{$matches[1]}~350x{$ht}.{$matches[2]}";
      if (($result = resize_image($work_image, $picName, 350, 'wd')) !== true) { return $result; }

      $ht = round($imagesize[1] * (800 / $imagesize[0]));
      $picName = "{$matches[1]}~800x{$ht}.{$matches[2]}";
      if (($result = resize_image($work_image, $picName, 800, 'wd')) !== true) { return $result; }

    }



    $image_filesize = filesize($image);
    $total_filesize = $image_filesize + (file_exists($normal) ? filesize($normal) : 0) + filesize($thumb);


    $PIC_NEED_APPROVAL = false;

    // User ID is recorded when in admin mode
    // $user_id  = USER_ID;

    // Populate Array to pass to plugins, then to SQL
    $data = ['aid' => $aid,
    'filepath' => addslashes($filepath),
    'filename' => addslashes($filename),
    'filesize' => $image_filesize,
    'total_filesize' => $total_filesize,
    'pwidth' => $imagesize[0],
    'pheight' => $imagesize[1],
    'mtime' => date('19y-m-d h:i:s'),
    'ctime' => time(date('19y-m-d h:i:s')),
    'owner_id' => 9,
    'title' => $title,
    'caption' => $caption,
    'keywords' => $keywords,
    'approved' => 'YES',
    'user1' => $user1,
    'user2' => $user2,
    'user3' => $user3,
    'user4' => $user4,
    'pic_raw_ip' => '',
    'pic_hdr_ip' => '',
    'position' => $position,
    'guest_token' => ''];
    $fields = '';
    $values = '';
    foreach ($data as $key => $value) {
      $fields .= ($fields == '' ? '' : ', ').$key;
      $values .= ($values == '' ? '' : ', ')."'{$value}'";
    }

    $query = "INSERT INTO cpg132_pictures ({$fields}) VALUES ({$values})";
    $result = $db->query($query);

    // Put the pid in current_pic_data and call the plugin filter for file data success
    $data['pid'] = $db->lastInsertId();


    //return $result;
    return ['normal' => $filepath . 'normal_'. $filename,
            'thumb' => $filepath . 'thumb_' . $filename ];
}

define("GIS_GIF", 1);
define("GIS_JPG", 2);
define("GIS_PNG", 3);



/**
* resize_image()
*
* Create a file containing a resized image
*
* @param  $src_file the source file
* @param  $dest_file the destination file
* @param  $new_size the size of the square within which the new image must fit
* @param  $method the method used for image resizing
* @return 'true' in case of success
*/
function resize_image($src_file, $dest_file, $new_size, $use)
{
    global $CONFIG, $ERROR;
    global $lang_errors;
    global $logger;
    $logger->debug("resize_image src:$src_file, dst:$dest_file, size:$new_size, use:$use");
    Kint::dump('resize_image', $src_file, $dest_file, $new_size, $use);


    $imginfo = getimagesize($src_file);
    if ($imginfo == null) {
        return false;
    }
    // GD can only handle JPG & PNG images
    if ($imginfo[2] != GIS_JPG ) {
        $ERROR = $lang_errors['gd_file_type_err'];
        //return false;
        return array('error' => $ERROR);
    }
    // height/width
    $srcWidth = $imginfo[0];
    $srcHeight = $imginfo[1];

    $crop = 0; // initialize
    if ($use == 'ex') {
        $thb_width = 60;
        $thb_height = 60;


        if ($new_size==$thb_width) {
            $crop = 1;
            // using GD2
            if($srcHeight < $srcWidth) {
                $ratio = (double)($srcHeight / $thb_height);
                $cpyWidth = round($thb_width * $ratio);
                if ($cpyWidth > $srcWidth) {
                    $ratio = (double)($srcWidth / $thb_width);
                    $cpyWidth = $srcWidth;
                    $cpyHeight = round($thb_height * $ratio);
                    $xOffset = 0;
                    $yOffset = round(($srcHeight - $cpyHeight) / 2);
                } else {
                    $cpyHeight = $srcHeight;
                    $xOffset = round(($srcWidth - $cpyWidth) / 2);
                    $yOffset = 0;
                }

            } else {
                $ratio = (double)($srcWidth / $thb_width);
                $cpyHeight = round($thb_height * $ratio);
                if ($cpyHeight > $srcHeight) {
                    $ratio = (double)($srcHeight / $thb_height);
                    $cpyHeight = $srcHeight;
                    $cpyWidth = round($thb_width * $ratio);
                    $xOffset = round(($srcWidth - $cpyWidth) / 2);
                    $yOffset = 0;
                } else {
                    $cpyWidth = $srcWidth;
                    $xOffset = 0;
                    $yOffset = round(($srcHeight - $cpyHeight) / 2);
                }
            }

            $destWidth = $thb_width;
            $destHeight = $thb_height;
            $srcWidth = $cpyWidth;
            $srcHeight = $cpyHeight;
        }
        // Kint::dump($dst_img, $src_img, 0, 0, $xOffset, $yOffset, $destWidth, $destHeight, $srcWidth, $srcHeight);
    } elseif ($use == 'wd') {
        // resize method width
        $ratio = $srcWidth / $new_size;
    } elseif ($use == 'ht') {
        // resize method height
        $ratio = $srcHeight / $new_size;
    } else { // resize method any
        $ratio = max($srcWidth, $srcHeight) / $new_size;
    }


    $ratio = max($ratio, 1.0);
    if ($crop != 1) {
        $destWidth = (int)($srcWidth / $ratio);
        $destHeight = (int)($srcHeight / $ratio);
        // $resize_commands = "-geometry ".$destWidth."x".$destHeight;
        $xOffset = 0;
        $yOffset = 0;
    }
    // using GD2
    $src_img = imagecreatefromjpeg($src_file);
    if (!$src_img) {
        $ERROR = $lang_errors['invalid_image'];
        //return false;
        return array('error' => $ERROR);
    }
    $dst_img = imagecreatetruecolor($destWidth, $destHeight);
    imagecopyresampled($dst_img, $src_img, 0, 0, $xOffset, $yOffset, (int)$destWidth, (int)$destHeight, $srcWidth, $srcHeight);
    touch($dest_file);
    $fh=fopen($dest_file,'w');
    fclose($fh);


    imagejpeg($dst_img, $dest_file, 80);
    imagedestroy($src_img);
    imagedestroy($dst_img);
    // Set mode of uploaded picture
    // $perms = substr(sprintf('%o', fileperms($dest_file)), -4);
    // chmod($dest_file, 0644); //silence the output in case chmod is disabled
    // clearstatcache();
    // $perms2 = substr(sprintf('%o', fileperms($dest_file)), -4);
    //
    // // We check that the image is valid
    // $logger->debug("ressize file $dest_file", [file_exists($dest_file), $perms, $perms2]);
    // Kint::dump($dest_file, file_exists($dest_file), $perms, $perms2);
    $imginfo = getimagesize($dest_file);
    if ($imginfo == null) {
        $ERROR = $lang_errors['resize_failed'];
        @unlink($dest_file);
        //return false;
        return array('error' => $ERROR);
    } else {
        return true;
    }
}

?>
