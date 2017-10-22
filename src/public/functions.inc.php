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
  $HeadURL: https://coppermine.svn.sourceforge.net/svnroot/coppermine/trunk/cpg1.5.x/include/functions.inc.php $
  $Revision: 8154 $
**********************************************/



// Perform a database query

/**
 * cpg_db_query()
 *
 * Perform a database query
 *
 * @param $query
 * @param integer $link_id
 * @return
 **/

// function cpg_db_query($query, $use_link_id = 0)
// {
//     global $CONFIG, $query_stats, $queries;
//
//     if ($use_link_id) {
//         $link_id = $use_link_id;
//     } else {
//         $link_id = $CONFIG['LINK_ID'];
//     }
//
//     $query_start = cpgGetMicroTime();
//
//     $result = mysql_query($query, $link_id);
//
//     $query_end = cpgGetMicroTime();
//
//     if (!isset($CONFIG['debug_mode']) || $CONFIG['debug_mode'] == 1 || $CONFIG['debug_mode'] == 2) {
//         $trace = debug_backtrace();
//         $last = $trace[0];
//         $localfile = str_replace(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR , '', $last['file']);
//
//         $duration      = round(($query_end - $query_start) * 1000);
//         $query_stats[] = $duration;
//         $queries[]     = "$query [$localfile:{$last['line']}] ({$duration} ms)";
//     }
//
//     if (!$result && !defined('UPDATE_PHP')) {
//         $trace = debug_backtrace();
//         $last = $trace[0];
//         $localfile = str_replace(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR , '', $last['file']);
//         cpg_db_error("While executing query '$query' in $localfile on line {$last['line']}", $link_id);
//     }
//
//     return $result;
// }

// Error message if a query failed


/**
 * cpg_db_error()
 *
 * Error message if a query failed
 *
 * @param $the_error
 * @return
 **/

function cpg_db_error($the_error, $link_id)
{
    global $CONFIG, $lang_errors, $LINEBREAK;

    log_write("$the_error the following error was encountered: $LINEBREAK" . mysql_error($link_id), CPG_DATABASE_LOG);

    if ($CONFIG['debug_mode'] === '0' || ($CONFIG['debug_mode'] === '2' && !GALLERY_ADMIN_MODE)) {
        cpg_die(CRITICAL_ERROR, $lang_errors['database_query'], __FILE__, __LINE__);
    } else {
        $the_error .= $LINEBREAK . $LINEBREAK . 'mySQL error: ' . mysql_error($link_id) . $LINEBREAK;
        $out        = "<br />" . $lang_errors['database_query'] . ".<br /><br/>
                <form name=\"mysql\" id=\"mysql\"><textarea rows=\"8\" cols=\"60\">" . htmlspecialchars($the_error) . "</textarea></form>";
        cpg_die(CRITICAL_ERROR, $out, __FILE__, __LINE__);
    }
}

// // Fetch all rows in an array
//
// /**
//  * cpg_db_fetch_rowset()
//  *
//  * Fetch all rows in an array
//  *
//  * @param $result
//  * @return
//  **/
//
// function cpg_db_fetch_rowset($result)
// {
//     $rowset = array();
//
//     while ( ($row = mysql_fetch_assoc($result)) ) {
//         $rowset[] = $row;
//     }
//
//     return $rowset;
// }
//
// /**
//  * cpg_db_fetch_row()
//  *
//  * Fetch row in an array
//  *
//  * @param $result
//  * @return
//  **/
//
// function cpg_db_fetch_row($result)
// {
//     return mysql_fetch_assoc($result);
// }
//
// /**
//  * cpg_db_last_insert_id()
//  *
//  * Get the last inserted id of a query
//  *
//  * @return integer $id
//  **/
//
// function cpg_db_last_insert_id()
// {
//     global $CONFIG;
//
//     return mysql_insert_id($CONFIG['LINK_ID']);
// }

/**************************************************************************
   Sanitization functions
 **************************************************************************/

