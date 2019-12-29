<?php


// Original work copyright (C) 2008-2010, 2013 Jos� Manuel Ferrer Ortiz
// Fixes and completion copyright (C) Uto (2015-2019)



// Header of the DDB files

// POSITION  LENGTH    CONTAINS    
// 0         1 byte    Seems to contain DAAD version number (1 for Aventura Original and Jabato, 1989, 2 for the rest)
// 1         1 byte    High nibble: target machine | Low nibble: target language
// 2         1 byte    Always contains 95, not identified
// 3         1 byte    Number of object descriptions
// 4         1 byte    Number of location descriptions
// 5         1 byte    Number of user messages
// 6         1 byte    Number of system messages
// 7         1 byte    Number of processes
// 8         2 bytes   Compressed text position
// 10        2 bytes   Process list position
// 12        2 bytes   Objects lookup list position
// 14        2 bytes   Locations lookup lits position
// 16        2 bytes   User messages lookup lits position
// 18        2 bytes   System messages lookup lits position
// 20        2 bytes   Connections lookup lits position
// 22        2 bytes   Vocabulary  
// 24        2 bytes   Objects "initialy at" list position
// 26        2 bytes   Object names positions
// 28        2 bytes   Object weight and container/wearable attributes
// 30        2 bytes   Extra object attributes 
// 32        2 bytes   File length 

// Note: some games lack of support for extra attributes so file length is at offset 30


/*

TIPS FOR PC DDBs:

ORIGINAL & JABATO header word values are little endian
COZUMEL, ESPACIAL, TEMPLOS & CHICHEN ITZA big endian

ORIGINAL, JABATO, COZUMEL use an old version that doesn't support extra object attributes
ESPACIAL, TEMPLOS & CHICHEN ITZA support them

New games made after DAAD was found, are supposed to be big endian and support
extra attributes, at least if they are compiled without any specific parameters.

*/


//####################################### AUX FUNCTIONS #########################################

function fgetb($handler)
{
  return ord(fgetc($handler));
}

function usageInfo()
{
 echo( "Usage: undaad.php <ddb_file> [options]\n");
 echo( "\n");
 echo( "Options:\n");
 echo( "-h -H : show only header data\n");
 echo( "-D -d : export to DSF\n");
 echo( "-o n -O n : read the DDB file from offset n\n");
 echo( "-v -V : verbose output\n");
}

function printSeparator($output)
{
 write($output, ";------------------------------------------------------------------------------\n");
}

//######################################## GLOBAL VARS ##########################################

// Handle big endian/little endian games
$SHR1=0;
$SHR2=8;



// Command line parameter settings
$headerOnly=false;
$exportToDSF=false;
$verboseOutput=false;


//Other vars
$isOldGame = false;
$isLittleEndian = false;

// Things to beter save in memory
$tokens=array();
$words=array();

$languages = array(
  0 => "English",
  1 => "Spanish"
);

// Vocabulary word types
$wordTypes = array("verb", "adverb", "noun", "adjective", "preposition",
                         "conjugation", "pronoun");

$wordParamTypes = array(
//      array(param.no => wordType)
  "36"=>array(0 => 0, 1 => 2), // SYNONYM
  "17"=>array(0 => 1), // ADVERB
  "69"=>array(0 => 2), // NOUN2
  "16"=>array(0 => 3), // ADJECT1
  "70"=>array(0 => 3), // ADJECT2
  "68"=>array(0 => 4)  // PREP
);

$specialLocs = array(
  252 => 'NOT_CREATED',
  253 => 'WORN',
  254 => 'CARRIED',
  255 => 'HERE'
);

