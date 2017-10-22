<?php
// define('CPGBASE', '/www/sites/steds-server/Coppermine/');
require "./picmgmt.inc.php";
require "./functions.inc.php";
function simple_die($level, $msg, $line, $file){
  global $logger;
  Kint::dump('simple_die', $level, $msg, $line, $file);
  $excp = "{$file}@{$line} {$level}: {$msg}";
  $logger->error($excp);
  throw new Exception($excp);
  // exit($msg);
};
$lang_db_input_php = array(
  'empty_name_or_com' => 'You need to type your name and a comment',
  'com_added' => 'Your comment was added',
  'alb_need_title' => 'You have to provide a title for the album !',
  'no_udp_needed' => 'No update needed.',
  'alb_updated' => 'The album was updated',
  'unknown_album' => 'Selected album does not exist or you don\'t have permission to upload in this album',
  'no_pic_uploaded' => 'No file was uploaded !<br /><br />If you have really selected a file to upload, check that the server allows file uploads...', //cpg1.3.0
  'err_mkdir' => 'Failed to create directory %s !',
  'dest_dir_ro' => 'Destination directory %s is not writable by the script !',
  'err_move' => 'Impossible to move %s to %s !',
  'err_fsize_too_large' => 'The size of file you have uploaded is too large (maximum allowed is %s x %s) !', //cpg1.3.0
  'err_imgsize_too_large' => 'The size of the file you have uploaded is too large (maximum allowed is %s KB) !',
  'err_invalid_img' => 'The file you have uploaded is not a valid image !',
  'allowed_img_types' => 'You can only upload %s images.',
  'err_insert_pic' => 'The file \'%s\' can\'t be inserted in the album ', //cpg1.3.0
  'upload_success' => 'Your file was uploaded successfully.<br /><br />It will be visible after admin approval.', //cpg1.3.0
  'notify_admin_email_subject' => '%s - Upload notification', //cpg1.3.0
  'notify_admin_email_body' => 'A picture has been uploaded by %s that needs your approval. Visit %s', //cpg1.3.0
  'info' => 'Information',
  'com_added' => 'Comment added',
  'alb_updated' => 'Album updated',
  'err_comment_empty' => 'Your comment is empty !',
  'err_invalid_fext' => 'Only files with the following extensions are accepted : <br /><br />%s.',
  'no_flood' => 'Sorry but you are already the author of the last comment posted for this file<br /><br />Edit the comment you have posted if you want to modify it', //cpg1.3.0
  'redirect_msg' => 'You are being redirected.<br /><br /><br />Click \'CONTINUE\' if the page does not refresh automatically',
  'upl_success' => 'Your file was successfully added', //cpg1.3.0
  'email_comment_subject' => 'Comment posted on Coppermine Photo Gallery', //cpg1.3.0
  'email_comment_body' => 'Someone has posted a comment on your gallery. See it at', //cpg1.3.0
);

// Create a new album where pictures will be uploaded

function getAlbumId($db, $albumName)
{
  $albumDate = substr($albumName, 0, 10);
  $alb = $db->query("SELECT aid, title FROM cpg132_albums WHERE substr( title, 1, 10 ) = '{$albumDate}'")->fetchAll(PDO::FETCH_ASSOC);
  Kint::dump($alb);
  // var_dump($alb);
  if (count($alb) !== 0)return $alb[0]['aid'];
  // Doesn't exist so creat it
  // $category = 19; //assume 2017 for now
  $query = "INSERT INTO cpg132_albums (category, title, uploads, pos) VALUES ('20', '{$albumName}', 'NO',  '0')";
  $db->query($query);
  $aid = $db->lastInsertId();
  return $aid;
}


// Add a picture