/**
 * cpgSanitizeUserTextInput()
 *
 * Function to sanitize the data which cannot be directly sanitized with Inspekt
 *
 * @param string $string
 * @return string Return sanitized data
 */
function cpgSanitizeUserTextInput($string)
{
    //TODO: Add some sanitization code
    return $string;
}

/**************************************************************************
   Utilities functions
 **************************************************************************/

// Replacement for the die function

/**
 * cpg_die()
 *
 * Replacement for the die function
 *
 * @param $msg_code
 * @param $msg_text
 * @param $error_file
 * @param $error_line
 * @param boolean $output_buffer
 * @return
 **/

function cpg_die($msg_code, $msg_text,  $error_file, $error_line, $output_buffer = false)
{
    global $lang_common, $lang_errors, $CONFIG, $USER_DATA, $hdr_ip;

    // Three types of error levels: INFORMATION, ERROR, CRITICAL_ERROR.
    // There used to be a clumsy method for error mesages that didn't work well with i18n.
    // Let's add some more logic to this: try to get the translation
    // for the error type from the language file. If that fails, use the hard-coded
    // English string.

    // Record access denied messages to the log
    if ($msg_text == $lang_errors['access_denied'] && $CONFIG['log_mode'] != 0) {
        log_write("Denied privileged access to " . basename($error_file) . " by user {$USER_DATA['user_name']} at IP $hdr_ip", CPG_SECURITY_LOG);
    }

    // Record invalid form token messages to the log
    if ($msg_text == $lang_errors['invalid_form_token'] && $CONFIG['log_mode'] != 0) {
        log_write("Invalid form token encountered for " . basename($error_file) . " by user {$USER_DATA['user_name']} at IP $hdr_ip", CPG_SECURITY_LOG);
    }

    if ($msg_code == INFORMATION) {
        //$msg_icon = 'info'; not used anymore?
        $css_class = 'cpg_message_info';
        if ($lang_common['information'] != '') {
            $msg_string = $lang_common['information'];
        } else {
            $msg_string = 'Information';
        }
    } elseif ($msg_code == ERROR) {
        //$msg_icon = 'warning'; not used anymore?
        $css_class = 'cpg_message_warning';
        if ($lang_errors['error'] != '') {
            $msg_string = $lang_errors['error'];
        } else {
            $msg_string = 'Error';
        }
    } elseif ($msg_code == CRITICAL_ERROR) {
        //$msg_icon = 'stop'; not used anymore?
        $css_class = 'cpg_message_error';
        if ($lang_errors['critical_error'] != '') {
            $msg_string = $lang_errors['critical_error'];
        } else {
            $msg_string = 'Critical error';
        }
    }

    // Simple output if theme file is not loaded
    if (!function_exists('pageheader')) {
        echo 'Fatal error :<br />'.$msg_text;
        exit;
    }

    $ob = ob_get_contents();

    if ($ob) {
        ob_end_clean();
    }

    theme_cpg_die($msg_code, $msg_text, $msg_string, $css_class, $error_file, $error_line, $output_buffer, $ob);
    exit;
}


// Function to create correct URLs for image name with space or exotic characters

/**
 * path2url()
 *
 * Function to create correct URLs for image name with space or exotic characters
 *
 * @param $path
 * @return
 **/

function path2url($path)
{
    return str_replace("%2F", "/", rawurlencode($path));
}




// Compute image geometry based on max width / height

/**
 * compute_img_size()
 *
 * Compute image geometry based on max, width / height
 *
 * @param integer $width
 * @param integer $height
 * @param integer $max
 * @return array
 **/