$condacts = array(
array(1,'AT     '), //   0 $00
array(1,'NOTAT  '), //   1 $01
array(1,'ATGT   '), //   2 $$02
array(1,'ATLT   '), //   3 $03
array(1,'PRESENT'), //   4 $04
array(1,'ABSENT '), //   5 $05
array(1,'WORN   '), //   6 $06
array(1,'NOTWORN'), //   7 $07
array(1,'CARRIED'), //   8 $08
array(1,'NOTCARR'), //   9 $09
array(1,'CHANCE '), //  10 $0A
array(1,'ZERO   '), //  11 $0B
array(1,'NOTZERO'), //  12 $0C
array(2,'EQ     '), //  13 $0D
array(2,'GT     '), //  14 $0E
array(2,'LT     '), //  15 $0F
array(1,'ADJECT1'), //  16 $10
array(1,'ADVERB '), //  17 $11
array(2,'SFX    '), //  18 $12
array(1,'DESC   '), //  19 $13
array(0,'QUIT   '), //  20 $14
array(0,'END    '), //  21 $15
array(0,'DONE   '), //  22 $16
array(0,'OK     '), //  23 $17
array(0,'ANYKEY '), //  24 $18
array(1,'SAVE   '), //  25 $19
array(1,'LOAD   '), //  26 $1A
array(1,'DPRINT '), //  27 * $1B
array(1,'DISPLAY'), //  28 * $1C
array(0,'CLS    '), //  29 $1D
array(0,'DROPALL'), //  30 $1E
array(0,'AUTOG  '), //  31 $1F
array(0,'AUTOD  '), //  32 $20
array(0,'AUTOW  '), //  33 $21
array(0,'AUTOR  '), //  34 $22
array(1,'PAUSE  '), //  35 $23
array(2,'SYNONYM'), //  36 * $24
array(1,'GOTO   '), //  37 $25
array(1,'MESSAGE'), //  38 $26
array(1,'REMOVE '), //  39 $27
array(1,'GET    '), //  40 $28
array(1,'DROP   '), //  41 $29
array(1,'WEAR   '), //  42 $2A
array(1,'DESTROY'), //  43 $2B
array(1,'CREATE '), //  44 $2C
array(2,'SWAP   '), //  45 $2D
array(2,'PLACE  '), //  46 $2E
array(1,'SET    '), //  47 $2F
array(1,'CLEAR  '), //  48 $30
array(2,'PLUS   '), //  49 $31
array(2,'MINUS  '), //  50 $32
array(2,'LET    '), //  51 $33
array(0,'NEWLINE'), //  52 $34
array(1,'PRINT  '), //  53 $35
array(1,'SYSMESS'), //  54 $36
array(2,'ISAT   '), //  55 $37
array(1,'SETCO  '), //  56 $38 COPYOF in old games 
array(0,'SPACE  '), //  57 $39 COPYOO in old games
array(1,'HASAT  '), //  58 $3A COPYFO in old games
array(1,'HASNAT '), //  59 $3B COPYFF in old games
array(0,'LISTOBJ'), //  60 $3C
array(2,'EXTERN '), //  61 $3D
array(0,'RAMSAVE'), //  62 $3E
array(1,'RAMLOAD'), //  63 $3F
array(2,'BEEP   '), //  64 $40
array(1,'PAPER  '), //  65 $41
array(1,'INK    '), //  66 $42
array(1,'BORDER '), //  67 $43
array(1,'PREP   '), //  68 $44
array(1,'NOUN2  '), //  69 $45
array(1,'ADJECT2'), //  70 $46
array(2,'ADD    '), //  71 $47
array(2,'SUB    '), //  72 $48
array(1,'PARSE  '), //  73 $49
array(1,'LISTAT '), //  74 $4A
array(1,'PROCESS'), //  75 $4B
array(2,'SAME   '), //  76 $4C
array(1,'MES    '), //  77 $4D
array(1,'WINDOW '), //  78 $4E
array(2,'NOTEQ  '), //  79 $4F
array(2,'NOTSAME'), //  80 $50
array(1,'MODE   '), //  81 $51
array(2,'WINAT  '), //  82 $52
array(2,'TIME   '), //  83 $53
array(1,'PICTURE'), //  84 $54
array(1,'DOALL  '), //  85 $55
array(1,'MOUSE  '), //  86 $56
array(2,'GFX    '), //  87 $57
array(2,'ISNOTAT'), //  88 $58
array(2,'WEIGH  '), //  89 $59
array(2,'PUTIN  '), //  90 $5A
array(2,'TAKEOUT'), //  91 $5B
array(0,'NEWTEXT'), //  92 $5C
array(2,'ABILITY'), //  93 $5D
array(1,'WEIGHT '), //  94 $5E
array(1,'RANDOM '), //  95 $5F
array(2,'INPUT  '), //  96 $60
array(0,'SAVEAT '), //  97 $61
array(0,'BACKAT '), //  98 $62
array(2,'PRINTAT'), //  99 $63
array(0,'WHATO  '), // 100 $64
array(1,'CALL   '), // 101 $65
array(1,'PUTO   '), // 102 $66
array(0,'NOTDONE'), // 103 $67
array(1,'AUTOP  '), // 104 $68
array(1,'AUTOT  '), // 105 $69
array(1,'MOVE   '), // 106 $6A
array(2,'WINSIZE'), // 107 $6B
array(0,'REDO   '), // 108 $6C
array(0,'CENTRE '), // 109 $6D
array(1,'EXIT   '), // 110 $6E
array(0,'INKEY  '), // 111 $6F
array(2,'BIGGER '), // 112 $70
array(2,'SMALLER'), // 113 $71
array(0,'ISDONE '), // 114 $72
array(0,'ISNDONE'), // 115 $73
array(1,'SKIP   '), // 116 $74
array(0,'RESTART'), // 117 $75
array(1,'TAB    '), // 118 $76
array(2,'COPYOF '), // 119 $77
array(0,'dumb   '), // 120 $78 (according DAAD manual, internal)
array(2,'COPYOO '), // 121 $79 
array(0,'dumb   '), // 122 $7A (according DAAD manual, internal)
array(2,'COPYFO '), // 123 $7B
array(0,'dumb   '), // 124 $7C (according DAAD manual, internal)
array(2,'COPYFF '), // 125 $7D
array(2,'COPYBF '), // 126 $7E
array(0,'RESET  ')  // 127 $7F
);

// Global functions

