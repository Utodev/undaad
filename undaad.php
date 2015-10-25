<?php


// Copyright (C) 2008-2010, 2013 José Manuel Ferrer Ortiz
// Fixed and completed by Uto (2015)


// Header of the DDB files

// POSITION  LENGTH    CONTAINS    
// 0         1 byte    Seems to contain DAAD version number (1 for Aventura Original and Jabato, 1989, 2 for the rest)
// 1         1 byte    Always contains 1, not identified
// 2         1 byte    Always contains 95, not identified
// 3         1 byte    Number of object descriptions
// 4         1 byte    Number of location descriptions
// 5         1 byte    Number of system messages
// 6         1 byte    Number of user messages
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

TIPS:

ORIGINAL & JABATO header word values are little endian
COZUMEL, ESPACIAL, TEMPLOS & CHICHEN ITZA big endian

ORIGINAL, JABATO, COZUMEL use an old version hat doesn't support extra object attributes
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
 echo "Usage: undaad.php <ddb_file> [options]\n";
 echo "\n";
 echo "Options:\n";
 echo "/h /H : show only header data";
}

//######################################## GLOBAL VARS ##########################################

// Handle big endian/little endian games
$SHR1=0;
$SHR2=8;



// Command line parameter settings
$headerOnly=false;


//Other vars
$isOldGame = false;
$isLittleEndian = false;

// Things to beter save in memory
$tokens=array();
$words=array();


// Predefined tables

// Vocabulary word types
$wordTypes = array("verb", "adverb", "noun", "adjective", "preposition",
                         "conjunction", "pronoun");

$wordParamTypes = array(
  "17"=>1, // ADVERB
  "69"=>2, // NOUN2
  "16"=>3, // ADJECT1
  "70"=>3, // ADJECT2
  "68"=>4  // PREP
  );


$condacts = array(
array(1,'AT     '), //   0
array(1,'NOTAT  '), //   1
array(1,'ATGT   '), //   2
array(1,'ATLT   '), //   3
array(1,'PRESENT'), //   4
array(1,'ABSENT '), //   5
array(1,'WORN   '), //   6
array(1,'NOTWORN'), //   7
array(1,'CARRIED'), //   8
array(1,'NOTCARR'), //   9
array(1,'CHANCE '), //  10
array(1,'ZERO   '), //  11
array(1,'NOTZERO'), //  12
array(2,'EQ     '), //  13
array(2,'GT     '), //  14
array(2,'LT     '), //  15
array(1,'ADJECT1'), //  16
array(1,'ADVERB '), //  17
array(0,'INVEN  '), //  18
array(0,'DESC   '), //  19
array(0,'QUIT   '), //  20
array(0,'END    '), //  21
array(0,'DONE   '), //  22
array(0,'OK     '), //  23
array(0,'ANYKEY '), //  24
array(0,'SAVE   '), //  25
array(0,'LOAD   '), //  26
array(1,'DPRINT '), //  27 *
array(1,'DISPLAY'), //  28 *
array(0,'CLS    '), //  29
array(0,'DROPALL'), //  30
array(0,'AUTOG  '), //  31
array(0,'AUTOD  '), //  32
array(0,'AUTOW  '), //  33
array(0,'AUTOR  '), //  34
array(1,'PAUSE  '), //  35
array(2,'SYNONYM'), //  36 *
array(1,'GOTO   '), //  37
array(1,'MESSAGE'), //  38
array(1,'REMOVE '), //  39
array(1,'GET    '), //  40
array(1,'DROP   '), //  41
array(1,'WEAR   '), //  42
array(1,'DESTROY'), //  43
array(1,'CREATE '), //  44
array(2,'SWAP   '), //  45
array(2,'PLACE  '), //  46
array(1,'SET    '), //  47
array(1,'CLEAR  '), //  48
array(2,'PLUS   '), //  49
array(2,'MINUS  '), //  50
array(2,'LET    '), //  51
array(0,'NEWLINE'), //  52
array(1,'PRINT  '), //  53
array(1,'SYSMESS'), //  54
array(2,'ISAT   '), //  55
array(1,'SETCO  '), //  56 COPYOF in old games
array(0,'SPACE  '), //  57 COPYOO in old games
array(1,'HASAT  '), //  58 COPYFO in old games
array(1,'HASNAT '), //  59 COPYFF in old games
array(0,'LISTOBJ'), //  60
array(1,'EXTERN '), //  61
array(0,'RAMSAVE'), //  62
array(1,'RAMLOAD'), //  63
array(2,'BEEP   '), //  64
array(1,'PAPER  '), //  65
array(1,'INK    '), //  66
array(1,'BORDER '), //  67
array(1,'PREP   '), //  68
array(1,'NOUN2  '), //  69
array(1,'ADJECT2'), //  70
array(2,'ADD    '), //  71
array(2,'SUB    '), //  72
array(0,'PARSE  '), //  73
array(1,'LISTAT '), //  74
array(1,'PROCESS'), //  75
array(2,'SAME   '), //  76
array(1,'MES    '), //  77
array(1,'WINDOW '), //  78
array(2,'NOTEQ  '), //  79
array(2,'NOTSAME'), //  80
array(1,'MODE   '), //  81
array(2,'WINAT  '), //  82
array(2,'TIME   '), //  83
array(1,'PICTURE'), //  84
array(1,'DOALL  '), //  85
array(1,'MOUSE  '), //  86
array(2,'GFX    '), //  87
array(2,'ISNOTAT'), //  88
array(2,'WEIGH  '), //  89
array(2,'PUTIN  '), //  90
array(2,'TAKEOUT'), //  91
array(0,'NEWTEXT'), //  92
array(2,'ABILITY'), //  93
array(1,'WEIGHT '), //  94
array(1,'RANDOM '), //  95
array(2,'INPUT  '), //  96 
array(0,'SAVEAT '), //  97
array(0,'BACKAT '), //  98
array(2,'PRINTAT'), //  99
array(0,'WHATO  '), // 100
array(1,'CALL   '), // 101
array(1,'PUTO   '), // 102
array(0,'NOTDONE'), // 103
array(1,'AUTOP  '), // 104
array(1,'AUTOT  '), // 105
array(1,'MOVE   '), // 106
array(2,'WINSIZE'), // 107
array(0,'REDO   '), // 108
array(0,'CENTRE '), // 109
array(1,'EXIT   '), // 110
array(0,'INKEY  '), // 111 
array(2,'BIGGER '), // 112
array(2,'SMALLER'), // 113 
array(0,'ISDONE '), // 114
array(0,'ISNDONE'), // 115 
array(1,'SKIP   '), // 116 
array(0,'RESTART'), // 117 
array(1,'TAB    '), // 118
array(2,'COPYOF '), // 119
array(0,'dumb   '), // 120 (according DAAD manual, internal)
array(2,'COPYOO '), // 121 
array(0,'dumb   '), // 122 (according DAAD manual, internal)
array(2,'COPYFO '), // 123
array(0,'dumb   '), // 124 (according DAAD manual, internal)
array(2,'COPYFF '), // 125 
array(2,'COPYBF '), // 126 
array(0,'RESET  ')  // 127 
);



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