function compute_img_size($width, $height, $max, $system_icon = false, $normal = false)
{
    global $CONFIG;

    $thumb_use = $CONFIG['thumb_use'];

    if ($thumb_use == 'ht') {
        $ratio = $height / $max;
    } elseif ($thumb_use == 'wd') {
        $ratio = $width / $max;
    } else {
        $ratio = max($width, $height) / $max;
    }

    if ($ratio > 1) {
        $image_size['reduced'] = true;
    }

    $ratio = max($ratio, 1);

    $image_size['width']  =  (int) ($width / $ratio);
    $image_size['height'] = (int) ($height / $ratio);
    $image_size['whole']  = 'width="' . $image_size['width'] . '" height="' . $image_size['height'] . '"';

    if ($thumb_use == 'ht') {
        $image_size['geom'] = ' height="' . $image_size['height'] . '"';
    } elseif ($thumb_use == 'wd') {
        $image_size['geom'] = 'width="' . $image_size['width'] . '"';

        //thumb cropping
    } elseif ($thumb_use == 'ex') {

        if ($normal == 'normal') {
            $image_size['geom'] = 'width="' . $image_size['width'] . '" height="' . $image_size['height'] . '"';
        } elseif ($normal == 'cat_thumb') {
            $image_size['geom'] = 'width="' . $max . '" height="' . ($CONFIG['thumb_height'] * $max / $CONFIG['thumb_width']) . '"';
        } else {
            $image_size['geom'] = 'width="' . $CONFIG['thumb_width'] . '" height="' . $CONFIG['thumb_height'] . '"';
        }
        //if we have a system icon we override the previous calculation and take 'any' as base for the calc
        if ($system_icon) {
            $image_size['geom'] = 'width="' . $image_size['width'] . '" height="' . $image_size['height'] . '"';
        }

    } else {
        $image_size['geom'] = 'width="' . $image_size['width'] . '" height="' . $image_size['height'] . '"';
    }

    return $image_size;
} // function compute_img_size


function utf_strtolower($str)
{
    if (!function_exists('mb_strtolower')) {
        require 'include/mb.inc.php';
    }
    return mb_strtolower($str);
} // function utf_strtolower

function utf_substr($str, $start, $end = null)
{
    if (!function_exists('mb_substr')) {
        require 'include/mb.inc.php';
    }
    return mb_substr($str, $start, $end);
} // function utf_substr

function utf_strlen($str)
{
    if (!function_exists('mb_strlen')) {
        require 'include/mb.inc.php';
    }
    return mb_strlen($str);
} // function utf_strlen

function utf_ucfirst($str)
{
    if (!function_exists('mb_strtoupper')) {
        require 'include/mb.inc.php';
    }
    return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
} // function utf_ucfirst


/*
  This function replaces special UTF characters to their ANSI equivelant for
  correct processing by MySQL, keywords, search, etc. since a bug has been
  found:  http://coppermine-gallery.net/forum/index.php?topic=17366.0
*/
function utf_replace($str)
{
    return preg_replace('#[\xC2][\xA0]|[\xE3][\x80][\x80]#', ' ', $str);
} // function utf_replace

function replace_forbidden($str)
{
    static $forbidden_chars;
    if (!is_array($forbidden_chars)) {
        global $CONFIG, $mb_utf8_regex;
        if (function_exists('html_entity_decode')) {
            $chars = html_entity_decode($CONFIG['forbiden_fname_char'], ENT_QUOTES, 'UTF-8');
        } else {
            $chars = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;', '&nbsp;', '&#39;'), array('&', '"', '<', '>', ' ', "'"), $CONFIG['forbiden_fname_char']);
        }
        preg_match_all("#$mb_utf8_regex".'|[\x00-\x7F]#', $chars, $forbidden_chars);
    }
    /**
     * $str may also come from $_POST, in this case, all &, ", etc will get replaced with entities.
     * Replace them back to normal chars so that the str_replace below can work.
     */
    $str = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $str);

    $return = str_replace($forbidden_chars[0], '_', $str);

    $condition = array (
        'transliteration' => true,
        'special_chars' => true
    );
    // $condition = CPGPluginAPI::filter('replace_forbidden_conditions', $condition);
    //
    // /**
    //  * Transliteration
    //  */
    // if ($condition['transliteration']) {
    //     require_once('include/transliteration.inc.php');
    //     $return = transliteration_process($return, '_');
    // }

    /**
     * Replace special chars
     */
    if ($condition['special_chars']) {
        $return = str_replace('%', '', rawurlencode($return));
    }

    /**
     * Fix the obscure, misdocumented "feature" in Apache that causes the server
     * to process the last "valid" extension in the filename (rar exploit): replace all
     * dots in the filename except the last one with an underscore.
     */
    // This could be concatenated into a more efficient string later, keeping it in three
    // lines for better readability for now.
    $extension = ltrim(substr($return, strrpos($return, '.')), '.');

    $filenameWithoutExtension = str_replace('.' . $extension, '', $return);

    $return = str_replace('.', '_', $filenameWithoutExtension) . '.' . $extension;

    return $return;
} // function replace_forbidden
/**
 * function cpg_getimagesize()
 *
 * Try to get the size of an image, this is custom built as some webhosts disable this function or do weird things with it
 *
 * @param string $image
 * @param boolean $force_cpg_function
 * @return array $size
 */