function prettyFormat($value)
{
    $value = strtoupper(dechex($value));
    $value = str_pad($value,4,"0",STR_PAD_LEFT);
    $value = "0x$value";
    return $value;
}

function isLittleEndianPlatform($machineID)
{
    $target = getTargetByMachineID($machineID);
    return (($target=='ST') || ($target=='AMIGA'));
};


function replace_extension($filename, $new_extension) {
  $info = pathinfo($filename);
  return ($info['dirname'] ? $info['dirname'] . DIRECTORY_SEPARATOR : '') 
      . $info['filename'] 
      . '.' 
      . $new_extension;
}


function getMSX2Subtarget($nullword)
{
  $charWidth = ($nullword & 128) == 0 ? '6x8' : '8x8';
  $mode = ($nullword & 3) +5;
  return "Mode $mode, $charWidth characters.";
}

function write($handle, $text)
{
  fputs($handle, $text);
}


function getBaseAddressByTarget($target)
{
  if ($target=='ZX') return 0x8400; else
  if ($target=='MSX') return 0x100; else
  if ($target=='CPC') return 0x2880; else
  if ($target=='CP4') return 0x7080; else
  if ($target=='C64') return 0x3880;

  return 0;
};


function getTargetByMachineID($id)
{
  if ($id==0) return 'PC'; else
  if ($id==1) return 'ZX'; else
  if ($id==2) return 'C64'; else
  if ($id==3) return 'CPC'; else
  if ($id==4) return 'MSX'; else
  if ($id==5) return 'ST'; else
  if ($id==6) return 'AMIGA'; else
  if ($id==7) return 'PCW'; else
  if ($id==0x0E) return 'CP4'; else
  if ($id==0x0F) return 'MSX2';
};  


//####################################### LOOKUP TABLES #########################################
  

$tokens_to_iso8859_15 = array(
      0,   1,   2,   3,   4,   5,   6,   7,   8,   9,  //   0 -   9
     10,  11,  12,  13,  14,  15, 170, 161, 191, 171,  //  10 -  19
    187, 225, 233, 237, 243, 250, 241, 209, 231, 199,  //  20 -  29
    252, 220,  ord('_'),  33,  34,  35,  36,  37,  38,  39,  //  30 -  39
     40,  41,  42,  43,  44,  45,  46,  47,  48,  49,  //  40 -  49
     50,  51,  52,  53,  54,  55,  56,  57,  58,  59,  //  50 -  59
     60,  61,  62,  63,  64,  65,  66,  67,  68,  69,  //  60 -  69
     70,  71,  72,  73,  74,  75,  76,  77,  78,  79,  //  70 -  79
     80,  81,  82,  83,  84,  85,  86,  87,  88,  89,  //  80 -  89
     90,  91,  92,  93,  94,  95,  96,  97,  98,  99,  //  90 -  99
    100, 101, 102, 103, 104, 105, 106, 107, 108, 109,  // 100 - 109
    110, 111, 112, 113, 114, 115, 116, 117, 118, 119,  // 110 - 119
    120, 121, 122, 123, 124, 125, 126, 127             // 120 - 127
 );

$daad_to_iso8859_15 = array(
    255, 254, 253, 252, 251, 250, 249, 248, 247, 246,  //   0 -   9
    245, 244, 243, 242, 241, 240, 239, 238, 237, 236,  //  10 -  19
    235, 234, 233, 232, 231, 230, 229, 228, 227, 226,  //  20 -  29
    225, 224, 223, 222, 221, 220, 219, 218, 217, 216,  //  30 -  39
    215, 214, 213, 212, 211, 210, 209, 208, 207, 206,  //  40 -  49
    205, 204, 203, 202, 201, 200, 199, 198, 197, 196,  //  50 -  59
    195, 194, 193, 192, 191, 190, 189, 188, 187, 186,  //  60 -  69
    185, 184, 183, 182, 181, 180, 179, 178, 177, 176,  //  70 -  79
    175, 174, 173, 172, 171, 170, 169, 168, 167, 166,  //  80 -  89
    165, 164, 163, 162, 161, 160, 159, 158, 157, 156,  //  90 -  99
    155, 154, 153, 152, 151, 150, 149, 148, 147, 146,  // 100 - 109
    145, 144, 143, 142, 141, 140, 139, 138, 137, 136,  // 110 - 119
    135, 134, 133, 132, 131, 130, 129, 128, 127, 126,  // 120 - 129
    125, 124, 123, 122, 121, 120, 119, 118, 117, 116,  // 130 - 139
    115, 114, 113, 112, 111, 110, 109, 108, 107, 106,  // 140 - 149
    105, 104, 103, 102, 101, 100,  99,  98,  97,  96,  // 150 - 159
     95,  94,  93,  92,  91,  90,  89,  88,  87,  86,  // 160 - 169
     85,  84,  83,  82,  81,  80,  79,  78,  77,  76,  // 170 - 179
     75,  74,  73,  72,  71,  70,  69,  68,  67,  66,  // 180 - 189
     65,  64,  63,  62,  61,  60,  59,  58,  57,  56,  // 190 - 199
     55,  54,  53,  52,  51,  50,  49,  48,  47,  46,  // 200 - 209
     45,  44,  43,  42,  41,  40,  39,  38,  37,  36,  // 210 - 219
     35,  34,  33,  32, 220, 252, 199, 231, 209, 241,  // 220 - 229
    250, 243, 237, 233, 225, 187, 171, 191, 161, 170,  // 230 - 239
     15,  14,  13,  12,  11,  10,   9,   8,   7,   6,  // 240 - 249
      5,   4,   3,   2,   1,   0                       // 250 - 255
  );





