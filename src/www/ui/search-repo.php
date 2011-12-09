<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

define("TITLE_search_repo", _("Search the Repository"));

class search_repo extends FO_Plugin
{
  var $Name       = "search_repo";
  var $Title      = TITLE_search_repo;
  var $Version    = "1.0";
  // var $MenuList   = "Help::Debug::Debug Repository";
  var $Dependency = array("view","browse");
  var $DBaccess   = PLUGIN_DB_READ;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Search based on repository keys");
    menu_insert("Search::Repository",0,$this->Name,$text);
  } // RegisterMenus()

  /**
   * \brief Given a pfile_pk, return all uploadtree.
   */
  function GetUploadtreeFromPfile($Pfilepk,$Page)
  {
    global $PG_CONN;
    $Max = 50;
    $Offset = $Max * $Page;
    $sql = "SELECT * FROM pfile
      INNER JOIN uploadtree ON pfile_pk = '$Pfilepk'
      AND pfile_fk = pfile_pk
      ORDER BY pfile_fk,ufile_name LIMIT $Max OFFSET $Offset;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Count = pg_num_rows($result);
    $V = "";
    if (($Page > 0) || ($Count >= $Max))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name;
      $Uri .= "&search=" . urlencode(GetParm("search",PARM_STRING));
      $VM = MenuEndlessPage($Page, ($Count >= $Max), $Uri) . "<P />\n";
      $V .= $VM;
    }
    else
    {
      $VM = "";
    }
    $V .= Dir2FileList($result,"browse","view",$Page*$Max + 1);
    pg_free_result($result);
    if (!empty($VM)) { $V .= "<P />\n" . $VM; }
    return($V);
  } // GetUploadtreeFromPfile()

  /**
   * \brief Given a sha1.md5.len, return all uploadtree.
   */
  function GetUploadtreeFromRepo($Repo,$Page)
  {
    /* Split repo into Sha1, Md5, and Len */
    $Repo = strtoupper($Repo);
    list($Sha1,$Md5,$Len) = split("[.]",$Repo,3);
    $Sha1 = preg_replace("/[^A-F0-9]/","",$Sha1);
    $Md5 = preg_replace("/[^A-F0-9]/","",$Md5);
    $Len = preg_replace("/[^0-9]/","",$Len);
    if (strlen($Sha1) != 40) { return; }
    if (strlen($Md5) != 32) { return; }
    if (strlen($Len) < 1) { return; }

    /* Get the pfile */
    global $PG_CONN;
    $sql = "SELECT pfile_pk FROM pfile
      WHERE pfile_sha1 = '$Sha1'
      AND pfile_md5 = '$Md5'
      AND pfile_size = '$Len';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['pfile_pk'])) { return; }
    return($this->GetUploadtreeFromPfile($row['pfile_pk'],$Page));
  } // GetUploadtreeFromRepo()

  /**
   * \brief Given a string to search for, search for it!
   * This identifies whether the string is a pfile_pk or sha1.md5.len.
   *
   * \return all uploadtree, or null if none found.
   */
  function Search($String,$Page=0)
  {
    if (preg_match("/^[0-9]+$/",$String) > 0)
    {
      return($this->GetUploadtreeFromPfile($String,$Page));
    }
    if (preg_match("/^[0-9a-fA-F]{40}\.[0-9a-fA-F]{32}.[0-9]+$/",$String) > 0)
    {
      return($this->GetUploadtreeFromRepo($String,$Page));
    }
    $V = _("Search string is not a valid format.");
    return($V);
  } // Search()

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V .= menu_to_1html(menu_find("Search",$MenuDepth),1);

        $SearchArg = GetParm("search",PARM_STRING);
        $Page = GetParm("page",PARM_INTEGER);
        if (empty($Page)) { $Page = 0; }

        $V .= _("Given a file key (pfile_pk) or repository identifier (sha1.md5.length), return the list of files.\n");
        $V .= "<P /><form method='post'>\n";
        $V .= _("Enter the pfile key or repository identifier:<P />");
        $V .= "<INPUT type='text' name='search' size='60' value='" . htmlentities($SearchArg) . "'><P>\n";
        $text = _("Find");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";

        if (!empty($SearchArg))
        {
          $V .= "<hr>\n";
          $text = _("Files associated with");
          $V .= "<H2>$text " . htmlentities($SearchArg) . "</H2>\n";
          $V .= $this->Search($SearchArg,$Page);
        }

        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  } // Output()


};
$NewPlugin = new search_repo;
$NewPlugin->Initialize();

?>
