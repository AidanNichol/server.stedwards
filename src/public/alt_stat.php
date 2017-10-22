<?php

function alt_stat($file) {

clearstatcache();
$ss=@stat($file);
if(!$ss) return false; //Couldnt stat file

$ts=array(
  0140000=>'ssocket',
  0120000=>'llink',
  0100000=>'-file',
  0060000=>'bblock',
  0040000=>'ddir',
  0020000=>'cchar',
  0010000=>'pfifo'
);

$p=$ss['mode'];
$t=decoct($ss['mode'] & 0170000); // File Encoding Bit

$str =(array_key_exists(octdec($t),$ts))?$ts[octdec($t)]{0}:'u';
$str.=(($p&0x0100)?'r':'-').(($p&0x0080)?'w':'-');
$str.=(($p&0x0040)?(($p&0x0800)?'s':'x'):(($p&0x0800)?'S':'-'));
$str.=(($p&0x0020)?'r':'-').(($p&0x0010)?'w':'-');
$str.=(($p&0x0008)?(($p&0x0400)?'s':'x'):(($p&0x0400)?'S':'-'));
$str.=(($p&0x0004)?'r':'-').(($p&0x0002)?'w':'-');
$str.=(($p&0x0001)?(($p&0x0200)?'t':'x'):(($p&0x0200)?'T':'-'));

$s=array(
'perms'=>array(
  'umask'=>sprintf("%04o",@umask()),
  'human'=>$str,
  'octal1'=>sprintf("%o", ($ss['mode'] & 000777)),
  'octal2'=>sprintf("0%o", 0777 & $p),
  'decimal'=>sprintf("%04o", $p),
  'fileperms'=>@fileperms($file),
  'mode1'=>$p,
  'mode2'=>$ss['mode']),

'owner'=>array(
  'fileowner'=>$ss['uid'],
  'filegroup'=>$ss['gid'],
  'owner'=>
  (function_exists('posix_getpwuid'))?
  @posix_getpwuid($ss['uid']):'',
  'group'=>
  (function_exists('posix_getgrgid'))?
  @posix_getgrgid($ss['gid']):''
  ),

'file'=>array(
  'filename'=>$file,
  'realpath'=>(@realpath($file) != $file) ? @realpath($file) : '',
  'dirname'=>@dirname($file),
  'basename'=>@basename($file)
  ),

'filetype'=>array(
  'type'=>substr($ts[octdec($t)],1),
  'type_octal'=>sprintf("%07o", octdec($t)),
  'is_file'=>@is_file($file),
  'is_dir'=>@is_dir($file),
  'is_link'=>@is_link($file),
  'is_readable'=> @is_readable($file),
  'is_writable'=> @is_writable($file)
  ),

'device'=>array(
  'device'=>$ss['dev'], //Device
  'device_number'=>$ss['rdev'], //Device number, if device.
  'inode'=>$ss['ino'], //File serial number
  'link_count'=>$ss['nlink'], //link count
  'link_to'=>($s['type']=='link') ? @readlink($file) : ''
  ),

'size'=>array(
  'size'=>$ss['size'], //Size of file, in bytes.
  'blocks'=>$ss['blocks'], //Number 512-byte blocks allocated
  'block_size'=> $ss['blksize'] //Optimal block size for I/O.
  ),

'time'=>array(
  'mtime'=>$ss['mtime'], //Time of last modification
  'atime'=>$ss['atime'], //Time of last access.
  'ctime'=>$ss['ctime'], //Time of last status change
  'accessed'=>@date('Y M D H:i:s',$ss['atime']),
  'modified'=>@date('Y M D H:i:s',$ss['mtime']),
  'created'=>@date('Y M D H:i:s',$ss['ctime'])
  ),
);

clearstatcache();
return $s;
}

?>