//####################################### MAIN PROGRAM #########################################

// Check params
if (sizeof($argv) < 2) {
  usageInfo();
  exit (1); 
}

// By default, undaad will read the DDB file from offset 0, but that can be changed so you can, for instace, read from offset 2 in C64 DDB files which have the C64 header, or 
// directly from snapshots if you know where the DDB area is located
$fileOffset = 0;

if (sizeof($argv)>=3)
{
  for ($i=2;$i<sizeof($argv);$i++)
  {
    switch($argv[$i])
    {
      case '-h':
      case '-H': $headerOnly = true; break;
      case '-o':
      case '-O': {
                    if ($i==(sizeof($argv)-1)) {echo ("Invalid parameter: $argv[$i] \n"); usageInfo(); exit(1);}
                    $fileOffset = $argv[$i+1];
                    $fileOffset = intval($fileOffset);
                    if ($fileOffset<1) {echo ("Invalid parameter: ". $argv[$i]." ".$argv[$i+1]." \n"); usageInfo(); exit(1);}
                    $i++;
                    break;
                  }
      case '-d':
      case '-D': $exportToDSF = true; break;
      case '-v':
      case '-V': $verboseOutput = true; break;
      default: {echo ("Invalid parameter: $argv[$i] \n"); usageInfo(); exit(1);}
    }
  }
}

if (!file_exists($argv[1]))
{
  echo ("File not found.\n");
  usageInfo();
  exit (1); 
}


// Open input file
$file = fopen($argv[1],'r');
fseek($file, $fileOffset);
if($exportToDSF) $outputFileName = replace_extension($argv[1],'DSF'); else $outputFileName = replace_extension($argv[1],'SCE');
if ($outputFileName == $argv[1]) 
{
  echo ("Input and output file cannot be the same one.");
  exit(1);
}
$output =  fopen($outputFileName, 'wr');

fseek($file, 0 + $fileOffset);
$daad_version = fgetb($file);
$daad_machine = fgetb($file);
$daad_language = $daad_machine & 0x0F;
$daad_machine = ($daad_machine >> 4) & 0x0F;


if  (intval($daad_version)>=2)
{
  $isLittleEndian = isLittleEndianPlatform($daad_machine);
  if ($isLittleEndian)
  {
    $SHR1=8;
    $SHR2=0;
  }
}
else
{
  // Determine if it's a little endian old game or big endian game based on if it has or not extra attr.

  fseek($file, 32 + $fileOffset);
  $file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
  if ($file_length!=filesize($argv[1])) // Not matching, check offset 30
  {
    fseek($file, 30 + $fileOffset);
    $file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
    if ($file_length!=filesize($argv[1])) // Not matching, it's little endian
    {
      $SHR1=8;
      $SHR2=0;
      $isLittleEndian = true;
    }
  }
}

// Read header data
fseek($file, 2 + $fileOffset);
$nullword    = fgetb($file);
$num_objs     = fgetb($file);
$num_locs     = fgetb($file);
$num_msgs_usr = fgetb($file);
$num_msgs_sys = fgetb($file);
$num_procs    = fgetb($file);

$target = getTargetByMachineID($daad_machine);
$baseAddress = getBaseAddressByTarget($target);


$pos_tokens   = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_procs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_objs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_locs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_msgs_usr = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_msgs_sys = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_list_pos_cnxs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_vocabulary = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_locs_objs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_noms_objs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$pos_attr_objs = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );

// Determine if game is in the old format
if ($file_length==filesize($argv[1]))  // File length is at ofset 30
{
  $isOldGame  = true;
  // Patch condact list so it keeps old condacts
  $condacts[56] = array(2,'COPYOF ');
  $condacts[57] = array(2,'COPYOO ');
  $condacts[58] = array(2,'COPYFO ');
  $condacts[59] = array(2,'COPYFF ');
}
else
{
  $pos_extattr_objs = $file_length;
  $file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
}


$file_length-=$baseAddress;
$filesize = filesize($argv[1]);
if ($file_length>$filesize)
{
  echo ("Invalid DAAD header, length ($file_length) doesn't match file size ($filesize)!");
  exit;
}


