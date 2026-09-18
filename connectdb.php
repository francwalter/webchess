<?php
	// $Id: connectdb.php,v 1.4 2010/08/14 16:57:54 sandking Exp $

/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores

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

	/* load settings */
	if (!isset($_CONFIG))
		require 'config.php';

	/* connect to database using MySQLi (PHP 8.5 compatible) */
	$dbh = mysqli_connect($CFG_SERVER, $CFG_USER, $CFG_PASSWORD, $CFG_DATABASE)
		or die('WebChess cannot connect to the database. Please check the database settings in your config. Error: ' . mysqli_connect_error());

	// Set charset to utf8mb4 for proper character handling
	mysqli_set_charset($dbh, "utf8mb4");

  /* password_hash() needs more room than the legacy CHAR(16) schema provides. */
  if (isset($CFG_TABLE['players']))
  {
    $playersTable = $CFG_TABLE['players'];
    $tmpPasswordColumn = null;

    $stmtColumnInfo = @mysqli_prepare(
      $dbh,
      "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'password' LIMIT 1"
    );
    if ($stmtColumnInfo)
    {
      mysqli_stmt_bind_param($stmtColumnInfo, "ss", $CFG_DATABASE, $playersTable);
      mysqli_stmt_execute($stmtColumnInfo);
      $tmpColumnInfo = mysqli_stmt_get_result($stmtColumnInfo);
      $tmpPasswordColumn = $tmpColumnInfo ? mysqli_fetch_assoc($tmpColumnInfo) : null;
      mysqli_stmt_close($stmtColumnInfo);
    }

    if ($tmpPasswordColumn)
    {
      if (preg_match('/^(?:var)?char\((\d+)\)$/i', (string)$tmpPasswordColumn['COLUMN_TYPE'], $matches))
      {
        $tmpPasswordLength = (int)$matches[1];
        if ($tmpPasswordLength < 255)
        {
          if (!preg_match('/^[A-Za-z0-9_]+$/', $playersTable))
          {
            error_log("WebChess: invalid players table name for schema upgrade check.");
          }
          else
          {
            $tmpAlterTable = @mysqli_query($dbh, "ALTER TABLE `" . $playersTable . "` MODIFY password VARCHAR(255) NOT NULL");
            if (!$tmpAlterTable)
              error_log("WebChess: could not widen players.password column for password hashes: " . mysqli_error($dbh));
            else
              error_log("WebChess: upgraded players.password column to VARCHAR(255). ");
          }
        }
      }
    }
  }

