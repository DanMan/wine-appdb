<?php
/********************/
/* Users Management */
/********************/

require("path.php");
require(BASE."include/incl.php");

apidb_header("Admin Users Management");

if(!$_SESSION['current']->hasPriv("admin"))
    util_show_error_page_and_exit("Insufficient privileges.");


// we want to delete a user
if(isset($aClean['sAction']) && $aClean['sAction'] == "delete" &&
    is_numeric($aClean['iUserId']))
{
    $oUser = new User($aClean['iUserId']);
    $oUser->delete();
}


// search form
echo html_frame_start("Users Management","400","",0);

$aClean['sSearch'] = isset($aClean['sSearch']) ? $aClean['sSearch'] : '';

$sLimit100 = $sLimit200 = $sLimit500 = '';
if ( isset($aClean['iLimit']) )
{
    switch ($aClean['iLimit'])
    {
        case '100':
            $sLimit100 = 'selected';
            break;
        case '200':
            $sLimit200 = 'selected';
            break;
        case '500':
            $sLimit500 = 'selected';
            break;
    }
}

$sOrder1 = $sOrder2 = $sOrder3 = '';
if ( isset($aClean['sOrderBy']) )
{
    switch ($aClean['sOrderBy'])
    {
        case 'email':
            $sOrder1 = 'selected';
            break;
        case 'realname':
            $sOrder2 = 'selected';
            break;
        case 'created':
            $sOrder3 = 'selected';
            break;
    }
}

?>
    <form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
    <table width="100%" border=0 cellpadding=0 cellspacing=0>
        <tr>
            <td class="color1">Pattern</td>
            <td>
            <input type="text" name="sSearch" value="<?php echo $aClean['sSearch'];?>">
            <br><small>(leave blank to match all)</small>
            </td>
        </tr>
        <tr>
            <td class="color1">Show first</td>
            <td>
                <select name="iLimit">
                    <option value="100" <?php echo $sLimit100; ?>>100 results</option>
                    <option value="200" <?php echo $sLimit200; ?>>200 results</option>
                    <option value="500" <?php echo $sLimit500; ?>>500 results</option>
                </select>
            </td>
        </tr>
        <tr>
            <td class="color1">Order by</td>
            <td>
                <select name="sOrderBy">
                    <option value="email" <?php echo $sOrder1;?>>e-mail</option>
                    <option value="realname" <?php echo $sOrder2;?>>real name</option>
                    <option value="created" <?php echo $sOrder3;?>>creation date</option>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan=2 class=color3 align=center><input type="submit" name="sSubmit" value="List Users" class="button"></td>
        </tr>
    </table>
  </form>
<?php
echo html_frame_end();

// if the search form was submitted
if(isset($aClean['sSubmit']))
{
    echo html_frame_start("Query Results","90%","",0);
    echo "<table width='100%' border=0 cellpadding=3 cellspacing=0>\n\n";
    echo "<tr class=color4>\n";
    echo "    <td>Real name</td>\n";
    echo "    <td>E-mail</td>\n";
    echo "    <td>Creation date</td>\n";
    echo "    <td>Last connected</td>\n";
    echo "    <td>Roles</td>\n";
    echo "    <td align=\"center\">Action</td>\n";
    echo "</tr>\n\n";
    if(is_numeric($aClean['iLimit']) && in_array($aClean['sOrderBy'],array("email","realname","created")))
    {
        $sSearch = $aClean['sSearch'];
        $sQuery = "SELECT * FROM user_list 
                   WHERE realname LIKE '%?%' OR email LIKE '%?%'
                   ORDER BY ?
                   LIMIT ?";
        $hResult = query_parameters($sQuery, $sSearch, $sSearch, $aClean['sOrderBy'],
                                $aClean['iLimit']);
        $i=0;
        while($hResult && $oRow = query_fetch_object($hResult))
        {
            $oUser = new User($oRow->userid);
            $sAreYouSure = "Are you sure that you want to delete user ".addslashes($oUser->sRealname)." ?";
            echo "<tr class=\"color".(($i++)%2)."\">\n";
            echo "    <td>".$oUser->sRealname."</td>\n";
            echo "    <td>".$oUser->sEmail."</td>\n";
            echo "    <td>".$oUser->sDateCreated."</td>\n";
            echo "    <td>".$oUser->sStamp."</td>\n";
            echo "    <td>";
            if($oUser->hasPriv("admin")) echo "A";
            if($oUser->isMaintainer()) echo "M";
            echo "    </td>\n";
            echo "    <td align=\"center\">[<a href=\"../preferences.php?iUserId=".$oRow->userid."&sSearch=".$sSearch."&iLimit=".$aClean['iLimit']."&sOrderBy=".$aClean['sOrderBy']."\">edit</a>]&nbsp;[<a onclick=\"if(!confirm('".$sAreYouSure."'))return false;\" \"<a href=\"".$_SERVER['PHP_SELF']."?sAction=delete&iUserId=".$oRow->userid."&sSearch=".$sSearch."&iLimit=".$aClean['iLimit']."&sOrderBy=".$aClean['sOrderBy']."&sSubmit=true\">delete</a>]</td>\n";
            echo "</tr>\n\n";
        }
    }
    echo "</table>";
    echo html_frame_end();
}
apidb_footer();
?>