// DUMP GLOBALS
write($output, ";---------------------------------------------------------------------------\n");
write($output, ";---------------------   An UnDAADed game o'hacker   -----------------------\n");
write($output, ";---------------------------------------------------------------------------\n");
write($output, "; DAAD Vers. : $daad_version\n");
write($output, "; Target     : $target ($daad_machine)\n");
write($output, "; Language   : ".$languages[$daad_language]." ($daad_language)\n");
write($output, "; BaseAddr   : ".prettyFormat($baseAddress)."\n");
write($output, "; Endianness : " . ($isLittleEndian ? "Little-endian" : "Big-endian") . "\n");
if ($target=='MSX2') 
write($output, "; MSX2 Mode  : ". getMSX2Subtarget($nullword)."\n"); else
write($output, "; Null Word  : ".chr($nullword)."\n");  
write($output, ";---------------------------------------------------------------------------\n");
write($output, "; Number of objects         : $num_objs\n");
write($output, "; Number of Locations       : $num_locs\n");
write($output, "; Number of User Messages   : $num_msgs_usr\n");
write($output, "; Number of System Messages : $num_msgs_sys\n");
write($output, "; Number of Processes       : $num_procs\n");
write($output, ";---------------------------------------------------------------------------\n");
write($output, ";                   Memory   File \n");
write($output, "; Pointer            Addr   Offset\n");
write($output, "; =======           ======  ======\n");
write($output, "; Tokens addr     : ".prettyFormat($pos_tokens)."  ".prettyFormat($pos_tokens!=0 ? $pos_tokens- $baseAddress: 0)."\n");  
write($output, "; Procs addr      : ".prettyFormat($pos_list_pos_procs)."  ".prettyFormat($pos_list_pos_procs-$baseAddress)."\n");  
write($output, "; Objs addr       : ".prettyFormat($pos_list_pos_objs)."  ".prettyFormat($pos_list_pos_objs-$baseAddress)."\n");  
write($output, "; Locs addr       : ".prettyFormat($pos_list_pos_locs)."  ".prettyFormat($pos_list_pos_locs-$baseAddress)."\n");  
write($output, "; UsrMsg addr     : ".prettyFormat($pos_list_pos_msgs_usr)."  ".prettyFormat($pos_list_pos_msgs_usr-$baseAddress)."\n");  
write($output, "; SysMsg addr     : ".prettyFormat($pos_list_pos_msgs_sys)."  ".prettyFormat($pos_list_pos_msgs_sys-$baseAddress)."\n");  
write($output, "; Connex addr     : ".prettyFormat($pos_list_pos_cnxs)."  ".prettyFormat($pos_list_pos_cnxs-$baseAddress)."\n");  
write($output, "; Vocabu addr     : ".prettyFormat($pos_vocabulary)."  ".prettyFormat($pos_vocabulary-$baseAddress)."\n");  
write($output, "; InitAt addr     : ".prettyFormat($pos_locs_objs)."  ".prettyFormat($pos_locs_objs-$baseAddress)."\n");  
write($output, "; ObjName addr    : ".prettyFormat($pos_noms_objs)."  ".prettyFormat($pos_noms_objs-$baseAddress)."\n");  
write($output, "; Weight/CW addr  : ".prettyFormat($pos_attr_objs)."  ".prettyFormat($pos_attr_objs-$baseAddress)."\n");  
if (!$isOldGame) 
write($output, "; Extra attr addr : ".prettyFormat($pos_extattr_objs)."  ".prettyFormat($pos_extattr_objs-$baseAddress)."\n");  
write($output, ";---------------------------------------------------------------------------\n");
write($output, "; File length    : ".prettyFormat($file_length)." ($file_length bytes)\n");  
write($output, ";---------------------------------------------------------------------------\n;\n;\n");
if ($headerOnly) 
{
  fclose($file);
  fclose($output);
  exit(0);
}


if ($pos_tokens) $pos_tokens -= $baseAddress; // Only subsstract baseAddress if not zero, if zero it means there are no tokens (DDB has no compression at all)
$pos_list_pos_procs -= $baseAddress;
$pos_list_pos_objs -= $baseAddress;
$pos_list_pos_locs -= $baseAddress;
$pos_list_pos_msgs_usr -= $baseAddress;
$pos_list_pos_msgs_sys -= $baseAddress;
$pos_list_pos_cnxs -= $baseAddress;
$pos_vocabulary -= $baseAddress;
$pos_locs_objs -= $baseAddress;
$pos_noms_objs -= $baseAddress;
$pos_attr_objs -= $baseAddress;
$pos_extattr_objs -= $baseAddress;

// CONTROL
write($output, "/CTL\n_\n");

if (!$exportToDSF)
{
  if ($pos_tokens) // If no compression, $pos_tokens must be 0x0000
  {
    // TOKENS
    write($output, "/TOK\n");
    fseek ($file, $pos_tokens + 1 + $fileOffset);  // It seems actual token table starts one byte after the one the header points to
    $tokenCount = 0;
    $token = '';
    $c = fgetb($file); // Ignore first
    while ($tokenCount<128)  // There should be exactly 128 tokens
    {
      $c = fgetb($file);
      
      if ($c==0) break;
      if ($c > 127) {
        
        $token .=  chr($tokens_to_iso8859_15[$c & 127]);
        if ($exportToDSF) write($output, ';');
        write($output, "$token\n");
        $tokens[$tokenCount] = str_replace('_', ' ',  $token);
        $tokenCount++;
        $token = '';
      } else $token .=  chr($tokens_to_iso8859_15[$c]);
    }
  }
}