if (sizeof($argv)>=3)
{
  for ($i=2;$i<sizeof($argv);$i++)
  {
    switch($argv[$i])
    {
      case '/h':
      case '/H': $headerOnly = true; break;
      default: echo "Invalid parameter: $argv[$i] \n"; usageInfo(); exit(1);
    }
  }
}

if (!file_exists($argv[1]))
{
  echo "File not found.\n";
  usageInfo();
  exit (1); 
}


// Open input file
$file = fopen($argv[1],'r');


// Determine if it's a little endian old game or big endian game
fseek($file, 32);
$file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
if ($file_length!=filesize($argv[1])) // Not matching, check offset 30
{
  fseek($file, 32);
  $file_length = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
  if ($file_length!=filesize($argv[1])) // Not matching, it's little endian
  {
    $SHR1=8;
    $SHR2=0;
    $isLittleEndian = true;
   }
}


// Read header data
fseek($file, 0);
$daad_version = fgetb($file);
$signature = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2 );
$num_objs     = fgetb($file);
$num_locs     = fgetb($file);
$num_msgs_usr = fgetb($file);
$num_msgs_sys = fgetb($file);
$num_procs    = fgetb($file);



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


if ($file_length!=filesize($argv[1]))
{
  echo "Invalid DAAD header!";
  exit;
}


