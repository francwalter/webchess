<?php
/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores, rigao

    WebChess is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WebChess is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WebChess.  If not, see <http://www.gnu.org/licenses/>.
*/

declare(strict_types=1);

$installerLang = strtolower((string)($_GET['lang'] ?? $_POST['installer_lang'] ?? ''));
if (!in_array($installerLang, ['en', 'de'], true)) {
    $installerLang = 'en';
}

require __DIR__ . '/installer_locale.php';

function t(string $key): string
{
    global $INSTALLER_STRINGS, $installerLang;
    return $INSTALLER_STRINGS[$installerLang][$key]
        ?? $INSTALLER_STRINGS['en'][$key]
        ?? $key;
}

function ie(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
/*******************************************************************************
 *                                                                             *
 *        This block of functions creates the table-system of WebChess.        *
 *                                                                             *
 ******************************************************************************/
//ToDo: The probing algoritm must check inside tables to see if it has all its fields.
function tableExists($dbh, $tablename) {
  $tableExists = false;
  $currentDbRes = mysqli_query($dbh, "SELECT DATABASE()");
  $currentDbRow = $currentDbRes ? mysqli_fetch_row($currentDbRes) : null;
  $currentDb = $currentDbRow ? (string)$currentDbRow[0] : '';
  if ($currentDb === '')
    return false;

  $stmt = mysqli_prepare(
    $dbh,
    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1"
  );
  if ($stmt)
  {
    mysqli_stmt_bind_param($stmt, "ss", $currentDb, $tablename);
    mysqli_stmt_execute($stmt);
    $Result = mysqli_stmt_get_result($stmt);
    $tableExists = ($Result && mysqli_num_rows($Result) > 0);
    mysqli_stmt_close($stmt);
  }

  return $tableExists;
}

function webchessIsSafeSqlIdentifier($value) {
  return (is_string($value) && preg_match('/^[A-Za-z0-9_]+$/', $value) === 1);
}

function createTableGames($dbh){
	$SQLCreateTableGames = "CREATE TABLE games (
		gameID SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		whitePlayer MEDIUMINT NOT NULL,
		blackPlayer MEDIUMINT NOT NULL,
		gameMessage ENUM('playerInvited', 'inviteDeclined', 'draw', 'playerResigned', 'checkMate') NULL,
		messageFrom ENUM('black', 'white') NULL,
		dateCreated DATETIME NOT NULL,
		lastMove DATETIME NOT NULL
	);";
	$Result = mysqli_query($dbh, $SQLCreateTableGames);
        return $Result;
}

function createTableHistory($dbh){
	$SQLCreateTableHistory = "CREATE TABLE history (
		timeOfMove DATETIME NOT NULL,
		gameID SMALLINT NOT NULL,
		curPiece ENUM('pawn', 'bishop', 'knight', 'rook', 'queen', 'king') NOT NULL,
		curColor ENUM('white', 'black') NOT NULL,
		fromRow SMALLINT NOT NULL,
		fromCol SMALLINT NOT NULL,
		toRow SMALLINT NOT NULL,
		toCol SMALLINT NOT NULL,
		replaced ENUM('pawn', 'bishop', 'knight', 'rook', 'queen', 'king') NULL,
		promotedTo ENUM('pawn', 'bishop', 'knight', 'rook', 'queen', 'king') NULL,
		isInCheck BOOL NOT NULL,
		PRIMARY KEY(timeOfMove, gameID)
	);";
	$Result = mysqli_query($dbh, $SQLCreateTableHistory);
        return $Result;
}

function createTableMessages($dbh) {
	$SQLCreateTableMessages = "CREATE TABLE messages (
		msgID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		gameID SMALLINT NOT NULL,
		msgType ENUM('undo', 'draw') NOT NULL,
		msgStatus ENUM('request', 'approved', 'denied') NOT NULL,
		destination ENUM('black', 'white') NOT NULL
	);";
	$Result = mysqli_query($dbh, $SQLCreateTableMessages);
        return $Result;
}