//VOCABULARY
printSeparator($output);
write($output, "/VOC    ;Vocabulary\n");
fseek ($file, $pos_vocabulary +$fileOffset);
while (1)
{
  $c = fgetb($file);
  if (!$c) break;  // End of vocabulary list  
  $currentWord = chr($c); $currentCodes = dechex($c) ." ";
  $c = $daad_to_iso8859_15[$c];
  $currentWord = chr($c); $currentCodes .= "($c) ";
  for ($i=0;$i<4;$i++) 
  { 
    $c= fgetb($file);
    $currentCodes .=dechex($c). " ";
    $c = $daad_to_iso8859_15[$c];
    $currentWord .= chr($c); 
    $currentCodes.= "($c) ";
  }
  $id  = fgetb($file);
  $currentCodes .= "(ID: $id ";
  $wordType = fgetb($file);
  $currentCodes .= "type: $wordType) ";
  $wordTypeText = $wordTypes[$wordType];
  if (!$verboseOutput) $currentCodes = ''; else $currentCodes = "; - $currentCodes -";
  write($output,str_pad($currentWord, 8).str_pad($id, 8).$wordTypeText."$currentCodes\n");
  if (!isset($words[$wordType])) $words[$wordType] = array();
  $words[$wordType][$id]=$currentWord;

}
for ($i=0;$i<7;$i++) $words[$i][255] = '_';

// SYSTEM MESSAGES
printSeparator($output);
write($output, "/STX    ;System Messages Texts\n");
for ($i = 0; $i < $num_msgs_sys; $i++)
  {
    $message='';
    write($output, "/$i "); 
    if(!$exportToDSF) write($output,"\n");else write($output,"\"");
    fseek ($file, $pos_list_pos_msgs_sys + (2 * $i) + $fileOffset);
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    fseek ($file, $current_message_position - $baseAddress + $fileOffset);
    do
    {
      $c = fgetb($file);
      if (!$pos_tokens) 
        {
          $d= $daad_to_iso8859_15[$c];
          if ($d != 10) $message.=chr($d); else  if (($d == 10) && (!$exportToDSF)) $message.=chr($d);
        }
      else  
        {
          if ($c < 128) 
            {
              $token_id = $c ^ 0xff - 128;
              $thetoken = $tokens[$token_id];
              $message.=$thetoken;
            } else 
            {
                $d = $daad_to_iso8859_15[$c];
                if ($d==0x0c) $message.= (($exportToDSF ? "#":"\\") . 'k');
                else if ($d==0x0e) $message.= (($exportToDSF ? "#":"\\") . 'g');
                else if ($d==0x0f) $message.= (($exportToDSF ? "#":"\\") . 't');
                else if ($d==0x0b) $message.= (($exportToDSF ? "#":"\\") . 'b');
                else if ($d==0x7f) $message.= (($exportToDSF ? "#":"\\") . 'f');
                else if ($d==0x0d) $message.= ($exportToDSF ? "":"\\r");
                else if (($d==0x0a) && ($exportToDSF)) $message.='#n';
                else $message.=chr($d);            }
           }
    } while ($c != 0xF5);  // 0x0A xor 255
    $message = str_replace(chr(13), '\n', $message);
    $message = str_replace('"', '\"', $message);
    write ($output, $message);
    if($exportToDSF) write($output, "\"\n");
  }

  

// USER MESSAGES
printSeparator($output);
write($output, "/MTX    ;Message Texts\n");
for ($i = 0; $i < $num_msgs_usr; $i++)
  {
    $message='';
    write($output, "/$i "); 
    if(!$exportToDSF) write($output,"\n");else write($output,"\"");
    fseek ($file, $pos_list_pos_msgs_usr + (2 * $i) + $fileOffset);
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    fseek ($file, $current_message_position - $baseAddress + $fileOffset);
    do
    {
      $c = fgetb($file);
      if (!$pos_tokens) 
        {
          $d= $daad_to_iso8859_15[$c];
          if ($d != 10) $message.=chr($d); else  if (($d == 10) && (!$exportToDSF)) $message.=chr($d);
        }
      else  
        {
          if ($c < 128) 
            {
              $token_id = $c ^ 0xff - 128;
              $thetoken = $tokens[$token_id];
              $message.=$thetoken;
            } else 
            {
              $d = $daad_to_iso8859_15[$c];
              if ($d==0x0c) $message.= (($exportToDSF ? "#":"\\") . 'k');
              else if ($d==0x0e) $message.= (($exportToDSF ? "#":"\\") . 'g');
              else if ($d==0x0f) $message.= (($exportToDSF ? "#":"\\") . 't');
              else if ($d==0x0b) $message.= (($exportToDSF ? "#":"\\") . 'b');
              else if ($d==0x7f) $message.= (($exportToDSF ? "#":"\\") . 'f');
              else if ($d==0x0d) $message.= (($exportToDSF ? "":"\\r"));
              else if (($d==0x0a) && ($exportToDSF)) $message.='#n';
              else $message.=chr($d);            }
        }
    } while ($c != 0xF5);  // 0x0A xor 255
    $message = str_replace(chr(13), '\n', $message);
    $message = str_replace('"', '\"', $message);
    write ($output, $message);
    if($exportToDSF) write($output, "\"\n");
  }