function cpg_getimagesize($image, $force_cpg_function = false)
{
    return getimagesize($image);

} // function cpg_getimagesize


function array_is_associative($array)
{
    if (is_array($array) && ! empty($array)) {
        for ($iterator = count($array) - 1; $iterator; $iterator--) {
            if (!array_key_exists($iterator, $array)) {
                return true;
            }
        }
        return !array_key_exists(0, $array);
    }
    return false;
}


function cpg_get_type($filename,$filter=null)
{
    global $CONFIG;

    static $FILE_TYPES = array();

    if (!$FILE_TYPES) {

        // Map content types to corresponding user parameters
        $content_types_to_vars = array(
            'image'    => 'allowed_img_types',
            'audio'    => 'allowed_snd_types',
            'movie'    => 'allowed_mov_types',
            'document' => 'allowed_doc_types',
        );

        $result = cpg_db_query('SELECT extension, mime, content, player FROM ' . $CONFIG['TABLE_FILETYPES']);

        $CONFIG['allowed_file_extensions'] = '';

        while ( ($row = mysql_fetch_assoc($result)) ) {
            // Only add types that are in both the database and user defined parameter
            if ($CONFIG[$content_types_to_vars[$row['content']]] == 'ALL' || is_int(strpos('/' . $CONFIG[$content_types_to_vars[$row['content']]] . '/', '/' . $row['extension'] . '/'))) {
                $FILE_TYPES[$row['extension']]      = $row;
                $CONFIG['allowed_file_extensions'] .= '/' . $row['extension'];
            }
        }

        $CONFIG['allowed_file_extensions'] = substr($CONFIG['allowed_file_extensions'], 1);

        mysql_free_result($result);
    }

    if (!is_array($filename)) {
        $filename = explode('.', $filename);
    }

    $EOA            = count($filename) - 1;
    $filename[$EOA] = strtolower($filename[$EOA]);

    if (!is_null($filter) && array_key_exists($filename[$EOA], $FILE_TYPES) && ($FILE_TYPES[$filename[$EOA]]['content'] == $filter)) {
        return $FILE_TYPES[$filename[$EOA]];
    } elseif (is_null($filter) && array_key_exists($filename[$EOA], $FILE_TYPES)) {
        return $FILE_TYPES[$filename[$EOA]];
    } else {
        return null;
    }
}

function is_image(&$file)
{
    return cpg_get_type($file, 'image');
}

function is_movie(&$file)
{
    return cpg_get_type($file, 'movie');
}

function is_audio(&$file)
{
    return cpg_get_type($file, 'audio');
}

function is_document(&$file)
{
    return cpg_get_type($file, 'document');
}

function is_flash(&$file)
{
    return pathinfo($file, PATHINFO_EXTENSION) == 'swf';
}

function is_known_filetype($file)
{
    return is_image($file) || is_movie($file) || is_audio($file) || is_document($file);
}




?>
