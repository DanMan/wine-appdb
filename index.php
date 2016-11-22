<?php
/**
 * Application database index page.
 *
 * TODO:
 *  - rename outputTopXRowAppsFromRating according to our coding standards
 *  - rename variables that don't follow our coding standards
 */

// application environment
require("path.php");
require(BASE."include/incl.php");

/* used by outputTopXRowAppsFromRating() to reduce duplicated code */
function outputTopXRow($oRow)
{
    $oVersion = new Version($oRow->versionId);
    $oApp = new Application($oVersion->iAppId);
    $img = Screenshot::get_random_screenshot_img(null, $oRow->versionId, false); // image, disable extra formatting

    // create the table row
    $oTableRow = new TableRow();
    $oTableRow->SetClass("white");

    // create the cells that represent the row
    $oTableCell = new TableCell(version::fullNameLink($oVersion->iVersionId));
    $oTableCell->SetClass("app_name");
    $oTableRow->AddCell($oTableCell);
    $oTableRow->AddTextCell(util_trim_description($oApp->sDescription));
    $oTableCell = new TableCell($img);
    $oTableCell->SetClass("text-center padding-left-md padding-right-md");
    $oTableRow->AddCell($oTableCell);

    // create a new TableRowclick
    $oTableRowClick = new TableRowClick($oVersion->objectMakeUrl());

    // set the click property of the html table row
    $oTableRow->SetRowClick($oTableRowClick);

    // output the entire table row
    echo $oTableRow->GetString();
    echo "\n";
}

/* Output the rows for the Top-X tables on the main page */
function outputTopXRowAppsFromRating($sRating, $iNumApps)
{
    /* clean the input values so we can continue to use query_appdb() */
    $sRating = query_escape_string($sRating);
    $iNumApps = query_escape_string($iNumApps);

    /* list of versionIds we've already output, so we don't output */
    /* them again when filling in any empty spots in the list */
    $aVersionId = array();

    $sQuery = "SELECT appVotes.versionId, COUNT( appVotes.versionId ) AS c
           FROM appVotes, appVersion
           WHERE appVersion.rating = '?'
           AND appVersion.versionId = appVotes.versionId
           AND appVersion.state = 'accepted'
           GROUP BY appVotes.versionId
           ORDER BY c DESC
           LIMIT ?";
    $hResult = query_parameters($sQuery, $sRating, $iNumApps);
    $iNumApps -= query_num_rows($hResult); /* take away the rows we are outputting here */
    while($oRow = query_fetch_object($hResult))
    {
        /* keep track of the apps we've already output */
        $aVersionId[] = $oRow->versionId;
        outputTopXRow($oRow);
    }

    /* if we have no more app entries we should stop now and save ourselves a query */
    if(!$iNumApps) return;

    /* if we have any empty spots in the list, get these from applications with images */
    $sQuery = "SELECT DISTINCT appVersion.versionId
           FROM appVersion, appData
           WHERE appVersion.rating = '$sRating'
           AND appVersion.versionId = appData.versionId
           AND appVersion.state = 'accepted'
           AND appData.type = 'screenshot'
           AND appData.state = 'accepted'";

    /* make sure we exclude any apps we've already output */
    foreach($aVersionId as $key=>$value)
        $sQuery.="AND appVersion.versionId != '".$value."' ";

    $sQuery .= " LIMIT $iNumApps";

    /* get the list that will fill the empty spots */
    $hResult = query_appdb($sQuery);
    while($oRow = query_fetch_object($hResult))
        outputTopXRow($oRow);
}

apidb_header("Wine Application Database");

?>

<div class="row">
    <div class="col-md-6 col-lg-8">

<h1 class="whq-app-title">Welcome</h1>

<p>This is the Wine Application Database (AppDB). Here you can get information on application
compatibility with Wine.</p>

<?php

$str_benefits="
    <ul>
        <li>Ability to <a href=\"https://wiki.winehq.org/AppDB_Voting_Help\" title=\"help on voting\">vote</a> on your favorite applications</li>
        <li>Ability to customize the layout and behavior of the AppDB and comments system</li>
        <li>Take credit for your witty posts</li>
        <li>Ability to sign up to be an <a href=\"https://wiki.winehq.org/AppDB_Maintainer_Guidelines\"
            title=\"information about application maintainers\">application maintainer</a></li>
        <li>Submit new applications and versions</li>
        <li>Submit new screenshots</li>
    </ul>