// OBJECT DESCRIPTIONS
printSeparator($output);
write($output, "/OTX    ;Object Texts\n");
for ($i = 0; $i < $num_objs; $i++)
  {
    $message='';
    write($output, "/$i "); 
    if(!$exportToDSF) write($output,"\n");else write($output,"\"");
    fseek ($file, $pos_list_pos_objs + (2 * $i) + $fileOffset);
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    fseek ($file, $current_message_position - $baseAddress + $fileOffset);
    do
    {
      $c = fgetb($file);
      if (!$pos_tokens) 
        {
          $d= $daad_to_iso8859_15[$c];
          if ($d != 10) $message.=chr($d); else  if (($d == 10) && (!$exportToDSF)) $message.=chr($d);
        }
      else  
        {
          if ($c < 128) 
            {
              $token_id = $c ^ 0xff - 128;
              $thetoken = $tokens[$token_id];
              $message.=$thetoken;
            } else 
            {
              $d = $daad_to_iso8859_15[$c];
              if ($d != 10) $message.=chr($d); else  if (($d == 10) && (!$exportToDSF)) $message.=chr($d);
            }
        }
    } while ($c != 0xF5);  // 0x0A xor 255
    $message = str_replace(chr(13), '\n', $message);
    $message = str_replace('"', '\"', $message);
    write ($output, $message);
    if($exportToDSF) write($output, "\"\n");
  }