// DUMP GLOBALS
echo ";---------------------------------------------------------------------------\n";
echo ";---------------------   An UnDAADed game o'hacker   -----------------------\n";
echo ";---------------------------------------------------------------------------\n";
echo "; Version   : $daad_version\n";
echo "; Signature : ".strtoupper(str_pad(dechex($signature),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Data      : " . ($isLittleEndian ? "Little-endian" : "Big-endian") . "\n";
echo "; Objects   : $num_objs\n";
echo "; Locations : $num_locs\n";
echo "; Usr Mess  : $num_msgs_usr\n";
echo "; Sys Mess  : $num_msgs_sys\n";
echo "; Processes : $num_procs\n";
echo ";---------------------------------------------------------------------------\n;\n;\n";
echo "; Tokens addr    : ".strtoupper(str_pad(dechex($pos_tokens),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Objs addr      : ".strtoupper(str_pad(dechex($pos_list_pos_objs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Locs addr      : ".strtoupper(str_pad(dechex($pos_list_pos_locs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; UsrMsg addr    : ".strtoupper(str_pad(dechex($pos_list_pos_msgs_usr),4,'0',STR_PAD_LEFT))."h\n";  
echo "; SysMsg addr    : ".strtoupper(str_pad(dechex($pos_list_pos_msgs_sys),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Connex addr    : ".strtoupper(str_pad(dechex($pos_list_pos_cnxs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Vocabu addr    : ".strtoupper(str_pad(dechex($pos_vocabulary),4,'0',STR_PAD_LEFT))."h\n";  
echo "; InitAt addr    : ".strtoupper(str_pad(dechex($pos_locs_objs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; ObjName addr   : ".strtoupper(str_pad(dechex($pos_noms_objs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; Weight/CW addr : ".strtoupper(str_pad(dechex($pos_attr_objs),4,'0',STR_PAD_LEFT))."h\n";  
if (!$isOldGame) echo "; Extra attr addr: ".strtoupper(str_pad(dechex($pos_extattr_objs),4,'0',STR_PAD_LEFT))."h\n";  
echo "; File legth     : ".strtoupper(str_pad(dechex($file_length),4,'0',STR_PAD_LEFT))."h ($file_length)\n";  
echo ";---------------------------------------------------------------------------\n;\n;\n";

if ($headerOnly) exit(0);

// CONTROL
echo "/CTL\n_\n";

if ($pos_tokens)
{
  // TOKENS
  echo "/TOK\n";
  fseek ($file, $pos_tokens + 1);  // It seems actual token table starts one byte after the one the header points to
  $tokenCount = 0;
  $token = '';
  while ($tokenCount<128)  // There should be exactly 128 tokens
  {
    $c = fgetb($file);
    if ($c > 127) {
      $token .=  chr($tokens_to_iso8859_15[$c & 127]);
      echo "$token\n";
      $tokens[$tokenCount] = str_replace('_', ' ',  $token);
      $tokenCount++;
      $token = '';
    } else $token .=  chr($tokens_to_iso8859_15[$c]);
  }
}


//VOCABULARY
echo "/VOC\n";
fseek ($file, $pos_vocabulary);
while (1)
{
  $c = fgetb($file);
  if (!$c) break;  // End of vocabulary list  
  $currentWord = chr($daad_to_iso8859_15[$c]);
  for ($i=0;$i<4;$i++) $currentWord .= chr($daad_to_iso8859_15[fgetb($file)]);
  $id  = fgetb($file);
  $wordType = fgetb($file);
  $wordTypeText = $wordTypes[$wordType];
  echo "$currentWord\t\t$id\t\t$wordTypeText\n";  
  if (!isset($words[$wordType])) $words[$wordType] = array();
  $words[$wordType][$id]=$currentWord;
}
for ($i=0;$i<7;$i++) $words[$i][255] = '_';

// SYSTEM MESSAGES
echo "/STX\n";
for ($i = 0; $i < $num_msgs_sys; $i++)
{
  echo "/$i\n";
  fseek ($file, $pos_list_pos_msgs_sys + (2 * $i));
  $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file)<< $SHR2);
  fseek ($file, $current_message_position);
  do
  {
    $c = $daad_to_iso8859_15[fgetb($file)];
    echo chr($c);
  } while ($c != 10);     
}

// USER MESSAGES
echo "/MTX\n";
for ($i = 0; $i < $num_msgs_usr; $i++)
{
  echo "/$i\n";
  fseek ($file, $pos_list_pos_msgs_usr + (2 * $i));
  $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
  fseek ($file, $current_message_position);
  do
  {
    $c = $daad_to_iso8859_15[fgetb($file)];
    echo chr($c);
  } while ($c != 10);     
}

// OBJECT DESCRIPTIONS
echo "/OTX\n";
for ($i = 0; $i < $num_objs; $i++)
{
  echo "/$i\n";
  fseek ($file, $pos_list_pos_objs + (2 * $i));
  $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
  fseek ($file, $current_message_position);
  do
  {
    $c = $daad_to_iso8859_15[fgetb($file)];
    echo chr($c);
  } while ($c != 10);     
}

// LOCATIONS (may be compressed)
echo "/LTX\n";
for ($i = 0; $i < $num_locs; $i++)
  {
    echo "/$i\n";
    fseek ($file, $pos_list_pos_locs + (2 * $i));
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    fseek ($file, $current_message_position);
    do
    {
      $c = fgetb($file);
      if (!$pos_tokens) echo chr($daad_to_iso8859_15[$c]);
      else  
        {
          if ($c < 127) 
            {
              $token_id = $daad_to_iso8859_15[$c] - 128;
              echo $tokens[$token_id];
            } else echo chr($daad_to_iso8859_15[$c]);
        }
    } while ($c != 0xF5);  // 0x0A xor 255
  }


  // CONNECTIONS
  echo "/CON\n";
  for ($i = 0; $i < $num_locs; $i++)
  {
    echo "/$i\n";
    fseek ($file, $pos_list_pos_cnxs + (2 * $i));
    $current_message_position = (fgetb($file) << $SHR1) | (fgetb($file)<<$SHR2);
    fseek ($file, $current_message_position);
    while (($c = fgetb($file)) != 255)
    {
      if (isset($words[0][$c])) $word = $words[0][$c];
      else if ((isset($words[2][$c])) && ($c<20)) $word = $words[2][$c];
      echo "$word " . fgetb($file) . "\n";
    } 
  }

  // OBJECT DATA
  echo "/OBJ\n";
  for ($i = 0; $i < $num_objs; $i++)
  {
    fseek ($file, $pos_locs_objs + $i);
    echo "\n/$i ";
    // initially at
    echo fgetb($file) . ' '; 
    // Object attributes
    fseek ($file, $pos_attr_objs + $i );
    $attr = fgetb($file) . ' ';  //weight
    $weigth = $attr & 0x3F;
    echo "$weigth ";
    $container = ($attr & 0x40) ? 'Y' : '_';
    echo "$container ";
    $worn = ($attr & 0x80) ? 'Y' : '_';
    echo "$worn ";

    if ($has_extattr)
    {
      fseek ($file, $pos_extattr_objs + ($i * 2) );
      $attrs = ((fgetb($file)<<$SHR1)) | ((fgetb($file)<<$SHR2));
      for ($j=15;$j>=0;$j--)
         echo ($attrs& (1<<$j)) ? 'Y ':'_ ';
    }
    else echo "_ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ ";

    // Object noun + adjective
    fseek ($file, $pos_noms_objs + ($i *2));
    $noun_id = fgetb($file);
    $adject_id = fgetb($file);
    if ($noun_id == 255) echo "_"; else echo $words[2][$noun_id];
    echo ' ';
    if ($adject_id == 255) echo "_"; else echo $words[3][$adject_id];
    echo ' ';

  }

  // PROCESSES
  for ($i=0;$i<$num_procs;$i++)
  {
   echo "/PRO $i\n"; 
   for ($entry = 0; ; $entry++)
   {
    fseek ($file, $pos_list_pos_procs + (2 * $i));
    fseek ($file, (fgetb($file) << $SHR1) | (fgetb($file)) << $SHR2);
    fseek ($file, $entry*4, SEEK_CUR);
    $c = fgetb($file);
    if ($c == 0)  break; // Process end
    echo "\n";
    if ($c == 255) echo  "_ "; else
    {
      if (isset($words[0][$c])) $word = $words[0][$c];
      else if ((isset($words[2][$c])) && ($c<20)) $word = $words[2][$c];
      echo "$word ";
    }
    $c = fgetb($file);
    if ($c == 255) echo  "_"; else echo $words[2][$c];
    $condacts_pos = (fgetb($file) << $SHR1) | (fgetb($file) << $SHR2);
    echo "\n";
    fseek ($file, $condacts_pos); // condacts
    while (($c = fgetb($file)) != 255)
      {
        $indirection = 0;
        if ($c > 127)
        {
          $c -= 128;
          $indirection = 1;
        }
        if ($c >= sizeof($condacts))
        {
          echo ";ERROR: unknown condact code: $c \n";
          break;
        }
        echo ' ' . $condacts[$c][1] .' ';
        
        for ($j = 0; $j < $condacts[$c][0]; $j++)
        {
          $val = fgetb($file);
          if (isset($wordParamTypes[$c])) echo $words[$wordParamTypes[$c]][$val];
          else if (($indirection) && ($j==0)) echo "[$val] "; else echo "$val ";
        }
        echo "\n";
      }
    }
  }  
  fclose ($file);
  echo  "\n";
  echo ";---------------------------------------------------------------------------\n";