function createTablePieces($dbh){
	$SQLCreateTablePieces = "CREATE TABLE pieces (
		gameID SMALLINT NOT NULL,
		color ENUM('white','black') NOT NULL,
		piece ENUM('pawn','rook','knight','bishop','queen','king') NOT NULL,
		col SMALLINT NOT NULL,
		row SMALLINT NOT NULL
	);";
	$Result = mysqli_query($dbh, $SQLCreateTablePieces);
        return $Result;
}

function createTablePlayers($dbh){
	$SQLCreateTablePlayers = "CREATE TABLE players (
		playerID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
		firstName CHAR(20) NOT NULL,
		lastName CHAR(20) NOT NULL,
		nick CHAR(20) NOT NULL UNIQUE,
		lastAccess DATETIME
	);";
	$Result = mysqli_query($dbh, $SQLCreateTablePlayers);
        return $Result;
}

function tablePlayersHasLastAccessField($dbh) {
	$SQLUpdateTablePlayers = "EXPLAIN players;";
	$Result = mysqli_query($dbh, $SQLUpdateTablePlayers);
	while($Row=mysqli_fetch_row($Result)){
		if($Row[0]=="lastAccess"){
			return true;
		}
	}
	return false;
}

function addLastAccessFieldToPlayersTable($dbh) {
	$SQLUpdateTablePlayers = "ALTER TABLE players ADD lastAccess DATETIME;";
	$Result = mysqli_query($dbh, $SQLUpdateTablePlayers);
        return $Result;
}

function tablePlayersPasswordSupportsHashes($dbh) {
  $SQLDescribePlayers = "SHOW COLUMNS FROM players LIKE 'password'";
  $Result = mysqli_query($dbh, $SQLDescribePlayers);
  if (!$Result)
    return false;

  $Row = mysqli_fetch_assoc($Result);
  if (!$Row)
    return false;

  if (preg_match('/^(?:var)?char\((\d+)\)$/i', (string)$Row['Type'], $matches))
    return ((int)$matches[1] >= 255);

  return false;
}

function widenPlayersPasswordField($dbh) {
  $SQLUpdateTablePlayers = "ALTER TABLE players MODIFY password VARCHAR(255) NOT NULL;";
  $Result = mysqli_query($dbh, $SQLUpdateTablePlayers);
  return $Result;
}

function createTablePreferences($dbh) {
	$SQLCreateTablePreferences = "CREATE TABLE preferences (
		playerID INT NOT NULL,
		preference CHAR(20) NOT NULL,
		value CHAR(50) NULL,
		PRIMARY KEY(playerID, preference)
	);";
	$Result = mysqli_query($dbh, $SQLCreateTablePreferences);
        return $Result;
}

function createTableCommunication($dbh) {
	$SQLCreateTableCommunication = "CREATE TABLE communication (
	  commID smallint(6) NOT NULL auto_increment,
	  gameID smallint(6) default NULL,
	  fromID mediumint(9) default NULL,
	  toID mediumint(9) default NULL,
	  title varchar(255) default NULL,
	  text longtext,
	  postDate datetime NOT NULL default '0000-00-00 00:00:00',
	  expireDate datetime default '0000-00-00 00:00:00',
	  ack tinyint(4) default '0',
	  commType smallint(6) default '0',
	  PRIMARY KEY  (commID)
	);";
	$Result = mysqli_query($dbh, $SQLCreateTableCommunication);
        return $Result;
}