// LOCATIONS (may be compressed)
printSeparator($output);
write($output, "/LTX    ;Location Texts\n");
for ($i = 0; $i < $num_locs; $i++)
  {
    $message='';
    write($output, "/$i "); 
    if(!$exportToDSF) write($output,"\n");else write($output,"\"");
    fseek ($file, $pos_list_pos_locs + (2 * $i) + $fileOffset);
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    fseek ($file, $current_message_position - $baseAddress + $fileOffset);
    do
    {
      $c = fgetb($file);
      if (!$pos_tokens) 
        {
          $d= $daad_to_iso8859_15[$c];
          if ($d != 10) $message.=chr($d); else  if (($d == 10) && (!$exportToDSF)) $message.=chr($d);
        }
      else  
        {
          if ($c < 128) 
            {
              $token_id = $c ^ 0xff - 128;
              $thetoken = $tokens[$token_id];
              $message.=$thetoken;
            } else 
            {
              $d = $daad_to_iso8859_15[$c];
              if ($d==0x0c) $message.= (($exportToDSF ? "#":"\\") . 'k');
              else if ($d==0x0e) $message.= (($exportToDSF ? "#":"\\") . 'g');
              else if ($d==0x0f) $message.= (($exportToDSF ? "#":"\\") . 't');
              else if ($d==0x0b) $message.= (($exportToDSF ? "#":"\\") . 'b');
              else if ($d==0x7f) $message.= (($exportToDSF ? "#":"\\") . 'f');
              else if ($d==0x0d) $message.= (($exportToDSF ? "":"\\r"));
              else if (($d==0x0a) && ($exportToDSF)) $message.='#n';
              else $message.=chr($d);            }
        }
    } while ($c != 0xF5);  // 0x0A xor 255
    $message = str_replace(chr(13), '\n', $message);
    $message = str_replace('"', '\"', $message);
    write ($output, $message);
    if($exportToDSF) write($output, "\"\n");
  }


  // CONNECTIONS
  printSeparator($output);
  write($output, "/CON    ;Conections\n");
  for ($i = 0; $i < $num_locs; $i++)
  {
    write($output, "/$i\n");
    fseek ($file, $pos_list_pos_cnxs + (2 * $i) + $fileOffset);
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file)<<$SHR2);
    fseek ($file, $current_message_position - $baseAddress + $fileOffset);
    while (($c = fgetb($file)) != 255)
    {
      if (isset($words[0][$c])) $word = $words[0][$c];
      else if ((isset($words[2][$c])) && ($c<20)) $word = $words[2][$c];
      write($output, "$word " . fgetb($file) . "\n");
    } 
  }

  // OBJECT DATA
  printSeparator($output);
  write($output, "/OBJ    ;Objects data\n");
  write($output, ";obj.no  starts.at   weight    c w  5 4 3 2 1 0 9 8 7 6 5 4 3 2 1 0    noun    adjective\n");
  for ($i = 0; $i < $num_objs; $i++)
  {
    fseek ($file, $pos_locs_objs + $i + $fileOffset);
    write($output,str_pad("/$i ", 10));
    // initially at
    $loc = fgetb($file);
    $byteCode ="Loc: $loc ";
    write($output,str_pad(isset($specialLocs[$loc]) ? $specialLocs[$loc] : $loc, 13));
    // Object attributes
    fseek ($file, $pos_attr_objs + $i  + $fileOffset);
    $attr = fgetb($file);  //weight
    $byteCode .= "Attr/Weight: $attr ";
    $weigth = $attr & 0x3F;
    write($output,str_pad($weigth, 8));
    $container = ($attr & 0x40) ? 'Y' : '_';
    write($output, "$container ");
    $worn = ($attr & 0x80) ? 'Y' : '_';
    write($output, "$worn  ");

    if (!$isOldGame)
    {
      fseek ($file, $pos_extattr_objs + ($i * 2)  + $fileOffset);
      $attrs = ((fgetb($file)<<$SHR1)) | ((fgetb($file)<<$SHR2));
      $byteCode .= "XAttr: $attrs ";
      for ($j=15;$j>=0;$j--)
         write($output,($attrs& (1<<$j)) ? 'Y ':'_ ');
      write($output, "   ");
    }
    else write($output, "_ _  _ _ _ _ _ _ _ _ _ _ _ _ _ _    ");

    // Object noun + adjective
    fseek ($file, $pos_noms_objs + ($i *2) + $fileOffset);
    $noun_id = fgetb($file);
    $adject_id = fgetb($file);
    $byteCode .= "Noun: $noun_id Adject: $adject_id ";
    write($output,str_pad($words[2][$noun_id], 7));
    write($output,' ');
    if ($adject_id == 255) write($output, "_"); else write($output,$words[3][$adject_id]);
    if ($verboseOutput) write($output, " ; $byteCode");
    write($output, " \n");
  }

  $terminatorOpcodes = array(22, 23,103, 116,117,108);  //DONE/OK/NOTDONE/SKIP/RESTART/REDO

  // PROCESSES
  for ($i=0;$i<$num_procs;$i++)
  {
    printSeparator($output);
    write($output, "/PRO $i\n"); 
    for ($entry = 0; ; $entry++)
    {
      $entryOffsetPosition = $pos_list_pos_procs + (2 * $i);
      fseek ($file, $entryOffsetPosition + $fileOffset);
      $condactsOffset = ((fgetb($file) << $SHR1) | (fgetb($file)) << $SHR2) - $baseAddress;
      fseek ($file, $condactsOffset + $fileOffset);
      fseek ($file, $entry*4, SEEK_CUR);
      $condactsOffset+=$entry*4;
      $c = fgetb($file);
      write($output, "\n");
      if ($c == 0)  break; // Process end
      if ($exportToDSF) $entrySign = "> "; else $entrySign ='';
      if ($c == 255) write($output,str_pad($entrySign . "_", 8)); else
      {
        if (isset($words[0][$c])) $word = $words[0][$c];
        else if ((isset($words[2][$c])) && ($c<20)) $word = $words[2][$c];
        write($output,str_pad($entrySign . "$word", 8));
      }
      $verbCode = $c;
      $c = fgetb($file);
      $nounCode = $c;
      write($output,str_pad($c == 255 ? "_" : $words[2][$c], 8));
      $condacts_pos = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
      fseek ($file, $condacts_pos - $baseAddress + $fileOffset); // condacts
      $first = true;
      $c = fgetb($file);
      while ($c != 255)
      {
        $byteCode = "$c ";
        if (!$first) write($output, "                ");
        $indirection = 0;
        if ($c > 127)
        {
          $c -= 128;
          $indirection = 1;
        }
        if ($c >= sizeof($condacts))
        {
          write($output, ";ERROR: unknown condact code: $c \n");
          break;
        }
        $opcode = $c;
        write($output,'        '.$condacts[$opcode][1].' ');
        for ($j = 0; $j < $condacts[$opcode][0]; $j++)
        {
          $val = fgetb($file);
          $byteCode .= "$val ";
          if (isset($wordParamTypes[$opcode][$j]) && isset($words[$wordParamTypes[$opcode][$j]][$val])) 
            write($output,$words[$wordParamTypes[$opcode][$j]][$val]." ");
          else 
          {
            if ($exportToDSF) write($output,(($indirection) && ($j==0) ? "@$val " : "$val "));
            else write($output,(($indirection) && ($j==0) ? "[$val] " : "$val "));
            
          }
        }
        if ($verboseOutput) write($output, "\t\t\t; $byteCode ");
        if (($verboseOutput) && ($first)) write($output, "\t; (Verb: $verbCode - Noun: $nounCode)");
        write($output, "\n");
        $first = false;
        $c = fgetb($file);
        if (in_array($opcode, $terminatorOpcodes) && ($c!=255)) // If compiled with DRC, there is a chance there is no terminator after one of this codes
        {
          fseek($file, -1, SEEK_CUR);  // Create fake $ff and rewind file one byte
          $c = 255;
        }
        
      }
    }
  }  
  write($output,"\n");
  write($output, ";---------------------------------------------------------------------------\n");
  if ($exportToDSF) write($output, "/END\n");
  fclose ($file);
  fclose($output);