function process_picture( $db, $file, $name, $albumName, $photographer)
{
  global $lang_db_input_php;
  global $logger;
  $aid = getAlbumId($db, $albumName);
  $logger->info("Album Id:$aid");
  Kint::dump($aid);
    $title = '';
    $caption = '';
    $keywords = '';
    $user1 = $photographer;
    $user2 = '';
    $user3 = '';
    $user4 = '';
    $position = 0;
    // Check if the album id provided is valid
    $row = $db->query("SELECT category FROM cpg132_albums WHERE aid='$aid'")->fetch(PDO::FETCH_ASSOC);
    $category = $row['category'];
    $position = 100;


    $filepath = 'userpics/'.substr($albumName, 0, 4).'/'.substr($albumName, 0, 10);
    $dest_dir = PICTURE_LOC . $filepath;
    $logger->info("Destination Dir is: {$dest_dir}");
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0775, true);
        $fp = fopen($dest_dir . '/index.html', 'w');
        fwrite($fp, ' ');
        fclose($fp);
    }
    chmod($dest_dir, 0777);

    $dest_dir .= '/';
    $filepath .= '/';

    // Check that target dir is writable
    if (!is_writable($dest_dir)) simple_die(CRITICAL_ERROR, sprintf($lang_db_input_php['dest_dir_ro'], $dest_dir), __FILE__, __LINE__, true);

    $matches = array();
    // Replace forbidden chars with underscores
    $picture_name = $name;


    $picture_name = replace_forbidden($name);
    // Check that the file uploaded has a valid extension
    if (!preg_match("/(.+)\.(.*?)\Z/", $picture_name, $matches)) {
        $matches[1] = 'invalid_fname';
        $matches[2] = 'xxx';
    }
    // Create a unique name for the uploaded file
    $nr = 0;
    while (file_exists($dest_dir . $picture_name)) {
        $picture_name = $matches[1] . '~' . $nr++ . '.' . $matches[2];
    }

    $uploaded_pic = $dest_dir . $picture_name;


    // Check file size. Delete if it is excessive.
    if (filesize($file) > 6000000) {
        @unlink($file);
        simple_die(ERROR, sprintf($lang_db_input_php['err_imgsize_too_large'], $CONFIG['max_upl_size']), __FILE__, __LINE__);
    }
Kint::dump(filesize($file), getimagesize($file));

    // Get picture information
    $imginfo = getimagesize($file);

    // getimagesize does not recognize the file as a picture
    if ($imginfo == null) {
        @unlink($file);
        simple_die(ERROR, $lang_db_input_php['err_invalid_img'], __FILE__, __LINE__, true);
    }



    // JPEG and PNG only are allowed with GD

    if ($imginfo[2] != GIS_JPG ) {
        @unlink($file);
        simple_die(ERROR, $lang_errors['gd_file_type_err'], __FILE__, __LINE__, true);
    }

    // Check that picture size (in pixels) is lower than the maximum allowed
    $uploaded_pic = $dest_dir . $picture_name;
    if (max($imginfo[0], $imginfo[1]) > 1028) {
          resize_image($file, $uploaded_pic, 1028, 'any');
          @unlink($file);
    }
    else {

      // Move the picture into its final location
      Kint::dump('about to rename ', $file, $uploaded_pic, file_exists($file));
      if (!rename($file, $uploaded_pic))
          simple_die(CRITICAL_ERROR, sprintf($lang_db_input_php['err_move'], $picture_name, $dest_dir), __FILE__, __LINE__, true);
      // Change file permission
      chmod($uploaded_pic, 0644);
      Kint::dump('done rename ', $uploaded_pic, file_exists($uploaded_pic));

    }
    // Create thumbnail and internediate image and add the image into the DB
    $filename = $picture_name;
    $arrgs = compact('db', 'aid', 'filepath', 'picture_name', 'position', 'title', 'caption', 'keywords', 'user1', 'user2', 'user3', 'user4', 'category', 'photographer', 'filename');

    $result = add_picture($arrgs);

    if (isset($result['error'])) {
        @unlink($uploaded_pic);
        simple_die(CRITICAL_ERROR, sprintf($lang_db_input_php['err_insert_pic'], $uploaded_pic) . '<br /><br />' . $ERROR, __FILE__, __LINE__, true);
    } else {
        // echo ("SUCCESS");
        return $result['thumb'];
    }



}





?>