function insertSampleFieldIntoCommunications($dbh) {
	$SQLInsertIntoCommunication = "INSERT INTO communication
	(gameID,fromID,toID,title,text,postDate,expireDate,ack,commType)
	VALUES (NULL,NULL,NULL,'Database Upgrade tSuccessfull','If you see this message
	then the communication table was created successfully... this will allow players
	and admins to communicate with each other by posting
	messages.',NOW(),'0000-00-00 00:00:00','0','0');";
	$Result = mysqli_query($dbh, $SQLInsertIntoCommunication);
        return $Result;
}

function writeLogFile($message) {
	if (! $logfile = fopen("install.log", "a+") ) {
		echo ie(t('error_open_log_file'));
		exit(0);
	}
	fwrite($logfile, $message);
	fclose($logfile);
}

//This function just show the error message when creating the tables.
//ToDo: it must accept internationalization.
function showMessage($message){
    if ($message==1) {
        echo ie(t('table_created_ok')) . "<br>";
    } else {
        echo ie(t('table_created_error')) . "<br>";
    }
}

//This function just show the error message when a table already exists and
//we are not going to create it.
//ToDo: Internationalization.
//ToDo: The probing algoritm must check inside tables to see if it has all its fields.
function showProbingMessage($message){
    if ($message==1) {
        echo ie(t('table_exists_nothing_done')) . "<br>";
    } else {
        echo ie(t('table_missing_will_create')) . "<br>";
    }
}

//This function must log in the mysql server using the $user/$password info
//and create the database named $DBname. Obviously, it won't be called if the
//user does not have direct access to the MySQL server, as will happen in most of the
//free web hosting providers.
function createDB($user, $password, $server, $DBname){
    if (!webchessIsSafeSqlIdentifier($DBname)) {
        return 'error_invalid_database_name';
    }

    $dbh = mysqli_connect($server, $user, $password);
    if (!$dbh) {
        return 'error_db_connect_settings';
    }

    $query = "CREATE DATABASE `" . $DBname . "`";

    try {
        $result = mysqli_query($dbh, $query);
        mysqli_close($dbh);
        return $result ? true : 'step2_status_db_create_failed';
    } catch (mysqli_sql_exception $e) {
        $code = (int)$e->getCode(); // MySQL-Fehlercode
        mysqli_close($dbh);

        // 1007 = Can't create database; database exists
        if ($code === 1007) {
            return 'step2_status_db_exists';
        }

        return 'step2_status_db_create_failed';
    }
}

//This function creates the tables within the database.
//ToDo: be able to write the install.log file.
//Until this is done, the $logMsg .= ... lines are commented.
function createTables($user,$password,$server,$DBname){
    if (!webchessIsSafeSqlIdentifier($DBname)) {
        return ie(t('error_invalid_database_name'));
    }
   $dbh=mysqli_connect($server, $user, $password)
           or die(ie(t('error_db_connect_settings')) . "<br>");
   mysqli_select_db($dbh, $DBname);

   //$logMsg .= "Probing for table games..\n";
    echo ie(t('probing_table_games')) . "<br>";
   if(!tableExists($dbh, "games")) {
   	//$logMsg .= "Creating table games..\n";
       echo ie(t('creating_table_games')) . "<br>";
	$result=createTableGames($dbh);
        showMessage($result);
   }  else {
      showProbingMessage(true);
   }
   //$logMsg .= "Probing for table history..\n";
   echo ie(t('probing_table_history')) . "<br>";
   if(!tableExists($dbh, "history")) {
	//$logMsg .= "Creating table history..\n";
   	echo ie(t('creating_table_history')) . "<br>";
	$result=createTableHistory($dbh);
        showMessage($result);
   }  else showProbingMessage(true);
   //$logMsg .= "Probing for table messages..\n";
   echo ie(t('probing_table_messages')) . "<br>";
   if(!tableExists($dbh, "messages")) {
	//$logMsg .= "Creating table messages..\n";
   	echo ie(t('creating_table_messages')) . "<br>";
	$result=createTableMessages($dbh);
        showMessage($result);
   }  else showProbingMessage(true);
   //$logMsg .= "Probing for table pieces..\n";
   echo ie(t('probing_table_pieces')) . "<br>";
   if(!tableExists($dbh, "pieces")) {
	//$logMsg .= "Creating table pieces..\n";
   	echo ie(t('creating_table_pieces')) . "<br>";
	$result=createTablePieces($dbh);
        showMessage($result);
   } else showProbingMessage(true);
   // ToDo: consider checking for fields named "pCol" and "pRow" in "pieces" table (n8chessnet) or updating to pCol and pRow
   //$logMsg .= "Probing for table players..\n";
   echo ie(t('probing_table_players')) . "<br>";
   if(!tableExists($dbh, "players")) {
	//$logMsg .= "Creating table players..\n";
   	echo ie(t('creating_table_players')) . "<br>";
	$result=createTablePlayers($dbh);
        showMessage($result);
   } else {
	//$logMsg .= "Table players exists. Checking if table players has field lastAccess..\n";
        echo ie(t('players_table_exists_check_last_access')) . "<br>";
	// Check if field "lastAccess" exists..
	if(!tablePlayersHasLastAccessField($dbh)) {
		// if false: update by calling addLastAccessFieldToPlayersTable()
		//$logMsg .= "Adding lastAccess field..\n";
                echo ie(t('adding_last_access_field')) . "<br>";
		addLastAccessFieldToPlayersTable($dbh);
	} else echo ie(t('last_access_exists_nothing_done')) . "<br>";

  echo ie(t('checking_password_hash_support')) . "<br>";
  if(!tablePlayersPasswordSupportsHashes($dbh)) {
    echo ie(t('expanding_password_field')) . "<br>";
    showMessage(widenPlayersPasswordField($dbh));
  } else echo ie(t('password_hash_support_exists')) . "<br>";
   }
   //$logMsg .= "Probing for table preferences..\n";
   echo ie(t('probing_table_preferences')) . "<br>";
   if(!tableExists($dbh, "preferences")) {
	//$logMsg .= "Creating table preferences..\n";
   	echo ie(t('creating_table_preferences')) . "<br>";
	$result=createTablePreferences($dbh);
        showMessage($result);
   } else showProbingMessage(true);
   //$logMsg .= "Probing for table communication..\n";
   echo ie(t('probing_table_communication')) . "<br>";
   if(!tableExists($dbh, "communication")) {
	//$logMsg .= "Creating table communication..\n";
   	echo ie(t('creating_table_communication')) . "<br>";
	$result=createTableCommunication($dbh);
        showMessage($result);
	//$logMsg .= "Inserting message to communication table..\n";
   	echo ie(t('inserting_communication_message')) . "<br>";
	$result=insertSampleFieldIntoCommunications($dbh);
        if ($result==1){
           echo ie(t('communication_message_insert_ok')) . "<br>";
        } else {
            echo "<strong>" . ie(t('communication_message_insert_error')) . "</strong><br>";
        }
   } else {
      showProbingMessage(true);
   }

   //ToDo: Make logfile work properly.
   //Without the proper permision, php won't be able to write any file.
   //In many free web hosting providers it won't be possible to allow proper
   //write permision, so it is not advisable to use this. It is needed a workaround.
   //Following code comented until this workaround is found.
   //echo str_replace("\n","<br />",$logMsg);
   //writeLogFile($logMsg);

   mysqli_close($dbh);
}

################################################################################
################################################################################
################################################################################
require_once 'lang.php';
?>
<!DOCTYPE html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo ie(t('page_title')); ?></title>
</head>
<body>
<div class="installer-language">
    <span><?php echo ie(t('language_label')); ?>:</span>
    <a class="btn <?php echo $installerLang === 'en' ? 'btn-primary' : 'btn-secondary'; ?>"
       href="?lang=en">English</a>
    <a class="btn <?php echo $installerLang === 'de' ? 'btn-primary' : 'btn-secondary'; ?>"
       href="?lang=de">Deutsch</a>
</div>
<h1><?php echo ie(t('page_title')); ?></h1>
<?php
/*
 * All mention to $logMsg has been commented out. Now the installer makes things
 * differently. Now it will show the result in the screen, because some times
 * php won't have write-permissions and it will be problematic for the novice
 * administrator to handle it.
 * It is possible, however, to have both ways: Show the info in the screen and then
 * write it to a log file. rigao (who is writing this) does rather be programing
 * something else in his available time, but feel free to code it yourself.
 */
/*$logMsg = "";
if(file_exists("install.log")) {
	// Enter this condition if file exists..
	echo "ERRROR: File \"install.log\" already exists!<br />";
	echo "  This probably means that Webchess is already installed.<br />";
	echo "  For security reasons, please remove the file first if you wish<br />";
	echo "  to proceed with install<br />";
	$logMsg.= "ERRROR: File \"install.log\" already exists!\n";
	$logMsg.= "  This probably means that Webchess is already installed.\n";
	$logMsg.= "  For security reasons, please remove the file first if you wish\n";
	$logMsg.= "  to proceed with install";
	writeLogFile($logMsg);
	exit(0);
}*/

/*
 * This installer will flow through this switch. It will start in the default,
 * then it will go to case 1, 2, 3 and finish. Each case has its own form which
 * will keep the values which need to be send to the next form and will change the
 * $_POST['confirm'] variable to access the correct case.
 *
 * The cases roughtly follow the steps shown in the installer. However, normally
 * the operations of each step will be made in the next case (so, the operations
 * which belong to step 1 will be made at the beginning of case 2).
 */
$confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
$action = isset($_POST['action']) ? (string)$_POST['action'] : 'next';

if ($action === 'back') {
    if ($confirm === '3') {
        $confirm = '2';
    } elseif ($confirm === '2') {
        $confirm = '1';
    } elseif ($confirm === 'finish') {
        $confirm = '3';
    } else {
        $confirm = '';
    }
}
switch($confirm){
   //Step 1 of the installation procedure: Setup the database. The beginning of
   //case 2 has code of step 1 too.
   case 1:
      //Presentation of the installer.
      ?>
       <h2><?php echo ie(t('step1_heading')); ?></h2>

       <p><?php echo ie(t('step1_p1')); ?></p>
       <p><?php echo ie(t('step1_p2')); ?></p>

      <?php // This form will be sent to case 2, to end step 1 and to start step 2. ?>
         <form action='install.php' method='POST' name='step1'>
             <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
             <table>
            <tr><td><label for="server"><?php echo ie(t('label_server')); ?></label></td><td><input type="text" id="server" name="server"/></td></tr>
            <tr><td><label for="user"><?php echo ie(t('label_user')); ?></label></td><td><input type="text" id="user" name="user"/></td></tr>
            <tr><td><label for="pass"><?php echo ie(t('label_password')); ?></label></td><td><input type="password" id="pass" name="pass"/></td></tr>
            <tr><td><label for="DBname"><?php echo ie(t('label_db_name')); ?></label></td><td><input type="text" id="DBname" name="DBname"/></td></tr>
            <tr><td colspan="2"><input type="checkbox" id="skip" name="skip" value="skip"/><label for="skip"><?php echo ie(t('label_skip')); ?></label></td></tr>
            <tr><td colspan="2"><input type='hidden' name='confirm' value='2' />
                    <input type="hidden" name="action" value="next">
                    <input type='submit' value="<?php echo ie(t('submit_continue')); ?>" /></td></tr>
         </table></form>
      <?php
      break;

/******************************************************************************/

   //This case features last part of the step 1 and the presentation of the Step 2
   // of the installation procedure.
   case 2:
      //We make the installation procedure from step 1.
      //If the database is not created yet, we will create it.
$action = isset($_POST['action']) ? (string)$_POST['action'] : 'next';

if ($action !== 'back') {
    if (isset($_POST['skip']) && $_POST['skip'] == 'skip') {
        echo ie(t('step2_status_skip_db')) . "<br>";
    } else {
        echo ie(t('step2_status_try_create_db')) . "<br>";
        $result = createDB($_POST['user'], $_POST['pass'], $_POST['server'], $_POST['DBname']);
        if ($result == true) {
            echo ie(t('step2_status_db_created')) . "<br>";
        } else {
            //This message will only show up if we are able to login the server
            //but the database is not created successfully.
            echo ie(t('step2_status_db_create_failed')) . "<br>";
        }
    }
}

      //We start step 2 with its presentation. It will be completed in case 3.
      ?>
      <h2><?php echo ie(t('step2_heading')); ?></h2>

       <p>
           <?php echo ie(t('step2_p1_prefix')); ?>
           '<?php echo ie((string)$_POST['DBname']); ?>'
           <?php echo ie(t('step2_p1_suffix')); ?>
       </p>
       <p><?php echo ie(t('step2_p2')); ?></p>

      <?php //This form will be sent to case 3, to end step 2 and to start step 3. ?>
         <form action='install.php' method='POST' name='step2'>
             <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
             <table>
            <tr><td colspan="2">
                  <input type="hidden" name="server" value="<?php echo $_POST['server']; ?>"/></td></tr>
            <tr><td><label for="user2">User:</label></td><td><input type="text" id="user2" name="user"/></td></tr>
            <tr><td><label for="pass2">Password:</label></td><td><input type="password" id="pass2" name="pass"/></td></tr>
            <tr><td colspan="2">
                  <input type="hidden" name="DBname" value="<?php echo $_POST['DBname']; ?>"/></td></tr>
            <tr><td colspan="2">
		<?php if ($_POST['user'] != '') { ?>
                  <input type="checkbox" id="reuse" name="reuse" value="true"/>
            <label for="reuse"><?php echo ie(t('reuse_user_step1')); ?></label>
		<?php } ?>
                  <input type="hidden" name="user_last" value="<?php echo $_POST['user']; ?>"/>
                  <input type="hidden" name="pass_last" value="<?php echo $_POST['pass']; ?>"/>
               </td></tr>
            <tr><td colspan="2">
                    <input type='hidden' name='confirm' value='3' />
                    <button type="submit" name="action" value="back"><?php echo ie(t('button_back')); ?></button>
                    <button type="submit" name="action" value="next"><?php echo ie(t('submit_continue')); ?></button>
                </td>
            </tr>
         </table></form>
      <?php
      break;

/******************************************************************************/

   //This case features last part of the step 2 and the presentation of the Step 3
   // of the installation procedure.
   case 3:
      //We make the installation procedure from step 2.
      //Little code to handle if there is a new user to create the tables.
      if (isset($_POST['reuse']) && $_POST['reuse']=='true')
      {
         $user=$_POST['user_last'];
         $pass=$_POST['pass_last'];
      } else {
         $user=$_POST['user'];
         $pass=$_POST['pass'];
      }
      $server=$_POST['server'];
      $DBname=$_POST['DBname'];
      //Once we know the user which will create the tables, we call the function
      //which will create them.
       $action = isset($_POST['action']) ? (string)$_POST['action'] : 'next';
       $DBname = trim((string)($_POST['DBname'] ?? ''));
       $result = true; // neutral für Back-Navigation
       if ($action !== 'back') {
            $result = createTables($user, $pass, $server, $DBname);
       }
       if ($action !== 'back' && $result !== true) {
           echo '<p><strong>' . ie(t((string)$result)) . '</strong></p>';
           // Optional: Hinweis zu erlaubten Zeichen
           if ($result === 'error_invalid_database_name') {
               echo '<p>' . ie(t('db_name_allowed_chars_hint')) . '</p>';
           }
           break;

           // kleines Back-Form, das wieder zu Schritt 2 geht und Werte mitgibt
           ?>
           <form action="install.php" method="post">
               <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
               <input type="hidden" name="confirm" value="2">
               <input type="hidden" name="server" value="<?php echo ie((string)$server); ?>">
               <input type="hidden" name="DBname" value="<?php echo ie((string)$DBname); ?>">
               <input type="hidden" name="user" value="<?php echo ie((string)$user); ?>">
               <input type="hidden" name="pass" value="<?php echo ie((string)$pass); ?>">
               <input type="submit" value="<?php echo ie(t('back_to_step2')); ?>">
           </form>
           <?php
           break;
       }
      // We start step 3 with this presentation. This step 3 is breaked into two
      // substeps. The first one will generate config.php, while the second one
      // will setup the user which will use WebChess to access MySQL.

      // Step 3.1:
      ?>
       <h2><?php echo ie(t('step3_heading')); ?></h2>

       <p><?php echo ie(t('step3_p1')); ?></p>
       <p><?php echo ie(t('step3_p2')); ?></p>
       <p><?php echo ie(t('step3_p3')); ?></p>

         <?php
            //This form will generate the config.php file and put it to download.
            //It is possible that the hosting provider will add some javascript code
            //at the end of the file for tracking reasons. Then it must be deleted
            //by the user before he uploads it. This needs to be very clear in the
            //installer and in the installer FAQ.
         ?>
         <form action='makeConfig.php' method='POST' name='generateConfigForm' target="_blank">
             <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
             <table>
            <tr><td colspan="2"><b><u><?php echo ie(t('section_database_settings')); ?></u></b>
                  <input type="hidden" name="server" value="<?php echo $_POST['server']; ?>"/></td></tr>
            <tr><td><label for="user3"><?php echo ie(t('label_user')); ?></label></td><td><input type="text" id="user3" name="user"/></td></tr>
            <tr><td><label for="pass3"><?php echo ie(t('label_password')); ?></label></td><td><input type="password" id="pass3" name="pass"/></td></tr>
            <tr><td colspan="2">
                  <input type="hidden" name="DBname" value="<?php echo $_POST['DBname']; ?>"/></td></tr>
            <tr><td colspan="2">
                  <input type="checkbox" id="reuse2" name="reuse" value="true"/><label for="reuse2"><?php echo ie(t('reuse_user_step2')); ?></label>
                  <input type="hidden" name="user_last" value="<?php echo $user; ?>"/>
                  <input type="hidden" name="pass_last" value="<?php echo $pass; ?>"/>
               </td></tr>
            <tr><td colspan="2"><b><u><?php echo ie(t('section_server_settings')); ?></u></b></td></tr>
                 <tr><td><label for="timeout"><?php echo ie(t('label_timeout_minutes')); ?></label></td><td><input type="text" id="timeout" name="timeout" value="30"/> <?php echo ie(t('hint_timeout_minutes')); ?></td></tr>
                 <tr><td><label for="login_lock_seconds"><?php echo ie(t('label_login_lock_seconds')); ?></label></td><td><input type="text" id="login_lock_seconds" name="login_lock_seconds" value="900"/> <?php echo ie(t('hint_login_lock_seconds')); ?></td></tr>
                 <tr><td><label for="expire"><?php echo ie(t('label_expire_days')); ?></label></td><td><input type="text" id="expire" name="expire"/> <?php echo ie(t('hint_expire_days')); ?></td></tr>
                 <tr><td><label for="autoreload"><?php echo ie(t('label_autoreload_seconds')); ?></label></td><td><input type="text" id="autoreload" name="autoreload"/> <?php echo ie(t('hint_autoreload_seconds')); ?></td></tr>
                 <tr><td><label for="mail_not"><?php echo ie(t('label_mail_notification')); ?></label></td><td><input type="checkbox" id="mail_not" name="mail_not" value="1"/></td></tr>
                 <tr><td><label for="mail_adr"><?php echo ie(t('label_mail_address')); ?></label></td><td><input type="text" id="mail_adr" name="mail_adr"/> <?php echo ie(t('hint_mail_address')); ?></td></tr>
                 <tr><td><label for="url"><?php echo ie(t('label_main_page_address')); ?></label></td><td><input type="text" id="url" name="url"/> <?php echo ie(t('hint_main_page_address')); ?></td></tr>
                 <tr><td><label for="maxUsers"><?php echo ie(t('label_max_users')); ?></label></td><td><input type="text" id="maxUsers" name="maxUsers"/> <?php echo ie(t('hint_max_users')); ?></td></tr>
                 <tr><td><label for="maxGames"><?php echo ie(t('label_max_games')); ?></label></td><td><input type="text" id="maxGames" name="maxGames"/> <?php echo ie(t('hint_max_games')); ?></td></tr>
                 <tr><td><label for="changeNick"><?php echo ie(t('label_change_nick')); ?></label></td><td><input type="checkbox" id="changeNick" name="changeNick" value="1"/></td></tr>
                 <tr><td><label for="newUsers"><?php echo ie(t('label_new_users')); ?></label></td><td><input type="checkbox" id="newUsers" name="newUsers" value="1"/></td></tr>
                 <tr><td><label for="size"><?php echo ie(t('label_board_square_size')); ?></label></td><td><input type="text" id="size" name="size"/> <?php echo ie(t('hint_board_square_size')); ?></td></tr>
                 <tr><td><label for="imageExtension"><?php echo ie(t('label_image_extension')); ?></label></td><td><select id="imageExtension" name="imageExtension" size="1">
               <option value="gif">gif</option>
               <option value="png">png</option>
                  </select>
               </td></tr>
            <tr><td colspan="2"><input type='hidden' name='confirm' value='finish' />
                    <input type='submit' value="<?php echo ie(t('submit_generate_config')); ?>" /></td></tr>
         </table></form>

         <?php //This form is just to end the installation procedure. ?>
         <p><?php echo ie(t('after_config_download_text')); ?></p>

         <form action='install.php' method='POST' name='finish'>
             <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
            <input type='hidden' name='confirm' value='finish' />
             <button type="submit" name="action" value="back"><?php echo ie(t('button_back')); ?></button>
             <button type="submit" name="action" value="next"><?php echo ie(t('submit_finish')); ?></button>
         </form>
      <?php

      break;

/******************************************************************************/

   case 'finish':
      ?>
       <h2><?php echo ie(t('finish_heading')); ?></h2>

       <p><?php echo ie(t('finish_p1')); ?></p>
       <p><?php echo ie(t('finish_p2')); ?></p>
       <p><?php echo ie(t('finish_p3')); ?></p>
       <p><i><?php echo ie(t('finish_p4')); ?></i></p>

      <?php
      break;

/******************************************************************************/

   //Welcome page.
   default:
      //The beginning of the installation process.

      /* --- Auto-redirect banner -------------------------------------------- */
      if (isset($_GET['reason']))
      {
         $reasonMessages = array(
            'no_config'     => '⚠ No <code>config.php</code> found. Please complete the installation below.',
            'not_installed' => '⚠ WebChess database or required tables are missing. Please complete the installation below.',
         );
         $reason = $_GET['reason'];
         $msg = isset($reasonMessages[$reason]) ? $reasonMessages[$reason] : '⚠ WebChess is not yet installed. Please complete the setup below.';
         echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:12px 16px;margin-bottom:16px;color:#664d03;">' . $msg . '</div>';
      }
      /* --------------------------------------------------------------------- */
      ?>
       <p><?php echo ie(t('default_intro_p1')); ?></p>
       <p><?php echo ie(t('default_intro_p2')); ?></p>

      <ul><li><?php echo ie(t('step1_heading')); ?></li>
             <li><?php echo ie(t('step2_heading')); ?></li>
             <li><?php echo ie(t('step3_heading')); ?></li></ul>

       <p><?php echo ie(t('default_intro_p3')); ?></p>

      <form action="install.php" method='POST'>
          <input type="hidden" name="installer_lang" value="<?php echo ie($installerLang); ?>">
          <input type="checkbox" id="confirm_proceed" name="confirm" value="1" /> <label for="confirm_proceed"><?php echo ie(t('ready_proceed')); ?></label>
      <br />
          <input type="submit" value="<?php echo ie(t('install_button')); ?>" />
      </form>
      <?php
      break;
}
?>
</body>
</html>