";

if (!$_SESSION['current']->isLoggedIn())
{
    echo "<p>Most of the features of the Application Database require that you have a user account and
    are logged in. Some of the benefits of membership are:</p>

    $str_benefits

    <p>So, what are you waiting for? <a href=\"".login_url()."\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-sign-in\"></i> Log in</a>
    or <a href=\"account.php?sCmd=new\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-user-plus\"></i> register</a> now! Your help in
    stomping out Wine issues will be greatly appreciated.</p>";
}
else
{
    echo "
    <p>As an Application Database member you enjoy some exclusive benefits like:</p>

    $str_benefits

    <p>We&#8217;d like to thank you for being a member and being logged in to the system. Your help in
    stomping out Wine issues will be greatly appreciated.</p>";

}

?>
<?php

    $iNumApps = number_format(version::objectGetEntriesCount('accepted'));

    $voteQuery = "SELECT appVotes.versionId, count(userId) as count ".
        "FROM appVotes ".
        "GROUP BY versionId ORDER BY count DESC LIMIT 1";
    $hResult = query_parameters($voteQuery);
    $oRow = query_fetch_object($hResult);

    echo "<p>There are <b>$iNumApps</b> applications currently in the database,\n";

    // don't mention the top application if there are no votes yet
    if (!empty($oRow))
    {
        if($oRow->versionId)
        {
            $shVoteAppLink = version::fullNameLink($oRow->versionId);
            echo " with $shVoteAppLink being the\n";
            echo "top <a href='votestats.php'>voted</a> application.\n";
        }
        else
        {
            echo " please <a href=\"https://wiki.winehq.org/AppDB_Voting_Help\">vote</a> for your favorite application.\n";
        }
    }

    echo "</p>\n";
?>

</div>
    <div class="col-md-6 col-lg-4">
        <img src="images/appdb_montage.jpg" class="fill-width" alt="Wine AppDB">
    </div>
</div>

<div class="row topx_style platinum">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="objectManager.php?sClass=application&sTitle=Browse+Applications&iappVersion-ratingOp0=5&sappVersion-ratingData0=Platinum&sOrderBy=appName&bAscending=true">Platinum</a> List
    </div>
    Applications which install and run flawlessly on an out-of-the-box Wine installation
  </div>
  <div>
    <table class="platinum" width="100%">
      <thead>
      <tr class="rowtitle">
        <td width="10%">Application</td><td width="80%">Description</td><td width="10%">Screenshot</td>
      </tr>
      </thead>
      <tbody>
      <?php
      outputTopXRowAppsFromRating('Platinum', 10);
      ?>
      </tbody>
    </table>
  </div>
</div>
<br>

<div class="row topx_style gold">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="objectManager.php?sClass=application&sTitle=Browse+Applications&iappVersion-ratingOp0=5&sappVersion-ratingData0=Gold&sOrderBy=appName&bAscending=true">Gold</a> List
    </div>
    Applications that work flawlessly with some special configuration
  </div>
  <div>
    <table class="gold" width="100%">
      <thead>
      <tr class="rowtitle">
        <td width="10%">Application</td><td width="80%">Description</td><td width="10%">Screenshot</td>
      </tr>
      </thead>
      <tbody>
      <?php
      outputTopXRowAppsFromRating('Gold', 10);
      ?>
      </tbody>
    </table>
  </div>
</div>
<br>

<div class="row topx_style silver">
  <div class="rating_header">
    <div class="rating_title">
      Top-10 <a href="objectManager.php?sClass=application&sTitle=Browse+Applications&iappVersion-ratingOp0=5&sappVersion-ratingData0=Silver&sOrderBy=appName&bAscending=true">Silver</a> List
    </div>
    Applications with minor issues that do not affect typical usage
  </div>
  <div>
    <table class="silver" width="100%">
      <thead>
      <tr class="rowtitle">
        <td width="10%">Application</td><td width="80%">Description</td><td width="10%">Screenshot</td>
      </tr>
      </thead>
      <tbody>
      <?php
      outputTopXRowAppsFromRating('Silver', 10);
      ?>
      </tbody>
    </table>
  </div>
</div>

<?php

apidb_footer();

?>
