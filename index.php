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

    $iNumApps = version::objectGetEntriesCount('accepted');

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
            echo " please <a href=\"".BASE."help/?sTopic=voting\" title=\"help on voting\"".
                "style=\"cursor: help\">vote</a> for your favourite application.\n";
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
